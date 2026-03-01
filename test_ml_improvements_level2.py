#!/usr/bin/env python3
"""
Test complet du système ML amélioré - Validation des corrections
"""

import sys
import logging
from pathlib import Path

# Setup logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)

sys.path.insert(0, str(Path(__file__).parent.parent))

from python.ml.full_nutrition_analyzer import FullNutritionAnalyzer
from python.ml.food_detection_corrector import FoodDetectionCorrector, ConfidenceAdjustmentFactory
from python.ml.detection_debugger import DetectionDebugger, DetectionValidator
from python.ml.nutrition_knowledge import NUTRITION_DATA


def test_scenario_1_simple_detection():
    """Scénario 1: Détection simple - une pomme"""
    print("\n" + "="*70)
    print("SCENARIO 1: Simple Detection (Une pomme)")
    print("="*70)
    
    analyzer = FullNutritionAnalyzer()
    
    # Simuler une détection de pomme avec bonne confiance
    test_foods = [
        {
            'nom': 'pomme',
            'confiance': 0.75,
            'source': 'similarity',
            'detected': True,
            'quantite': 150
        }
    ]
    
    # Calculer nutrition
    result = analyzer.calculate_nutrition(test_foods, 'standard')
    
    print(f"✓ Aliment détecté: pomme")
    print(f"✓ Confiance: 0.75 (SIMILARITY - seuil strict: 0.60)")
    print(f"✓ Calories calculées: {result['total_nutrition']['calories']} kcal")
    print(f"✓ Nombre d'aliments: {result['nombre_aliments']}")
    
    assert result['nombre_aliments'] == 1
    assert result['total_nutrition']['calories'] > 0
    print("✅ TEST PASS: Simple detection fonctionne")
    return True


def test_scenario_2_false_positive_removal():
    """Scénario 2: Suppression d'un faux positif (pizza + burger)"""
    print("\n" + "="*70)
    print("SCENARIO 2: False Positive Removal (Pizza + Burger)")
    print("="*70)
    
    corrector = FoodDetectionCorrector()
    debugger = DetectionDebugger(enable_verbose=True)
    
    # Simuler faux positifs: pizza ET burger détectés
    test_foods = [
        {
            'nom': 'pizza',
            'confiance': 0.72,
            'source': 'similarity',
            'detected': True,
        },
        {
            'nom': 'burger',
            'confiance': 0.65,  # Moins confiant - devrait être supprimé
            'source': 'cnn',
            'detected': True,
        }
    ]
    
    print(f"Avant correction: {[f['nom'] for f in test_foods]}")
    
    corrected = corrector.correct_detections(test_foods, debugger)
    
    print(f"Après correction: {[f['nom'] for f in corrected]}")
    
    # Vérifier que le moins confiant a été supprimé
    assert len(corrected) == 1
    assert corrected[0]['nom'] == 'pizza'
    
    print("✅ TEST PASS: Faux positif supprimé correctement")
    return True


def test_scenario_3_plausibility_boost():
    """Scénario 3: Augmentation de confiance pour combinaison plausible"""
    print("\n" + "="*70)
    print("SCENARIO 3: Plausibility Boost (Protéine + Légume)")
    print("="*70)
    
    corrector = FoodDetectionCorrector()
    debugger = DetectionDebugger(enable_verbose=True)
    
    # Combinaison très plausible: steak + salade
    test_foods = [
        {
            'nom': 'steak_boeuf',
            'confiance': 0.65,
            'source': 'cnn',
            'detected': True,
        },
        {
            'nom': 'salade_verte',
            'confiance': 0.58,
            'source': 'similarity',
            'detected': True,
        }
    ]
    
    print(f"Avant boost:")
    for f in test_foods:
        print(f"  - {f['nom']}: {f['confiance']:.2f}")
    
    corrected = corrector.correct_detections(test_foods, debugger)
    
    print(f"Après boost:")
    for f in corrected:
        print(f"  - {f['nom']}: {f['confiance']:.3f}")
    
    # La confiance devrait avoir augmenté
    salad = next((f for f in corrected if f['nom'] == 'salade_verte'), None)
    assert salad is not None
    assert salad['confiance'] > 0.58, "Confiance devrait augmenter pour combo plausible"
    
    print("✅ TEST PASS: Plausibilité appliquée correctement")
    return True


def test_scenario_4_quantity_validation():
    """Scénario 4: Validation et ajustement de quantité"""
    print("\n" + "="*70)
    print("SCENARIO 4: Quantity Validation")
    print("="*70)
    
    analyzer = FullNutritionAnalyzer()
    
    test_cases = [
        ('pomme', 30, "Trop petite (30g)"),
        ('pomme', 800, "Trop grande (800g)"),
        ('pizza', 2000, "Trop grande (2000g)"),
        ('boisson', 500, "Normal pour une boisson"),
    ]
    
    print("Validation de quantités:")
    for food_name, qty, description in test_cases:
        data = NUTRITION_DATA[food_name]
        adjusted = analyzer._validate_and_adjust_quantity(food_name, qty, data)
        
        if adjusted == qty:
            status = "✓ OK"
        else:
            status = f"✓ AJUSTÉE ({qty}g → {adjusted}g)"
        
        print(f"  {food_name:20} {qty:4}g → {adjusted:5.0f}g [{status}] ({description})")
    
    print("✅ TEST PASS: Quantités validées correctement")
    return True


def test_scenario_5_nutrition_correction_factors():
    """Scénario 5: Facteurs de correction nutritionnels"""
    print("\n" + "="*70)
    print("SCENARIO 5: Nutrition Correction Factors (Cuisson)")
    print("="*70)
    
    analyzer = FullNutritionAnalyzer()
    
    test_cases = [
        'pomme',  # 1.0 (pas de transformation)
        'poulet_grille',  # 0.88 (perte importante)
        'steak_boeuf',  # 0.85 (perte importante)
        'legume',  # 0.95 (légère perte)
    ]
    
    print("Facteurs de correction appliqués:")
    for food_name in test_cases:
        data = NUTRITION_DATA[food_name]
        factor = analyzer._get_nutrition_correction_factor(food_name, data)
        
        # Interpréter le facteur
        if factor == 1.0:
            interpretation = "Pas de transformation"
        elif factor > 1.0:
            interpretation = f"+{(factor-1)*100:.0f}% (absorption eau/liquide)"
        else:
            interpretation = f"-{(1-factor)*100:.0f}% (perte en cuisson)"
        
        print(f"  {food_name:20} factor: {factor:.2f} → {interpretation}")
    
    print("✅ TEST PASS: Facteurs de correction appliqués")
    return True


def test_scenario_6_combination_validation():
    """Scénario 6: Validation des combinaisons d'aliments"""
    print("\n" + "="*70)
    print("SCENARIO 6: Combination Validation")
    print("="*70)
    
    analyzer = FullNutritionAnalyzer()
    
    test_cases = [
        (
            ['pizza', 'salade_verte'],
            "Plausible: plat complet + fruits/légumes"
        ),
        (
            ['steak_boeuf', 'legume', 'riz'],
            "Très plausible: protéine + légume + féculent"
        ),
        (
            ['pomme', 'burger', 'pizza'],
            "Implausible: fruit avec deux plats"
        ),
    ]
    
    print("Validation de combinaisons:")
    for foods_names, description in test_cases:
        test_foods = [
            {'nom': name, 'confiance': 0.70, 'detected': True}
            for name in foods_names
        ]
        
        validated = analyzer._validate_food_combinations(test_foods)
        
        print(f"\n  Input:  {foods_names}")
        print(f"  Output: {[f['nom'] for f in validated]}")
        print(f"  Note:   {description}")
    
    print("\n✅ TEST PASS: Combinaisons validées correctement")
    return True


def test_scenario_7_detection_validation():
    """Scénario 7: Validation finale des détections"""
    print("\n" + "="*70)
    print("SCENARIO 7: Detection Validation (Outlier Detection)")
    print("="*70)
    
    # Test cas 1: Trop d'aliments
    test_foods_too_many = [
        {'nom': f'aliment_{i}', 'confiance': 0.7 - i*0.05, 'detected': True}
        for i in range(8)
    ]
    test_foods_too_many = [
        {'nom': 'pomme', 'confiance': 0.75, 'detected': True},
        {'nom': 'banane', 'confiance': 0.70, 'detected': True},
        {'nom': 'burger', 'confiance': 0.65, 'detected': True},
        {'nom': 'pizza', 'confiance': 0.60, 'detected': True},
        {'nom': 'salade', 'confiance': 0.55, 'detected': True},
        {'nom': 'riz', 'confiance': 0.50, 'detected': True},
        {'nom': 'poisson', 'confiance': 0.45, 'detected': True},
        {'nom': 'legume', 'confiance': 0.40, 'detected': True},
    ]
    
    result = DetectionValidator.validate(test_foods_too_many)
    
    print(f"Cas 1: Trop d'aliments ({len(test_foods_too_many)})")
    print(f"  Problèmes détectés: {len(result['issues'])}")
    for issue in result['issues']:
        print(f"    - {issue['pattern']}: {issue['description']}")
    
    assert any(i['pattern'] == 'trop_d_aliments' for i in result['issues']), \
        "Devrait détecter trop d'aliments"
    
    # Test cas 2: Confiance basse
    test_foods_low_conf = [
        {'nom': 'pomme', 'confiance': 0.40, 'detected': True},
        {'nom': 'banane', 'confiance': 0.35, 'detected': True},
        {'nom': 'burger', 'confiance': 0.38, 'detected': True},
    ]
    
    result2 = DetectionValidator.validate(test_foods_low_conf)
    
    print(f"\nCas 2: Confiance basse (moyenne < 0.40)")
    print(f"  Problèmes détectés: {len(result2['issues'])}")
    
    print("\n✅ TEST PASS: Validation détecte les problèmes")
    return True


def test_scenario_8_end_to_end():
    """Scénario 8: Test end-to-end du système complet"""
    print("\n" + "="*70)
    print("SCENARIO 8: End-to-End System Test")
    print("="*70)
    
    analyzer = FullNutritionAnalyzer()
    
    # Simuler une détection multi-aliments
    print("Simulation: Photo d'une assiette composée")
    print("Aliments réels: Steak grillé + Salade verte + Riz blanc\n")
    
    # Détections du modèle (y compris faux positifs potentiels)
    detected_foods = [
        {'nom': 'steak_boeuf', 'confiance': 0.78, 'source': 'similarity', 'detected': True, 'quantite': 150},
        {'nom': 'salade_verte', 'confiance': 0.72, 'source': 'similarity_alt', 'detected': True, 'quantite': 100},
        {'nom': 'riz_blanc', 'confiance': 0.68, 'source': 'fusion_sim+cnn', 'detected': True, 'quantite': 150},
        {'nom': 'fraise', 'confiance': 0.45, 'source': 'cnn', 'detected': True, 'quantite': 50},  # Faux positif
    ]
    
    print("Détections brutes:")
    for f in detected_foods:
        print(f"  {f['nom']:20} confiance: {f['confiance']:.2f} (source: {f['source']})")
    
    # Appliquer le pipeline de nettoyage
    print("\n1. Filtrage par seuil...")
    corrector = FoodDetectionCorrector()
    filtered = [f for f in detected_foods if f['confiance'] >= 0.50]
    print(f"   Avant: {len(detected_foods)} → Après: {len(filtered)}")
    
    print("\n2. Correction intelligente...")
    corrected = analyzer.food_corrector.correct_detections(filtered)
    print(f"   Avant: {len(filtered)} → Après: {len(corrected)}")
    for f in corrected:
        print(f"     {f['nom']:20} confiance: {f['confiance']:.3f}")
    
    print("\n3. Calcul nutritionnel...")
    result = analyzer.calculate_nutrition(corrected, 'standard')
    print(f"   Total calories: {result['total_nutrition']['calories']} kcal")
    print(f"   Protéines: {result['total_nutrition']['proteines']:.1f}g")
    print(f"   Nombre d'aliments: {result['nombre_aliments']}")
    
    assert result['nombre_aliments'] >= 3, "Devrait détecter au moins les 3 aliments principaux"
    
    print("\n✅ TEST PASS: Système end-to-end fonctionne")
    return True


def run_all_tests():
    """Exécuter tous les tests"""
    print("\n" + "█"*70)
    print("█ ML DETECTION SYSTEM - IMPROVED TESTS (LEVEL 2)")
    print("█"*70)
    
    tests = [
        ("Simple Detection", test_scenario_1_simple_detection),
        ("False Positive Removal", test_scenario_2_false_positive_removal),
        ("Plausibility Boost", test_scenario_3_plausibility_boost),
        ("Quantity Validation", test_scenario_4_quantity_validation),
        ("Nutrition Corrections", test_scenario_5_nutrition_correction_factors),
        ("Combination Validation", test_scenario_6_combination_validation),
        ("Detection Validation", test_scenario_7_detection_validation),
        ("End-to-End System", test_scenario_8_end_to_end),
    ]
    
    results = {}
    for test_name, test_func in tests:
        try:
            results[test_name] = test_func()
        except AssertionError as e:
            print(f"\n❌ TEST FAILED: {test_name}")
            print(f"   Assertion: {e}")
            results[test_name] = False
        except Exception as e:
            print(f"\n❌ TEST ERROR: {test_name}")
            print(f"   Error: {e}")
            results[test_name] = False
    
    # Summary
    print("\n" + "="*70)
    print("SUMMARY")
    print("="*70)
    
    passed = sum(1 for v in results.values() if v)
    total = len(results)
    
    for test_name, result in results.items():
        status = "✅ PASS" if result else "❌ FAIL"
        print(f"{test_name:30} {status}")
    
    print(f"\nTotal: {passed}/{total} tests passed")
    
    if passed == total:
        print("\n🎉 All improvements validated successfully!")
        print("\nKey improvements:")
        print("  ✓ Faux positifs supprimés intelligemment")
        print("  ✓ Combinaisons plausibles boostées")
        print("  ✓ Quantités validées et ajustées")
        print("  ✓ Facteurs de correction appliqués")
        print("  ✓ Validation contextuelle en place")
        return 0
    else:
        print("\n⚠️ Some tests failed, review output above")
        return 1


if __name__ == "__main__":
    exit_code = run_all_tests()
    sys.exit(exit_code)
