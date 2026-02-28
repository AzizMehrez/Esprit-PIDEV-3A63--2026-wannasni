#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
WANNASNI - Analyseur d'image IA local avec CLIP
Usage:  python image_analyzer_clip.py <chemin_image>
Sortie: JSON sur stdout

Utilise CLIP (openai/clip-vit-base-patch32) en zero-shot pour classifier
une image parmi : electricite, plomberie, courses, transport, menage, compagnie
"""

import sys
import json
import os

# Force UTF-8 pour Windows
if os.name == 'nt':
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.detach())
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.detach())

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "Usage: python image_analyzer_clip.py <image_path>"}))
        sys.exit(1)

    image_path = sys.argv[1]
    if not os.path.exists(image_path):
        print(json.dumps({"success": False, "error": f"Fichier introuvable: {image_path}"}))
        sys.exit(1)

    try:
        from transformers import CLIPProcessor, CLIPModel
        from PIL import Image
        import torch

        # Charger CLIP (en cache après le 1er téléchargement)
        model_name = "openai/clip-vit-base-patch32"
        model = CLIPModel.from_pretrained(model_name)
        processor = CLIPProcessor.from_pretrained(model_name)
        model.eval()

        img = Image.open(image_path).convert("RGB")

        # Plusieurs prompts visuels par catégorie pour une détection robuste
        prompts_by_type = {
            "electricite": [
                "a photo of an electrical outlet on a wall",
                "a photo of electrical wires and cables",
                "a photo of a broken light bulb or lamp",
                "a photo of a light switch on a wall",
                "a photo of an electrical panel or fuse box",
                "a photo of a power socket or plug",
                "a photo of exposed copper wires",
                "a photo of an electrician working",
                "a photo of a ceiling light fixture",
                "a photo of a short circuit or sparks",
            ],
            "plomberie": [
                "a photo of a water leak from a pipe",
                "a photo of a dripping faucet or tap",
                "a photo of a clogged sink or drain",
                "a photo of a broken toilet",
                "a photo of water damage on a wall or floor",
                "a photo of a bathroom with plumbing issues",
                "a photo of a shower head or bathtub",
                "a photo of wet floor with water puddle",
            ],
            "courses": [
                "a photo of an empty refrigerator",
                "a photo of groceries and food items",
                "a photo of a kitchen with no food",
                "a photo of fruits and vegetables",
                "a photo of a supermarket or grocery store",
                "a photo of an empty pantry or cupboard",
                "a photo of milk, bread and eggs",
            ],
            "transport": [
                "a photo of a hospital building",
                "a photo of a wheelchair",
                "a photo of medicine pills and bottles",
                "a photo of a doctor or nurse",
                "a photo of an ambulance",
                "a photo of a medical clinic waiting room",
                "a photo of crutches or walking aid",
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
            ],
            "compagnie": [
                "a photo of an elderly person sitting alone",
                "a photo of a lonely old person at home",
                "a photo of an old man or woman looking sad",
                "a photo of an empty chair by a window",
                "a photo of two people having a conversation",
                "a photo of a senior citizen alone in a room",
            ],
        }

        # Construire la liste plate de tous les prompts
        all_prompts = []
        prompt_to_type = []
        for stype, prompts in prompts_by_type.items():
            for p in prompts:
                all_prompts.append(p)
                prompt_to_type.append(stype)

        # Calculer les similarités CLIP
        inputs = processor(text=all_prompts, images=img, return_tensors="pt", padding=True)
        with torch.no_grad():
            outputs = model(**inputs)
            logits = outputs.logits_per_image.squeeze(0)  # [N]
            probs = logits.softmax(dim=0).tolist()

        # Agréger : score max par type
        type_max_score = {t: 0.0 for t in prompts_by_type}
        type_sum_score = {t: 0.0 for t in prompts_by_type}
        type_count = {t: 0 for t in prompts_by_type}
        best_prompt_per_type = {t: "" for t in prompts_by_type}

        for i, prob in enumerate(probs):
            t = prompt_to_type[i]
            type_sum_score[t] += prob
            type_count[t] += 1
            if prob > type_max_score[t]:
                type_max_score[t] = prob
                best_prompt_per_type[t] = all_prompts[i]

        # Score final = 60% max + 40% moyenne (favorise les types avec plusieurs bons matchs)
        type_final_score = {}
        for t in prompts_by_type:
            avg = type_sum_score[t] / type_count[t] if type_count[t] > 0 else 0
            type_final_score[t] = 0.6 * type_max_score[t] + 0.4 * avg

        # Trier par score décroissant
        sorted_types = sorted(type_final_score.items(), key=lambda x: x[1], reverse=True)
        detected_type = sorted_types[0][0]
        best_score = sorted_types[0][1]

        # Descriptions françaises
        descriptions = {
            "plomberie": "Problème de plomberie détecté : fuite d'eau ou canalisation à réparer.",
            "electricite": "Problème électrique identifié : câblage, prise ou éclairage défectueux.",
            "courses": "Besoin d'aide pour les courses : réapprovisionnement alimentaire nécessaire.",
            "transport": "Transport médical requis : accompagnement vers un rendez-vous de santé.",
            "menage": "Service de ménage recommandé : nettoyage et rangement à effectuer.",
            "compagnie": "Besoin de compagnie : visite et présence bienveillante pour rompre la solitude."
        }

        # Urgence selon le type
        urgence = "normale"
        if detected_type in ("plomberie", "electricite") and best_score > 0.02:
            urgence = "urgente" if best_score > 0.04 else "moyenne"
        elif detected_type == "transport":
            urgence = "moyenne"

        # Normaliser le score pour l'affichage (0-1)
        total = sum(type_final_score.values())
        confidence = best_score / total if total > 0 else 0.5

        all_scores = {}
        for t, s in sorted_types:
            all_scores[t] = round(s / total if total > 0 else 0, 4)

        # Log debug
        import sys as _sys
        for t, s in sorted_types:
            _sys.stderr.write(f"  CLIP: {t:15s} = {s:.6f} (norm: {s/total:.2%}) best_prompt: {best_prompt_per_type[t]}\n")

        output = {
            "success": True,
            "type_service": detected_type,
            "description": descriptions.get(detected_type, "Service identifié par IA."),
            "niveau_urgence": urgence,
            "confidence": round(min(confidence + 0.05, 0.98), 4),
            "details": f"CLIP local: {detected_type} (score: {confidence:.0%})",
            "ai_provider": "local_clip",
            "all_scores": all_scores
        }

        print(json.dumps(output, ensure_ascii=False))

    except ImportError as e:
        print(json.dumps({
            "success": False,
            "error": f"Dépendances manquantes: {e}. Exécutez: pip install transformers torch pillow"
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
