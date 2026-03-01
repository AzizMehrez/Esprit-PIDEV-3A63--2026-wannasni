#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
WANNASNI - Prédiction ML pour classification d'images
=====================================================
Charge le modèle SVM entraîné et classifie une image en
une des 6 catégories de services.

Pipeline de prédiction :
  1. Chargement du modèle SVM pré-entraîné (model.pkl)
  2. Extraction des features CLIP de l'image
  3. Prédiction avec le SVM → catégorie + confiance
  4. Sortie JSON sur stdout

Usage:
  python ml_model/predict.py <chemin_image>
"""

import os
import sys
import json
import warnings

# ─── Supprimer TOUTE sortie parasite AVANT d'importer transformers/torch ─────
# Désactiver les barres de progression tqdm (poids CLIP)
os.environ["TQDM_DISABLE"] = "1"
os.environ["TRANSFORMERS_NO_ADVISORY_WARNINGS"] = "1"
os.environ["TOKENIZERS_PARALLELISM"] = "false"
os.environ["HF_HUB_DISABLE_PROGRESS_BARS"] = "1"
# Supprimer les warnings Python
warnings.filterwarnings("ignore")

import numpy as np
import joblib
from pathlib import Path

# Force UTF-8 pour Windows (stdout uniquement)
if os.name == 'nt':
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())
    # Rediriger stderr vers devnull pour éviter toute pollution
    sys.stderr = open(os.devnull, 'w', encoding='utf-8')


# ─── Configuration ───────────────────────────────────────────────────────────

MODEL_DIR = Path(__file__).parent
MODEL_PATH = MODEL_DIR / "model.pkl"
CLIP_MODEL_NAME = "openai/clip-vit-base-patch32"

# Descriptions françaises pour chaque catégorie
DESCRIPTIONS = {
    "electricite": "Problème électrique identifié : câblage, prise ou éclairage défectueux.",
    "plomberie": "Problème de plomberie détecté : fuite d'eau ou canalisation à réparer.",
    "menage": "Service de ménage recommandé : nettoyage et rangement à effectuer.",
    "courses": "Besoin d'aide pour les courses : réapprovisionnement alimentaire nécessaire.",
    "transport": "Transport médical requis : accompagnement vers un rendez-vous de santé.",
    "compagnie": "Besoin de compagnie : visite et présence bienveillante pour rompre la solitude.",
}

# Mapping urgence par catégorie
URGENCE_MAP = {
    "electricite": "urgente",
    "plomberie": "urgente",
    "transport": "moyenne",
    "menage": "normale",
    "courses": "normale",
    "compagnie": "normale",
}


def load_model():
    """Charge le modèle SVM pré-entraîné"""
    if not MODEL_PATH.exists():
        return None
    return joblib.load(MODEL_PATH)


def extract_image_features(image_path):
    """Extrait les features CLIP projetées d'une image"""
    import torch
    import logging
    # Supprimer les logs transformers
    logging.getLogger("transformers").setLevel(logging.ERROR)
    logging.getLogger("transformers.modeling_utils").setLevel(logging.ERROR)
    from transformers import CLIPProcessor, CLIPModel
    from PIL import Image

    model = CLIPModel.from_pretrained(CLIP_MODEL_NAME, local_files_only=False)
    processor = CLIPProcessor.from_pretrained(CLIP_MODEL_NAME, local_files_only=False)
    model.eval()

    img = Image.open(image_path).convert("RGB")
    inputs = processor(images=img, return_tensors="pt")

    with torch.no_grad():
        vision_outputs = model.vision_model(**inputs)
        # pooler_output = sortie du pooling (avant projection)
        pooled = vision_outputs.pooler_output if hasattr(vision_outputs, 'pooler_output') else vision_outputs[1]
        # Appliquer la projection CLIP → même espace que les textes d'entraînement
        image_features = model.visual_projection(pooled)

    # Normaliser L2 (même normalisation qu'à l'entraînement)
    image_features = image_features / image_features.norm(dim=-1, keepdim=True)
    return image_features.numpy()


def predict(image_path):
    """
    Prédit la catégorie de service à partir d'une image.
    Retourne un dictionnaire avec le résultat.
    """
    # 1. Charger le modèle
    model_data = load_model()
    if model_data is None:
        return {
            "success": False,
            "error": "Modèle non trouvé. Exécutez d'abord : python ml_model/train_model.py"
        }

    pipeline = model_data['pipeline']
    categories = model_data['categories']

    # 2. Extraire les features de l'image
    sys.stderr.write("[ML] Extraction des features CLIP...\n")
    features = extract_image_features(image_path)

    # 3. Prédiction SVM
    predicted_category = pipeline.predict(features)[0]
    probabilities = pipeline.predict_proba(features)[0]

    # 4. Construire les scores par catégorie
    class_labels = pipeline.classes_
    all_scores = {}
    for i, label in enumerate(class_labels):
        all_scores[label] = round(float(probabilities[i]), 4)

    # Trier par score décroissant
    all_scores = dict(sorted(all_scores.items(), key=lambda x: x[1], reverse=True))

    confidence = float(max(probabilities))

    # Ajuster l'urgence selon la confiance
    urgence = URGENCE_MAP.get(predicted_category, "normale")
    if confidence < 0.3:
        urgence = "normale"
    elif predicted_category in ("electricite", "plomberie") and confidence > 0.5:
        urgence = "urgente"

    # Log debug sur stderr
    sys.stderr.write(f"[ML] Prédiction : {predicted_category} (confiance: {confidence:.2%})\n")
    for cat, score in all_scores.items():
        sys.stderr.write(f"  SVM: {cat:15s} = {score:.4f} ({score:.2%})\n")

    return {
        "success": True,
        "type_service": predicted_category,
        "description": DESCRIPTIONS.get(predicted_category, "Service identifié par IA."),
        "niveau_urgence": urgence,
        "confidence": round(min(confidence, 0.98), 4),
        "details": f"ML SVM + CLIP : {predicted_category} (confiance: {confidence:.0%})",
        "ai_provider": "ml_model_svm",
        "all_scores": all_scores,
        "model_version": "1.0"
    }


def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: python ml_model/predict.py <chemin_image>"
        }))
        sys.exit(1)

    image_path = sys.argv[1]

    if not os.path.exists(image_path):
        print(json.dumps({
            "success": False,
            "error": f"Fichier introuvable: {image_path}"
        }))
        sys.exit(1)

    try:
        result = predict(image_path)
        print(json.dumps(result, ensure_ascii=False))
        if not result["success"]:
            sys.exit(1)
    except ImportError as e:
        print(json.dumps({
            "success": False,
            "error": f"Dépendances manquantes: {e}. Exécutez: pip install transformers torch pillow scikit-learn joblib"
        }))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))
        sys.exit(1)


if __name__ == "__main__":
    main()
