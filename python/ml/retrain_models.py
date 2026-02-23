#!/usr/bin/env python3
"""
Réentraîner les modèles ML avec les nouvelles images collectées
Utilise les images dans /python/data/raw/
"""

import os
import json
import numpy as np
import logging
from pathlib import Path
from collections import defaultdict

import tensorflow as tf
from tensorflow import keras
from tensorflow.keras import layers
from tensorflow.keras.preprocessing.image import ImageDataGenerator, load_img, img_to_array
from sklearn.model_selection import train_test_split

# Configuration du logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Chemins
SCRIPT_DIR = Path(__file__).parent
DATA_DIR = SCRIPT_DIR.parent / "data" / "raw"
MODEL_DIR = SCRIPT_DIR / "model"
OUTPUT_MODEL = MODEL_DIR / "food_classifier.h5"
LABELS_FILE = MODEL_DIR / "labels.json"

IMAGE_SIZE = (224, 224)
BATCH_SIZE = 32
EPOCHS = 50
VALIDATION_SPLIT = 0.2

def get_food_categories():
    """Récupérer les catégories depuis les répertoires de données"""
    categories = []
    if DATA_DIR.exists():
        for item in sorted(DATA_DIR.iterdir()):
            if item.is_dir():
                categories.append(item.name)
    return categories

def load_images_and_labels(categories):
    """Charger les images et leurs labels"""
    images = []
    labels = []
    label_to_idx = {label: idx for idx, label in enumerate(categories)}
    
    logger.info(f"📁 Chargement des images depuis {DATA_DIR}...")
    total_images = defaultdict(int)
    
    for category in categories:
        category_dir = DATA_DIR / category
        if not category_dir.exists():
            logger.warning(f"⚠️  Catégorie manquante: {category}")
            continue
            
        image_files = list(category_dir.glob("*.jpg")) + list(category_dir.glob("*.png"))
        logger.info(f"  ✓ {category}: {len(image_files)} images")
        total_images[category] = len(image_files)
        
        for img_file in image_files:
            try:
                # Charger et redimensionner l'image
                img = load_img(str(img_file), target_size=IMAGE_SIZE)
                img_array = img_to_array(img) / 255.0  # Normaliser
                
                images.append(img_array)
                labels.append(label_to_idx[category])
            except Exception as e:
                logger.warning(f"⚠️  Erreur chargement {img_file}: {e}")
    
    logger.info(f"\n📊 Total: {sum(total_images.values())} images chargées")
    for cat, count in sorted(total_images.items()):
        logger.info(f"   {cat}: {count}")
    
    return np.array(images), np.array(labels), label_to_idx

def build_cnn_model(num_classes):
    """Construire le modèle CNN"""
    logger.info(f"🔨 Construction du modèle pour {num_classes} classes...")
    
    model = keras.Sequential([
        # Bloc 1
        layers.Conv2D(32, (3, 3), activation='relu', padding='same', input_shape=(224, 224, 3)),
        layers.BatchNormalization(),
        layers.Conv2D(32, (3, 3), activation='relu', padding='same'),
        layers.BatchNormalization(),
        layers.MaxPooling2D((2, 2)),
        layers.Dropout(0.25),
        
        # Bloc 2
        layers.Conv2D(64, (3, 3), activation='relu', padding='same'),
        layers.BatchNormalization(),
        layers.Conv2D(64, (3, 3), activation='relu', padding='same'),
        layers.BatchNormalization(),
        layers.MaxPooling2D((2, 2)),
        layers.Dropout(0.25),
        
        # Bloc 3
        layers.Conv2D(128, (3, 3), activation='relu', padding='same'),
        layers.BatchNormalization(),
        layers.Conv2D(128, (3, 3), activation='relu', padding='same'),
        layers.BatchNormalization(),
        layers.MaxPooling2D((2, 2)),
        layers.Dropout(0.25),
        
        # Bloc 4
        layers.Conv2D(256, (3, 3), activation='relu', padding='same'),
        layers.BatchNormalization(),
        layers.Conv2D(256, (3, 3), activation='relu', padding='same'),
        layers.BatchNormalization(),
        layers.MaxPooling2D((2, 2)),
        layers.Dropout(0.25),
        
        # Global Average Pooling
        layers.GlobalAveragePooling2D(),
        
        # Couches Dense
        layers.Dense(512, activation='relu'),
        layers.BatchNormalization(),
        layers.Dropout(0.5),
        
        layers.Dense(256, activation='relu'),
        layers.BatchNormalization(),
        layers.Dropout(0.5),
        
        # Couche de sortie
        layers.Dense(num_classes, activation='softmax')
    ])
    
    model.compile(
        optimizer=keras.optimizers.Adam(learning_rate=0.001),
        loss='sparse_categorical_crossentropy',
        metrics=['accuracy']
    )
    
    logger.info(f"✅ Modèle construit avec {model.count_params():,} paramètres")
    return model

def train_model(model, X_train, y_train, X_val, y_val):
    """Entraîner le modèle"""
    logger.info("🚀 Entraînement du modèle...")
    
    # Data augmentation
    train_datagen = ImageDataGenerator(
        rotation_range=20,
        width_shift_range=0.2,
        height_shift_range=0.2,
        horizontal_flip=True,
        zoom_range=0.2,
    )
    
    # Callbacks
    early_stopping = keras.callbacks.EarlyStopping(
        monitor='val_loss',
        patience=10,
        restore_best_weights=True
    )
    
    reduce_lr = keras.callbacks.ReduceLROnPlateau(
        monitor='val_loss',
        factor=0.5,
        patience=5,
        min_lr=0.00001
    )
    
    # Entraîner
    history = model.fit(
        train_datagen.flow(X_train, y_train, batch_size=BATCH_SIZE),
        epochs=EPOCHS,
        steps_per_epoch=len(X_train) // BATCH_SIZE,
        validation_data=(X_val, y_val),
        callbacks=[early_stopping, reduce_lr],
        verbose=1
    )
    
    return history

def evaluate_model(model, X_test, y_test, categories):
    """Évaluer le modèle"""
    logger.info("\n📊 Évaluation du modèle...")
    
    loss, accuracy = model.evaluate(X_test, y_test, verbose=0)
    logger.info(f"✅ Accuracy: {accuracy*100:.2f}%")
    logger.info(f"✅ Loss: {loss:.4f}")
    
    # Prédictions
    predictions = model.predict(X_test, verbose=0)
    pred_labels = np.argmax(predictions, axis=1)
    
    # Résultats par classe
    logger.info("\n📈 Résultats par classe:")
    for idx, category in enumerate(categories):
        mask = y_test == idx
        if mask.sum() > 0:
            class_acc = (pred_labels[mask] == y_test[mask]).mean()
            logger.info(f"   {category}: {class_acc*100:.2f}% ({mask.sum()} images)")

def main():
    """Fonction principale"""
    logger.info("=" * 60)
    logger.info("🔄 RÉENTRAÎNEMENT DES MODÈLES ML")
    logger.info("=" * 60)
    
    if not DATA_DIR.exists():
        logger.error(f"❌ Répertoire de données manquant: {DATA_DIR}")
        return False
    
    # 1. Récupérer les catégories
    categories = get_food_categories()
    if not categories:
        logger.error("❌ Aucune catégorie trouvée dans les données")
        return False
    
    logger.info(f"\n✅ {len(categories)} catégories trouvées: {', '.join(categories[:5])}...")
    
    # 2. Charger les images
    X, y, label_to_idx = load_images_and_labels(categories)
    
    if len(X) < 10:
        logger.error("❌ Trop peu d'images pour l'entraînement (minimum 10)")
        return False
    
    # 3. Diviser les données
    logger.info(f"\n📊 Division des données (80/20)...")
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )
    X_train, X_val, y_train, y_val = train_test_split(
        X_train, y_train, test_size=0.2, random_state=42, stratify=y_train
    )
    
    logger.info(f"   Train: {len(X_train)}, Val: {len(X_val)}, Test: {len(X_test)}")
    
    # 4. Construire et entraîner
    model = build_cnn_model(len(categories))
    train_model(model, X_train, y_train, X_val, y_val)
    
    # 5. Évaluer
    evaluate_model(model, X_test, y_test, categories)
    
    # 6. Sauvegarder
    logger.info(f"\n💾 Sauvegarde du modèle...")
    model.save(str(OUTPUT_MODEL))
    logger.info(f"✅ Modèle sauvegardé: {OUTPUT_MODEL}")
    
    # Sauvegarder les labels
    with open(LABELS_FILE, 'w', encoding='utf-8') as f:
        json.dump(categories, f, ensure_ascii=False, indent=2)
    logger.info(f"✅ Labels sauvegardés: {LABELS_FILE}")
    
    logger.info("\n" + "=" * 60)
    logger.info("✅ RÉENTRAÎNEMENT TERMINÉ AVEC SUCCÈS!")
    logger.info("=" * 60)
    
    return True

if __name__ == "__main__":
    success = main()
    exit(0 if success else 1)
