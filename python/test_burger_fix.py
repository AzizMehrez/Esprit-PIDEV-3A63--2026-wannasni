#!/usr/bin/env python3
"""Test burger detection to verify false positives are eliminated."""

from ml.full_nutrition_analyzer import FullNutritionAnalyzer

print("\n" + "="*70)
print("TEST BURGER DETECTION (False Positive Fix)")
print("="*70)

analyzer = FullNutritionAnalyzer()
results = analyzer.detect_only('data/raw/fast_food/burger/burger.jpg')

print("\nRESULTATS:")
if isinstance(results, list) and isinstance(results[0], tuple):
    # Format: list of tuples [(nom, confiance), ...]
    for i, (nom, conf) in enumerate(results, 1):
        conf_val = float(conf) if isinstance(conf, str) else conf
        print(f"  {i}. {nom}: {conf_val:.3f}")
else:
    # Format: list of dicts
    for i, result in enumerate(results, 1):
        nom = result['nom'] if isinstance(result, dict) else result[0]
        conf = result['confiance'] if isinstance(result, dict) else result[1]
        conf_val = float(conf) if isinstance(conf, str) else conf
        print(f"  {i}. {nom}: {conf_val:.3f}")

# Check for false positives
false_positives = [
    'frites_moyenne', 'riz_blanc', 'salade_verte', 
    'champignon_cuit', 'tomate', 'pomme_de_terre_vapeur'
]

# Extract names from results
if isinstance(results, list) and isinstance(results[0], tuple):
    detected_names = {nom for nom, _ in results}
else:
    detected_names = {r['nom'] if isinstance(r, dict) else r[0] for r in results}

found_false = [fp for fp in false_positives if fp in detected_names]

print("\n" + "="*70)
if found_false:
    print(f"❌ ECHEC: {len(found_false)} faux positif(s) détectés: {found_false}")
    exit(1)
elif len(results) == 1:
    first_name = results[0][0] if isinstance(results[0], tuple) else results[0]['nom']
    if first_name == 'burger_classique':
        conf = results[0][1] if isinstance(results[0], tuple) else results[0]['confiance']
        print(f"✅ SUCCESS: Burger seul détecté avec confiance {conf:.3f}")
        exit(0)
    else:
        print(f"⚠️ Erreur: Détecté {first_name} au lieu de burger_classique")
        exit(2)
else:
    print(f"⚠️ PARTIEL: {len(results)} aliments détectés (attendu: 1)")
    exit(2)
