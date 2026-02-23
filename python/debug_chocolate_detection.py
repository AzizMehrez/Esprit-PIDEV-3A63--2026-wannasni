#!/usr/bin/env python3
"""
Debug du problème de détection du chocolat
Trace exactement ce qui s'est passé et pourquoi
"""

import sys
import json
from pathlib import Path

# Ajouter les chemins Python
sys.path.insert(0, str(Path(__file__).parent / 'ml'))

from full_nutrition_analyzer import FullNutritionAnalyzer
from detection_debugger import DetectionDebugger, DetectionValidator
from food_detection_corrector import FoodDetectionCorrector

def test_chocolate_detection():
    """Test la détection du chocolat et trace le problème"""
    
    print("\n" + "="*70)
    print("🔍 DEBUG: Pourquoi le chocolat → Pâtes/Glace/Lasagnes?")
    print("="*70 + "\n")
    
    # Initialiser les outils
    analyzer = FullNutritionAnalyzer()
    debugger = DetectionDebugger()
    validator = DetectionValidator()
    corrector = FoodDetectionCorrector()
    
    # Simulation d'une image de chocolat
    # (on va tester avec des données simulées)
    test_image_path = "test_chocolate.jpg"
    
    print("📸 Image: Chocolat simple (barre ou carré)")
    print("🎯 Attendu: Chocolat (~150-200 kcal)")
    print("❌ Obtenu: Pâtes (650) + Glace (207) + Poulet (248) + Lasagnes (750) = 1855 kcal!")
    print("\n" + "-"*70)
    
    # Étape 1: Analyser les candidats bruts
    print("\n1️⃣ CANDIDATS BRUTS (avant correction):")
    print("-" * 70)
    
    # Simuler ce qui a probablement été détecté
    raw_candidates = [
        {'name': 'pâtes bolognaise', 'confidence': 0.48, 'source': 'similarity'},
        {'name': 'glace', 'confidence': 0.45, 'source': 'cnn'},
        {'name': 'poulet grillé', 'confidence': 0.42, 'source': 'cnn'},
        {'name': 'lasagnes', 'confidence': 0.40, 'source': 'similarity'},
        {'name': 'pancakes', 'confidence': 0.38, 'source': 'cnn'},
        {'name': 'chocolat', 'confidence': 0.35, 'source': 'similarity'},
    ]
    
    for i, cand in enumerate(raw_candidates, 1):
        print(f"  {i}. {cand['name']:25} | Confiance: {cand['confidence']:.2f} | Source: {cand['source']}")
    
    # Étape 2: Analyser les problèmes
    print("\n2️⃣ ANALYSE DU PROBLÈME:")
    print("-" * 70)
    
    avg_confidence = sum(c['confidence'] for c in raw_candidates) / len(raw_candidates)
    print(f"  • Confiance moyenne: {avg_confidence:.2f}")
    print(f"  • Trop d'aliments: {len(raw_candidates)} détectés (> 5 = anormal)")
    print(f"  • Le chocolat a {raw_candidates[-1]['confidence']:.2f} confiance (le plus bas!)")
    print(f"  • Pâtes/Lasagnes sont similaires (redondance?)")
    print(f"  • Combinaison illogique: pâtes + glace + poulet = plat chaos")
    print(f"  • Aucun consensus: scores bas partout (< 0.50)")
    
    # Étape 3: Application du correcteur
    print("\n3️⃣ APRÈS CORRECTION (FoodDetectionCorrector):")
    print("-" * 70)
    
    corrected = corrector.correct_detections(raw_candidates)
    
    if corrected:
        for i, cand in enumerate(corrected, 1):
            print(f"  {i}. {cand['name']:25} | Confiance: {cand['confidence']:.2f}")
    else:
        print("  ✓ TOUTES les détections supprimées (correcteur détecta chaos)")
    
    # Étape 4: Validation
    print("\n4️⃣ VALIDATION (DetectionValidator):")
    print("-" * 70)
    
    validation_report = validator.validate_detections(
        raw_candidates,
        image_path=test_image_path
    )
    
    print(f"  Pattern détecté: {validation_report.get('pattern', 'unknown')}")
    if validation_report.get('is_valid'):
        print(f"  Verdict: ✓ VALIDE")
    else:
        print(f"  Verdict: ❌ INVALIDE")
        if validation_report.get('reason'):
            print(f"  Raison: {validation_report['reason']}")
    
    # Étape 5: Recommandations
    print("\n5️⃣ RECOMMANDATIONS:")
    print("-" * 70)
    
    recommendations = [
        "🔴 SEUIL TROP BAS: Le seuil primaire de 0.60 accepte 0.48 (pâtes). Trop permissif!",
        "🔴 FUSION DÉFECTUEUSE: 75% similarity + 25% CNN ne filtre pas assez",
        "🟡 CNN TROP BRUYANT: Génère trop de faux positifs (glace: 0.45, poulet: 0.42)",
        "🟡 OUTLIER DETECTION: Devrait rejeter le chocolat à 0.35 car trop bas",
        "✅ SOLUTION: Augmenter seuil minimum global ou rejeter si > 3 candidats avec score faible"
    ]
    
    for rec in recommendations:
        print(f"  {rec}")
    
    print("\n" + "="*70)
    print("🛠️ ACTION: Appliquer correction stricte Level 3")
    print("="*70 + "\n")
    
    return {
        'raw_count': len(raw_candidates),
        'corrected_count': len(corrected) if corrected else 0,
        'avg_confidence': avg_confidence,
        'is_valid': validation_report.get('is_valid', False),
        'pattern': validation_report.get('pattern', 'unknown')
    }

if __name__ == '__main__':
    result = test_chocolate_detection()
    print(f"\n📊 RÉSUMÉ:")
    print(f"  Détections brutes: {result['raw_count']}")
    print(f"  Après correction: {result['corrected_count']}")
    print(f"  Confiance moyenne: {result['avg_confidence']:.2f}")
    print(f"  Valide: {result['is_valid']}")
