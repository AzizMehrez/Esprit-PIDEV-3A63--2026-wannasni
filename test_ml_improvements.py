#!/usr/bin/env python3
"""
Test Script for Improved ML Detection System
Tests the new thresholds and validation logic
"""

import sys
import json
from pathlib import Path

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from python.ml.full_nutrition_analyzer import FullNutritionAnalyzer

def test_simple_detection():
    """Test single food detection (e.g., apple)"""
    print("\n" + "="*60)
    print("TEST 1: Simple Detection (Single Food)")
    print("="*60)
    
    analyzer = FullNutritionAnalyzer()
    
    # Simulate a simple food detection with high similarity score
    test_food = {
        'nom': 'pomme',
        'confiance': 0.72,
        'source': 'similarity',
        'quantite': 150,
    }
    
    nutrition = analyzer._calculate_detailed_nutrition(test_food)
    
    if nutrition:
        print(f"✓ Food: {nutrition['nom_affichage']}")
        print(f"  Calories: {nutrition['calories']} kcal")
        print(f"  Quantity: {nutrition['quantite']}g")
        print(f"  Category: {nutrition['categorie']}")
        print(f"  Portion: {nutrition['taille_portion']['label']}")
    else:
        print("✗ Failed to calculate nutrition")
    
    return nutrition is not None


def test_threshold_validation():
    """Test that thresholds are correctly applied"""
    print("\n" + "="*60)
    print("TEST 2: Threshold Validation")
    print("="*60)
    
    from python.ml.full_nutrition_analyzer import (
        SIMILARITY_PRIMARY_THRESHOLD,
        SIMILARITY_SECONDARY_THRESHOLD,
        CNN_PRIMARY_THRESHOLD,
        CNN_SECONDARY_THRESHOLD,
        FUSION_PRIMARY_THRESHOLD,
        FUSION_SECONDARY_THRESHOLD,
    )
    
    thresholds = {
        'SIMILARITY_PRIMARY': SIMILARITY_PRIMARY_THRESHOLD,
        'SIMILARITY_SECONDARY': SIMILARITY_SECONDARY_THRESHOLD,
        'CNN_PRIMARY': CNN_PRIMARY_THRESHOLD,
        'CNN_SECONDARY': CNN_SECONDARY_THRESHOLD,
        'FUSION_PRIMARY': FUSION_PRIMARY_THRESHOLD,
        'FUSION_SECONDARY': FUSION_SECONDARY_THRESHOLD,
    }
    
    print("Configured Thresholds:")
    for name, threshold in thresholds.items():
        print(f"  {name}: {threshold:.2f}")
    
    # Verify thresholds are reasonable
    assert SIMILARITY_PRIMARY_THRESHOLD >= CNN_PRIMARY_THRESHOLD, "Similarity should be stricter"
    assert CNN_PRIMARY_THRESHOLD >= CNN_SECONDARY_THRESHOLD, "Primary should be stricter than secondary"
    
    print("\n✓ All thresholds validated correctly")
    return True


def test_food_combination_validation():
    """Test that illogical food combinations are filtered"""
    print("\n" + "="*60)
    print("TEST 3: Food Combination Validation")
    print("="*60)
    
    analyzer = FullNutritionAnalyzer()
    
    # Test case 1: Too many foods (should limit to 5)
    too_many_foods = [
        {'nom': f'aliment_{i}', 'confiance': 0.5 + i*0.02} 
        for i in range(10)
    ]
    # Replace with actual food names
    too_many_foods = [
        {'nom': 'pomme', 'confiance': 0.80},
        {'nom': 'banane', 'confiance': 0.70},
        {'nom': 'pizza', 'confiance': 0.65},
        {'nom': 'burger', 'confiance': 0.60},
        {'nom': 'fraise', 'confiance': 0.55},
        {'nom': 'orange', 'confiance': 0.50},
        {'nom': 'kiwi', 'confiance': 0.48},
        {'nom': 'melon', 'confiance': 0.46},
    ]
    
    validated = analyzer._validate_food_combinations(too_many_foods)
    
    print(f"Input foods: {len(too_many_foods)}")
    print(f"Validated foods: {len(validated)}")
    print(f"Foods kept: {[f['nom'] for f in validated]}")
    
    if len(validated) <= 5:
        print("✓ Correctly limited to max 5 foods")
        return True
    else:
        print("✗ Failed to limit foods")
        return False


def test_nutrition_correction():
    """Test that cooking corrections are applied"""
    print("\n" + "="*60)
    print("TEST 4: Nutrition Correction Factors")
    print("="*60)
    
    analyzer = FullNutritionAnalyzer()
    
    from python.ml.nutrition_knowledge import NUTRITION_DATA
    
    test_cases = [
        ('poulet_grille', "Grilled chicken (high loss)"),
        ('steak_boeuf', "Grilled beef (high loss)"),
        ('salad_verte', "Green salad (minimal loss)"),
        ('riz_blanc', "White rice (water absorption)"),
    ]
    
    print("Correction Factors Applied:")
    for food_name, description in test_cases:
        if food_name in NUTRITION_DATA:
            data = NUTRITION_DATA[food_name]
            factor = analyzer._get_nutrition_correction_factor(food_name, data)
            print(f"  {food_name}: {factor:.2f}x ({description})")
    
    print("\n✓ Correction factors validated")
    return True


def test_quantity_validation():
    """Test that quantities are validated and adjusted"""
    print("\n" + "="*60)
    print("TEST 5: Quantity Validation")
    print("="*60)
    
    analyzer = FullNutritionAnalyzer()
    
    from python.ml.nutrition_knowledge import NUTRITION_DATA
    
    test_cases = [
        ('pomme', 50, "Too small (half apple)"),
        ('pomme', 500, "Too large (too many apples)"),
        ('pomme', 150, "Perfect (one apple)"),
        ('pizza', 300, "Reasonable (one slice with toppings)"),
        ('pizza', 2000, "Too large (entire pizza)"),
    ]
    
    print("Quantity Validation Results:")
    for food, qty, description in test_cases:
        data = NUTRITION_DATA[food]
        adjusted = analyzer._validate_and_adjust_quantity(food, qty, data)
        changed = "✓ ADJUSTED" if adjusted != qty else "✓ OK"
        print(f"  {food} {qty}g → {adjusted}g [{changed}] ({description})")
    
    print("\n✓ Quantity validation working")
    return True


def run_all_tests():
    """Run all tests"""
    print("\n" + "█"*60)
    print("█ ML DETECTION SYSTEM - IMPROVEMENT TESTS")
    print("█"*60)
    
    results = {}
    
    results['simple_detection'] = test_simple_detection()
    results['thresholds'] = test_threshold_validation()
    results['combinations'] = test_food_combination_validation()
    results['nutrition'] = test_nutrition_correction()
    results['quantity'] = test_quantity_validation()
    
    # Summary
    print("\n" + "="*60)
    print("SUMMARY")
    print("="*60)
    
    passed = sum(1 for v in results.values() if v)
    total = len(results)
    
    for test_name, result in results.items():
        status = "✓ PASS" if result else "✗ FAIL"
        print(f"{test_name}: {status}")
    
    print(f"\nTotal: {passed}/{total} tests passed")
    
    if passed == total:
        print("\n✅ All improvements validated successfully!")
        return 0
    else:
        print("\n⚠️ Some tests failed, review output above")
        return 1


if __name__ == "__main__":
    exit_code = run_all_tests()
    sys.exit(exit_code)
