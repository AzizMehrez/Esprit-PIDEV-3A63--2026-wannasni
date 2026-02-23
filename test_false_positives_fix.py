#!/usr/bin/env python3
"""
Test des améliorations de détection - Éviter les faux positifs
Teste le cas critique : spaghetti rouge simple détecté comme spaghetti bolognaise
"""

import sys
import os
import logging

# Suppress TensorFlow warnings
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
logging.getLogger('tensorflow').setLevel(logging.ERROR)

sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'python'))

from ml.full_nutrition_analyzer import FullNutritionAnalyzer

print()
print("╔" + "═"*68 + "╗")
print("║" + " "*15 + "TEST AMÉLIORATION - CORRECTION FAUX POSITIFS" + " "*12 + "║")
print("╚" + "═"*68 + "╝")
print()

analyzer = FullNutritionAnalyzer()
print("[OK] Analyzer chargé\n")

# Test images
test_cases = [
    ("data/raw/plats_pates/spaghetti/spaghetti_simple.jpg" if os.path.exists("data/raw/plats_pates/spaghetti/spaghetti_simple.jpg") else "python/data/raw/plats_pates/spaghetti/spaghetti_simple.jpg",
     "Spaghetti SIMPLE (pâtes rouges seules, SANS sauce)")
]

# If the specific test file doesn't exist, use any available spaghetti/lasagne
import glob
available = glob.glob("python/data/raw/plats_pates/*/*.jpg") + glob.glob("data/raw/plats_pates/*/*.jpg")
if available:
    test_cases = [(available[0], f"Image test disponible: {os.path.basename(available[0])}")]

print("="*70)
print("TEST: Détection de plats pâtes/simple vs plat complet")
print("="*70)
print()

for img_path, description in test_cases:
    if not os.path.exists(img_path):
        print(f"[SKIP] Image non trouvée: {img_path}")
        continue
    
    print(f"TEST: {description}")
    print(f"Fichier: {img_path}")
    print("-"*70)
    
    try:
        result = analyzer.detect_only(img_path)
        
        if result.get('detected'):
            foods = result.get('foods', [])
            print(f"Nombre d'aliments détectés: {len(foods)}\n")
            
            for i, food in enumerate(foods):
                nom = food.get('nom', '?')
                conf = food.get('confiance', 0)
                source = food.get('source', '?')
                print(f"  [{i+1}] {nom:30} conf={conf:.3f}  [{source}]")
            
            print()
            # Criteria for success
            primary = foods[0] if foods else None
            if primary:
                print(f"Aliment détecté: {primary.get('nom')}")
                print(f"Confiance: {primary.get('confiance'):.3f}")
                print(f"Source: {primary.get('source')}")
            
            # Check for false positives
            if len(foods) > 1:
                print(f"\n[ALERTE] {len(foods)-1} aliment(s) secondaire(s) détecté(s)")
                for food in foods[1:]:
                    print(f"  - {food.get('nom')}: {food.get('confiance'):.3f}")
            else:
                print("\n[SUCCÈS] Pas de faux positifs secondaires!")
        else:
            print("[INFO] Aucun aliment détecté")
            print(f"Raison: {result.get('message', 'Inconnue')}")
    
    except Exception as e:
        print(f"[ERREUR] {str(e)[:150]}")
    
    print()

print("="*70)
print("Test terminé")
print("="*70)
