#!/usr/bin/env python3
"""
Réentraînement rapide du modèle - Fine-tuning du modèle existant
"""

import os
import json
import numpy as np
import logging
from pathlib import Path
from collections import defaultdict

import tensorflow as tf
from tensorflow import keras
from tensorflow.keras.preprocessing.image import load_img, img_to_array
from sklearn.model_selection import train_test_split

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

SCRIPT_DIR = Path(__file__).parent
DATA_DIR = SCRIPT_DIR.parent / "data" / "raw"
MODEL_DIR = SCRIPT_DIR / "model"
OUTPUT_MODEL = MODEL_DIR / "food_classifier.h5"
LABELS_FILE = MODEL_DIR / "labels.json"

def load_images_and_labels(categories):
    """Charger les images rapidement"""
    images = []
    labels = []
    label_to_idx = {label: idx for idx, label in enumerate(categories)}
    
    logger.info(f"📁 Chargement des images depuis {DATA_DIR}...")
    total = defaultdict(int)
    
    for category in categories:
        category_dir = DATA_DIR / category
        if not category_dir.exists():
            continue
            
        img_files = list(category_dir.glob("*.jpg")) + list(category_dir.glob("*.png"))
        logger.info(f"  {category}: {len(img_files)}")
        
        for img_file in img_files:
            try:
                img = load_img(str(img_file), target_size=(224, 224))
                img_array = img_to_array(img) / 255.0
                images.append(img_array)
                labels.append(label_to_idx[category])
                total[category] += 1
            except Exception as e:
                logger.warning(f"⚠️  {img_file}: {e}")
    
    logger.info(f"\n✅ Total: {sum(total.values())} images")
    return np.array(images), np.array(labels)

def main():
    logger.info("\n" + "="*60)
    logger.info("🚀 RÉENTRAÎNEMENT RAPIDE DU MODÈLE")
    logger.info("="*60 + "\n")
    
    # Charger les catégories depuis les répertoires
    categories = sorted([d.name for d in DATA_DIR.iterdir() if d.is_dir()])
    logger.info(f"✅ Catégories: {', '.join(categories[:5])}... ({len(categories)} total)")
    
    # Charger les images
    X, y = load_images_and_labels(categories)
    
    if len(X) < 10:
        logger.error("❌ Pas assez d'images")
        return False
    
    # Diviser
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=42, stratify=y
    )
    
    logger.info(f"\n📊 Train: {len(X_train)}, Test: {len(X_test)}")
    
    # Charger le modèle existant
    logger.info(f"\n📦 Chargement du modèle existant...")
    try:
        model = keras.models.load_model(str(OUTPUT_MODEL))
        logger.info("✅ Modèle chargé")
    except Exception as e:
        logger.error(f"❌ Erreur: {e}")
        return False
    
    # Fine-tuning rapide
    logger.info("\n⚡ Fine-tuning (10 epochs)...\n")
    
    model.compile(
        optimizer=keras.optimizers.Adam(learning_rate=0.0001),
        loss='sparse_categorical_crossentropy',
        metrics=['accuracy']
    )
    
    model.fit(
        X_train, y_train,
        epochs=10,
        batch_size=32,
        validation_data=(X_test, y_test),
        verbose=1
    )
    
    # Évaluer
    loss, acc = model.evaluate(X_test, y_test, verbose=0)
    logger.info(f"\n✅ Accuracy: {acc*100:.1f}%, Loss: {loss:.3f}")
    
    # Sauvegarder
    model.save(str(OUTPUT_MODEL))
    logger.info(f"💾 Modèle sauvegardé")
    
    with open(LABELS_FILE, 'w') as f:
        json.dump(categories, f, indent=2)
    
    logger.info("\n" + "="*60)
    logger.info("✅ RÉENTRAÎNEMENT RÉUSSI!")
    logger.info("="*60)
    return True

if __name__ == "__main__":
    success = main()
    exit(0 if success else 1)
