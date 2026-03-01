#!/usr/bin/env python3
"""
Test RAPIDE: Vérifier que le filtre strict est bien activé
"""

import sys
from pathlib import Path

# Ajouter le chemin Python ML
sys.path.insert(0, str(Path(__file__).parent / 'ml'))

print("1️⃣ Test import du filtre strict...")
try:
    from strict_false_positive_filter import StrictFalsePositiveFilter
    print("   ✅ Import réussi")
except Exception as e:
    print(f"   ❌ Erreur import: {e}")
    sys.exit(1)

print("\n2️⃣ Test création du filtre...")
try:
    filter_obj = StrictFalsePositiveFilter()
    print("   ✅ Filtre créé")
except Exception as e:
    print(f"   ❌ Erreur création: {e}")
    sys.exit(1)

print("\n3️⃣ Test filtre avec cas chocolat (fausse positive)...")
chocolate_case = [
    {'name': 'spaghetti bolognaise', 'confidence': 0.48, 'source': 'similarity'},
    {'name': 'glace', 'confidence': 0.45, 'source': 'cnn'},
    {'name': 'poulet grille', 'confidence': 0.42, 'source': 'cnn'},
]

print(f"   Input: {len(chocolate_case)} aliments")
for c in chocolate_case:
    print(f"     • {c['name']:25} conf={c['confidence']:.2f}")

result = filter_obj.filter_detections(chocolate_case)
print(f"   Output: {len(result)} aliments")
if result:
    for r in result:
        print(f"     ✓ {r['name']}")
else:
    print(f"     🚫 TOUS LES ALIMENTS REJETÉS (correct!)")

if not result:
    print("\n   ✅ TEST RÉUSSI: Filtre rejette correctement les fausses positives!")
else:
    print(f"\n   ❌ TEST ÉCHOUÉ: {len(result)} aliments acceptés (devrait être 0)")

print("\n4️⃣ Test filtre avec cas valide (chocolat seul)...")
good_case = [
    {'name': 'chocolat', 'confidence': 0.72, 'source': 'similarity'},
]

result2 = filter_obj.filter_detections(good_case)
print(f"   Input: 1 aliment (chocolat, conf=0.72)")
print(f"   Output: {len(result2)} aliments")

if result2 and result2[0]['name'] == 'chocolat':
    print("   ✅ TEST RÉUSSI: Filtre accepte cas valide!")
else:
    print("   ❌ TEST ÉCHOUÉ: Chocolat devrait être accepté")

print("\n" + "="*70)
print("✅ TOUS LES TESTS FILTRE PASSÉS" if (not result and result2) else "❌ CERTAINS TESTS ÉCHOUÉS")
