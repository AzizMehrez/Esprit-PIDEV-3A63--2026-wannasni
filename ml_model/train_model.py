#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
WANNASNI - Entraînement du modèle ML de classification d'images
================================================================
Ce script entraîne un modèle SVM (Support Vector Machine) pour classifier
les images de problèmes domestiques en 6 catégories :
  electricite, plomberie, menage, courses, transport, compagnie

Pipeline ML :
  1. Extraction de features avec CLIP (openai/clip-vit-base-patch32)
  2. Création de données d'entraînement (texte + images réelles si disponibles)
  3. Entraînement d'un classifieur SVM avec noyau RBF
  4. Sauvegarde du modèle entraîné (model.pkl)

Usage:
  python ml_model/train_model.py
"""

import os
import sys
import json
import numpy as np
import joblib
from pathlib import Path

# Force UTF-8 pour Windows
if os.name == 'nt':
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.detach())


# ─── Configuration ───────────────────────────────────────────────────────────

MODEL_DIR = Path(__file__).parent
MODEL_PATH = MODEL_DIR / "model.pkl"
TRAINING_DATA_DIR = MODEL_DIR / "training_data"
CLIP_MODEL_NAME = "openai/clip-vit-base-patch32"

# Catégories de services
CATEGORIES = ["electricite", "plomberie", "menage", "courses", "transport", "compagnie"]

# Prompts d'entraînement textuels par catégorie (données synthétiques)
# CLIP encode ces descriptions textuelles en vecteurs comparables aux images
TRAINING_PROMPTS = {
    "electricite": [
        "a photo of an electrical outlet on a wall",
        "a photo of electrical wires and cables",
        "a photo of a broken light bulb or lamp",
        "a photo of a light switch on a wall",
        "a photo of an electrical panel or fuse box",
        "a photo of a power socket or plug",
        "a photo of exposed copper wires",
        "a photo of an electrician working on wiring",
        "a photo of a ceiling light fixture",
        "a photo of a short circuit with sparks",
        "a photo of a broken electrical socket",
        "a photo of a voltage meter or multimeter",
        "a photo of burned out electrical wires",
        "a photo of a chandelier or pendant light",
        "a photo of electrical tape on a wire",
    ],
    "plomberie": [
        "a photo of a water leak from a pipe",
        "a photo of a dripping faucet or tap",
        "a photo of a clogged sink or drain",
        "a photo of a broken toilet",
        "a photo of water damage on a wall or floor",
        "a photo of a bathroom with plumbing issues",
        "a photo of a shower head or bathtub",
        "a photo of a wet floor with water puddle",
        "a photo of rusty pipes under a sink",
        "a photo of a water heater or boiler",
        "a photo of a leaking pipe joint",
        "a photo of a plumber fixing a drain",
        "a photo of a flooded bathroom",
        "a photo of a broken water tap handle",
    ],
    "menage": [
        "a photo of a messy dirty room",
        "a photo of dust on furniture",
        "a photo of a cluttered living room",
        "a photo of garbage and trash bags",
        "a photo of a dirty kitchen or bathroom",
        "a photo of a vacuum cleaner or mop",
        "a photo of a disorganized house",
        "a photo of dirty dishes in a sink",
        "a photo of stains on a carpet or floor",
        "a photo of a dusty shelf with cobwebs",
        "a photo of a broom and dustpan",
        "a photo of clothes scattered on the floor",
        "a photo of an untidy bedroom with mess everywhere",
        "a photo of a dirty window needing cleaning",
        "a photo of a pile of laundry on the floor",
    ],
    "courses": [
        "a photo of an empty refrigerator",
        "a photo of groceries and food items",
        "a photo of a kitchen with no food",
        "a photo of fruits and vegetables",
        "a photo of a supermarket or grocery store",
        "a photo of an empty pantry or cupboard",
        "a photo of milk bread and eggs",
        "a photo of a shopping cart with groceries",
        "a photo of canned food on a shelf",
        "a photo of an empty kitchen counter",
        "a photo of a grocery list on paper",
        "a photo of food packages and bags",
        "a photo of an open fridge with few items",
        "a photo of a fruit basket on a table",
    ],
    "transport": [
        "a photo of a hospital building",
        "a photo of a wheelchair",
        "a photo of medicine pills and bottles",
        "a photo of a doctor or nurse",
        "a photo of an ambulance",
        "a photo of a medical clinic waiting room",
        "a photo of crutches or walking aids",
        "a photo of an elderly person needing transport",
        "a photo of a medical appointment card",
        "a photo of a pharmacy or drugstore",
        "a photo of a walker or mobility device",
        "a photo of a taxi or car for transport",
        "a photo of a blood pressure monitor",
        "a photo of medical equipment",
    ],
    "compagnie": [
        "a photo of an elderly person sitting alone",
        "a photo of a lonely old person at home",
        "a photo of an old man or woman looking sad",
        "a photo of an empty chair by a window",
        "a photo of two people having a conversation",
        "a photo of a senior citizen alone in a room",
        "a photo of an elderly couple holding hands",
        "a photo of a caregiver visiting an old person",
        "a photo of a quiet empty living room",
        "a photo of a person reading alone",
        "a photo of a tea set for two on a table",
        "a photo of board games or cards on a table",
    ],
}


def load_clip_model():
    """Charge le modèle CLIP et le processeur"""
    from transformers import CLIPProcessor, CLIPModel
    print("[INFO] Chargement du modèle CLIP...")
    model = CLIPModel.from_pretrained(CLIP_MODEL_NAME)
    processor = CLIPProcessor.from_pretrained(CLIP_MODEL_NAME)
    model.eval()
    print("[INFO] Modèle CLIP chargé avec succès.")
    return model, processor


def extract_text_features(model, processor, texts):
    """Extrait les features CLIP projetées à partir de textes"""
    import torch
    inputs = processor(text=texts, return_tensors="pt", padding=True, truncation=True)
    # Extraire seulement les inputs texte
    text_inputs = {k: v for k, v in inputs.items() if k in ('input_ids', 'attention_mask')}
    with torch.no_grad():
        text_outputs = model.text_model(**text_inputs)
        # pooler_output = sortie du pooling (avant projection)
        pooled = text_outputs.pooler_output if hasattr(text_outputs, 'pooler_output') else text_outputs[1]
        # Appliquer la projection CLIP pour être dans le MÊME espace que les images
        text_features = model.text_projection(pooled)
    # Normaliser L2
    text_features = text_features / text_features.norm(dim=-1, keepdim=True)
    return text_features.numpy()


def extract_image_features(model, processor, image_path):
    """Extrait les features CLIP projetées à partir d'une image"""
    import torch
    from PIL import Image
    img = Image.open(image_path).convert("RGB")
    inputs = processor(images=img, return_tensors="pt")
    with torch.no_grad():
        vision_outputs = model.vision_model(**inputs)
        # pooler_output = sortie du pooling (avant projection)
        pooled = vision_outputs.pooler_output if hasattr(vision_outputs, 'pooler_output') else vision_outputs[1]
        # Appliquer la projection CLIP pour être dans le MÊME espace que les textes
        image_features = model.visual_projection(pooled)
    # Normaliser L2
    image_features = image_features / image_features.norm(dim=-1, keepdim=True)
    return image_features.numpy()


def collect_training_data(model, processor):
    """
    Collecte les données d'entraînement :
      1. Données synthétiques (features textuelles CLIP)
      2. Images réelles du dossier training_data/ (si disponibles)
    """
    X_train = []  # Features (vecteurs 512D)
    y_train = []  # Labels (catégories)

    n_text = 0
    n_images = 0

    # ─── 1. Données synthétiques depuis les prompts textuels ─────────────
    print("\n[ÉTAPE 1] Génération des features textuelles synthétiques...")
    for category, prompts in TRAINING_PROMPTS.items():
        features = extract_text_features(model, processor, prompts)
        for feat in features:
            X_train.append(feat)
            y_train.append(category)
            n_text += 1
        print(f"  ✓ {category:15s} : {len(prompts)} prompts textuels")

    # ─── 2. Images réelles du dossier training_data/ ─────────────────────
    print("\n[ÉTAPE 2] Recherche d'images d'entraînement réelles...")
    for category in CATEGORIES:
        category_dir = TRAINING_DATA_DIR / category
        if not category_dir.exists():
            category_dir.mkdir(parents=True, exist_ok=True)
            continue

        images = list(category_dir.glob("*.jpg")) + \
                 list(category_dir.glob("*.jpeg")) + \
                 list(category_dir.glob("*.png")) + \
                 list(category_dir.glob("*.webp"))

        if images:
            print(f"  ✓ {category:15s} : {len(images)} images trouvées")
            for img_path in images:
                try:
                    features = extract_image_features(model, processor, str(img_path))
                    X_train.append(features.squeeze())
                    y_train.append(category)
                    n_images += 1
                except Exception as e:
                    print(f"  ✗ Erreur avec {img_path.name}: {e}")
        else:
            print(f"  - {category:15s} : aucune image (dossier vide)")

    X_train = np.array(X_train)
    y_train = np.array(y_train)

    print(f"\n[RÉSUMÉ] Données d'entraînement :")
    print(f"  • Échantillons textuels  : {n_text}")
    print(f"  • Images réelles         : {n_images}")
    print(f"  • Total                  : {len(y_train)}")
    print(f"  • Dimension des features : {X_train.shape[1]}")

    return X_train, y_train


def train_svm(X_train, y_train):
    """Entraîne un classifieur SVM avec noyau RBF"""
    from sklearn.svm import SVC
    from sklearn.preprocessing import StandardScaler
    from sklearn.pipeline import Pipeline
    from sklearn.model_selection import cross_val_score

    print("\n[ÉTAPE 3] Entraînement du modèle SVM...")

    # Pipeline : Normalisation → SVM (noyau RBF)
    pipeline = Pipeline([
        ('scaler', StandardScaler()),
        ('svm', SVC(
            kernel='rbf',
            C=10.0,
            gamma='scale',
            probability=True,  # Pour obtenir les probabilités de confiance
            class_weight='balanced',  # Équilibrer les classes
            random_state=42
        ))
    ])

    # Validation croisée (5-fold)
    print("  → Validation croisée 5-fold...")
    scores = cross_val_score(pipeline, X_train, y_train, cv=5, scoring='accuracy')
    print(f"  → Accuracy moyenne : {scores.mean():.2%} (±{scores.std():.2%})")

    # Entraînement final sur toutes les données
    print("  → Entraînement final sur l'ensemble complet...")
    pipeline.fit(X_train, y_train)

    # Rapport de classification
    from sklearn.metrics import classification_report
    y_pred = pipeline.predict(X_train)
    print("\n[RAPPORT] Classification sur les données d'entraînement :")
    print(classification_report(y_train, y_pred, target_names=CATEGORIES))

    return pipeline


def save_model(pipeline):
    """Sauvegarde le modèle entraîné avec joblib"""
    model_data = {
        'pipeline': pipeline,
        'categories': CATEGORIES,
        'clip_model_name': CLIP_MODEL_NAME,
        'version': '1.0',
        'training_prompts_count': sum(len(p) for p in TRAINING_PROMPTS.values()),
    }
    joblib.dump(model_data, MODEL_PATH)
    size_mb = MODEL_PATH.stat().st_size / (1024 * 1024)
    print(f"\n[SAUVEGARDE] Modèle sauvegardé : {MODEL_PATH}")
    print(f"  → Taille : {size_mb:.2f} MB")


def main():
    print("=" * 60)
    print("  WANNASNI - Entraînement du modèle ML")
    print("  Classification d'images de services")
    print("=" * 60)

    # 1. Charger CLIP
    model, processor = load_clip_model()

    # 2. Collecter les données d'entraînement
    X_train, y_train = collect_training_data(model, processor)

    # 3. Entraîner le SVM
    pipeline = train_svm(X_train, y_train)

    # 4. Sauvegarder le modèle
    save_model(pipeline)

    print("\n" + "=" * 60)
    print("  ✓ Entraînement terminé avec succès !")
    print(f"  ✓ Modèle prêt : {MODEL_PATH}")
    print("  ✓ Utilisez predict.py pour classifier une image")
    print("=" * 60)


if __name__ == "__main__":
    main()
