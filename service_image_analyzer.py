#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Local AI Image Analyzer (CLIP Zero-Shot) for WANNASNI

Runs a small FastAPI server that performs zero-shot image classification
using CLIP (openai/clip-vit-base-patch32) to map an input image to one of:
- electricite, plomberie, courses, transport, menage, compagnie

Start:
    pip install fastapi uvicorn transformers pillow torch --upgrade
    python service_image_analyzer.py

Endpoint:
    POST /analyze  (multipart/form-data: image=<file>)
Response:
    {
      "success": true,
      "type_service": "plomberie",
      "description": "...",
      "niveau_urgence": "moyenne",
      "confidence": 0.87,
      "details": "CLIP zero-shot (local)"
    }
"""

import io
import os
from typing import Dict, List

from fastapi import FastAPI, File, UploadFile
from fastapi.responses import JSONResponse
from PIL import Image

try:
    from transformers import CLIPProcessor, CLIPModel
    import torch
except Exception as e:
    raise RuntimeError("Missing dependencies. Install: pip install fastapi uvicorn transformers pillow torch")


app = FastAPI(title="WANNASNI Service Image Analyzer", version="1.0")


# Load CLIP model once at startup (CPU)
MODEL_NAME = "openai/clip-vit-base-patch32"
clip_model = CLIPModel.from_pretrained(MODEL_NAME)
clip_processor = CLIPProcessor.from_pretrained(MODEL_NAME)
clip_model.eval()


# Class templates for robust zero-shot prompts
CLASS_TEMPLATES: Dict[str, List[str]] = {
    "electricite": [
        "an electrical problem at home",
        "a broken electrical outlet",
        "exposed electrical wires",
        "a damaged light bulb or lamp",
        "an electrical switch problem"
    ],
    "plomberie": [
        "a water leak in a house",
        "a broken faucet or tap",
        "a leaking pipe",
        "a clogged sink or drain",
        "a toilet problem with water"
    ],
    "courses": [
        "an empty fridge with little food",
        "groceries and food shopping",
        "a kitchen with food supplies missing",
        "supermarket grocery items",
        "fridge and pantry needing restock"
    ],
    "transport": [
        "elderly person going to hospital",
        "medical transport for a patient",
        "wheelchair and medical assistance",
        "clinic visit for health appointment",
        "ambulance or medical help"
    ],
    "menage": [
        "a messy room needing cleaning",
        "dirty floor with dust",
        "house cleaning and tidying",
        "vacuum and mop for cleaning",
        "cluttered home interior"
    ],
    "compagnie": [
        "elderly person sitting alone",
        "lonely senior needing company",
        "companion visit for conversation",
        "social support for elderly",
        "a person alone at home"
    ],
}


def classify_image(img: Image.Image) -> Dict:
    # Build prompt list and map indices to types
    prompts: List[str] = []
    idx_to_type: List[str] = []
    for t, tmpl_list in CLASS_TEMPLATES.items():
        for tmpl in tmpl_list:
            prompts.append(tmpl)
            idx_to_type.append(t)

    inputs = clip_processor(text=prompts, images=img, return_tensors="pt")
    with torch.no_grad():
        outputs = clip_model(**inputs)
        logits_per_image = outputs.logits_per_image  # [1, N]
        probs = logits_per_image.softmax(dim=1).squeeze(0).tolist()

    # Aggregate per type (max probability among its templates)
    type_scores: Dict[str, float] = {t: 0.0 for t in CLASS_TEMPLATES.keys()}
    for i, p in enumerate(probs):
        t = idx_to_type[i]
        if p > type_scores[t]:
            type_scores[t] = p

    # Pick best type
    best_type = max(type_scores.items(), key=lambda kv: kv[1])[0]
    confidence = float(type_scores[best_type])

    # French description + urgence mapping
    if best_type == "plomberie":
        description = "Problème de plomberie détecté : fuite d'eau ou canalisation à réparer."
        urgence = "moyenne" if confidence < 0.75 else "urgente"
    elif best_type == "electricite":
        description = "Problème électrique identifié : câblage, prise ou éclairage défectueux."
        urgence = "moyenne" if confidence < 0.75 else "urgente"
    elif best_type == "courses":
        description = "Besoin d'aide pour les courses : réapprovisionnement alimentaire nécessaire."
        urgence = "normale"
    elif best_type == "transport":
        description = "Transport médical requis : accompagnement vers un rendez-vous de santé."
        urgence = "moyenne"
    elif best_type == "menage":
        description = "Service de ménage recommandé : nettoyage et rangement à effectuer."
        urgence = "normale"
    else:  # compagnie
        description = "Besoin de compagnie : visite et présence bienveillante pour rompre la solitude."
        urgence = "normale"

    return {
        "success": True,
        "type_service": best_type,
        "description": description,
        "niveau_urgence": urgence,
        "confidence": round(confidence, 4),
        "details": "CLIP zero-shot (local)"
    }


@app.post("/analyze")
async def analyze(image: UploadFile = File(...)):
    try:
        data = await image.read()
        img = Image.open(io.BytesIO(data)).convert("RGB")
        result = classify_image(img)
        return JSONResponse(result)
    except Exception as e:
        return JSONResponse({
            "success": False,
            "error": str(e)
        }, status_code=500)


if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("AI_LOCAL_PORT", "5001"))
    uvicorn.run(app, host="127.0.0.1", port=port)
