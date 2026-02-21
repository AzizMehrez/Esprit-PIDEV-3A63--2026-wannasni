#!/usr/bin/env python3
"""Test burger detection - simplified version."""

import sys
sys.path.insert(0, '.')

from ml.full_nutrition_analyzer import FullNutritionAnalyzer

print("\n" + "="*70)
print("TEST FINAL - BURGER DETECTION")
print("="*70 + "\n")

analyzer = FullNutritionAnalyzer()
results = analyzer.detect_only('data/raw/fast_food/burger/burger.jpg')

print(f"Detected: {results.get('detected', False)}")

# Extract foods
foods_list = results.get('foods', [])
detected_names = [f.get('nom') for f in foods_list]

print(f"Foods detected: {detected_names}")
print(f"Number of items: {len(detected_names)}\n")

# Check for false positives
false_positives = ['frites_moyenne', 'riz_blanc', 'salade_verte', 'champignon_cuit', 'tomate']
found_false = [fp for fp in false_positives if fp in detected_names]

print("="*70)
if len(detected_names) == 1 and 'burger' in detected_names[0].lower():
    confidence = foods_list[0].get('confiance', 0)
    print("SUCCES: Burger seul detecte ({:.3f}) - AUCUN faux positif!".format(confidence))
    exit(0)
elif found_false:
    print("FAILED: {} faux positif(s) detectes: {}".format(len(found_false), found_false))
    exit(1)
else:
    print("PARTIAL: {} aliments detectes".format(len(detected_names)))
    for nom in detected_names:
        print(f"  - {nom}")
    exit(2)
