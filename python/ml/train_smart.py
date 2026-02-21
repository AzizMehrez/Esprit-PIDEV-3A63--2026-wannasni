#!/usr/bin/env python3
"""
Réentraînement INTELLIGENT du modèle
Charge SEULEMENT les catégories qui ont des images
"""

import os
import json
import numpy as np
import logging
from pathlib import Path
from collections import defaultdict

import tensorflow as tf
from tensorflow import keras
from tensorflow.keras.preprocessing.image import load_img, img_to_array, ImageDataGenerator

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S'
)
logger = logging.getLogger(__name__)

SCRIPT_DIR = Path(__file__).parent
DATA_DIR = SCRIPT_DIR.parent / "data" / "raw"
MODEL_DIR = SCRIPT_DIR / "model"
MODEL_FILE = MODEL_DIR / "food_classifier.h5"
LABELS_FILE = MODEL_DIR / "labels.json"

IMG_SIZE = (224, 224)
BATCH_SIZE = 16
EPOCHS = 15

def get_categories_with_images():
    """Trouver SEULEMENT les catégories qui ont des images"""
    categories = {}
    
    if not DATA_DIR.exists():
        logger.error(f"❌ Répertoire vide: {DATA_DIR}")
        return categories
    
    for folder in sorted(DATA_DIR.iterdir()):
        if not folder.is_dir():
            continue
            
        images = list(folder.glob("*.jpg")) + list(folder.glob("*.png"))
        
        if len(images) > 0:  # SEULEMENT si images présentes
            categories[folder.name] = len(images)
    
    return categories

def load_all_images(categories_dict):
    """Charger toutes les images et leurs labels"""
    X = []
    y = []
    category_names = sorted(categories_dict.keys())
    label_to_idx = {name: idx for idx, name in enumerate(category_names)}
    
    total_loaded = 0
    
    logger.info("\n" + "="*70)
    logger.info("📁 CHARGEMENT DES IMAGES")
    logger.info("="*70 + "\n")
    
    for category in category_names:
        category_path = DATA_DIR / category
        images_in_cat = list(category_path.glob("*.jpg")) + list(category_path.glob("*.png"))
        
        loaded = 0
        failed = 0
        
        for img_path in images_in_cat:
            try:
                # Charger l'image
                img = load_img(str(img_path), target_size=IMG_SIZE)
                img_array = img_to_array(img) / 255.0  # Normaliser
                
                X.append(img_array)
                y.append(label_to_idx[category])
                loaded += 1
                total_loaded += 1
                
            except Exception as e:
                failed += 1
                logger.debug(f"   Erreur {img_path.name}: {e}")
        
        status = f"✅" if failed == 0 else f"⚠️  ({failed} erreurs)"
        logger.info(f"  {category:20} → {loaded:3} images {status}")
    
    logger.info(f"\n✅ Total chargé: {total_loaded} images sur {sum(categories_dict.values())}")
    
    return np.array(X), np.array(y), category_names

def build_model(num_classes):
    """Construire un modèle CNN adapté aux données"""
    logger.info(f"\n🔨 Construction du modèle pour {num_classes} classes...\n")
    
    model = keras.Sequential([
        # Bloc 1: Extraction de features de base
        keras.layers.Conv2D(32, (3, 3), activation='relu', padding='same', input_shape=(224, 224, 3)),
        keras.layers.BatchNormalization(),
        keras.layers.Conv2D(32, (3, 3), activation='relu', padding='same'),
        keras.layers.BatchNormalization(),
        keras.layers.MaxPooling2D((2, 2)),
        keras.layers.Dropout(0.25),
        
        # Bloc 2: Features moyennes
        keras.layers.Conv2D(64, (3, 3), activation='relu', padding='same'),
        keras.layers.BatchNormalization(),
        keras.layers.Conv2D(64, (3, 3), activation='relu', padding='same'),
        keras.layers.BatchNormalization(),
        keras.layers.MaxPooling2D((2, 2)),
        keras.layers.Dropout(0.25),
        
        # Bloc 3: Features complexes
        keras.layers.Conv2D(128, (3, 3), activation='relu', padding='same'),
        keras.layers.BatchNormalization(),
        keras.layers.Conv2D(128, (3, 3), activation='relu', padding='same'),
        keras.layers.BatchNormalization(),
        keras.layers.MaxPooling2D((2, 2)),
        keras.layers.Dropout(0.25),
        
        # Pooling global
        keras.layers.GlobalAveragePooling2D(),
        
        # Classification
        keras.layers.Dense(256, activation='relu'),
        keras.layers.BatchNormalization(),
        keras.layers.Dropout(0.5),
        keras.layers.Dense(num_classes, activation='softmax')
    ])
    
    model.compile(
        optimizer=keras.optimizers.Adam(learning_rate=0.001),
        loss='sparse_categorical_crossentropy',
        metrics=['accuracy']
    )
    
    logger.info(f"✅ Modèle créé: {model.count_params():,} paramètres\n")
    return model

def main():
    logger.info("\n" + "█" * 70)
    logger.info("█" + " " * 68 + "█")
    logger.info("█" + "  🚀 RÉENTRAÎNEMENT INTELLIGENT DU MODÈLE ML  ".center(68) + "█")
    logger.info("█" + " " * 68 + "█")
    logger.info("█" * 70)
    
    # 1. Découvrir les catégories
    categories = get_categories_with_images()
    
    if not categories:
        logger.error("❌ Aucune image trouvée!")
        return False
    
    logger.info(f"\n✅ {len(categories)} catégories avec images détectées:")
    for cat, count in sorted(categories.items(), key=lambda x: -x[1]):
        logger.info(f"   • {cat:25} → {count:3} images")
    
    # 2. Charger les images
    X, y, category_names = load_all_images(categories)
    
    if len(X) < 10:
        logger.error(f"❌ Trop peu d'images ({len(X)}), minimum 10 requis")
        return False
    
    # 3. Diviser train/test
    logger.info(f"\n📊 Division des données:")
    split_idx = int(len(X) * 0.8)
    X_train, X_test = X[:split_idx], X[split_idx:]
    y_train, y_test = y[:split_idx], y[split_idx:]
    
    logger.info(f"   Entraînement: {len(X_train)} images")
    logger.info(f"   Test:         {len(X_test)} images")
    
    # 4. Entraîner
    logger.info(f"\n⚡ ENTRAÎNEMENT LANCÉ ({EPOCHS} epochs)...\n")
    
    model = build_model(len(category_names))
    
    # Data augmentation légère
    datagen = ImageDataGenerator(
        rotation_range=15,
        width_shift_range=0.1,
        height_shift_range=0.1,
        zoom_range=0.1,
        horizontal_flip=True
    )
    
    history = model.fit(
        datagen.flow(X_train, y_train, batch_size=BATCH_SIZE),
        epochs=EPOCHS,
        steps_per_epoch=len(X_train) // BATCH_SIZE,
        validation_data=(X_test, y_test),
        verbose=1
    )
    
    # 5. Résultats
    logger.info("\n" + "="*70)
    logger.info("📊 RÉSULTATS")
    logger.info("="*70)
    
    loss, accuracy = model.evaluate(X_test, y_test, verbose=0)
    logger.info(f"✅ Accuracy: {accuracy*100:.1f}%")
    logger.info(f"✅ Loss:     {loss:.4f}")
    
    # Résultats par classe
    predictions = model.predict(X_test, verbose=0)
    pred_labels = np.argmax(predictions, axis=1)
    
    logger.info(f"\n📈 Précision par catégorie:")
    for idx, cat in enumerate(category_names):
        mask = y_test == idx
        if mask.sum() > 0:
            cat_acc = (pred_labels[mask] == y_test[mask]).mean()
            logger.info(f"   {cat:25} → {cat_acc*100:5.1f}% ({mask.sum()} test)")
    
    # 6. Sauvegarder
    logger.info(f"\n💾 SAUVEGARDE...")
    model.save(str(MODEL_FILE))
    logger.info(f"   ✅ Modèle → {MODEL_FILE}")
    
    with open(LABELS_FILE, 'w', encoding='utf-8') as f:
        json.dump(category_names, f, indent=2, ensure_ascii=False)
    logger.info(f"   ✅ Labels → {LABELS_FILE}")
    
    logger.info("\n" + "█" * 70)
    logger.info("█" + "  ✅ RÉENTRAÎNEMENT RÉUSSI!  ".center(68) + "█")
    logger.info("█" * 70 + "\n")
    
    return True

if __name__ == "__main__":
    try:
        success = main()
        exit(0 if success else 1)
    except KeyboardInterrupt:
        logger.info("\n\n⚠️  Arrêt utilisateur")
        exit(1)
    except Exception as e:
        logger.error(f"\n❌ Erreur fatale: {e}", exc_info=True)
        exit(1)
