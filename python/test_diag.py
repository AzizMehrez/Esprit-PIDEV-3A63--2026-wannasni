"""
WANNASNI ML Diagnostic Test Script
====================================
Tests the complete ML pipeline: detection, nutrition, recipes, and reports.
"""

import sys
import os
import glob

# Add current directory to path
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

def find_test_images():
    """Find available test images in data/raw/"""
    data_dir = os.path.join(os.path.dirname(__file__), 'data', 'raw')
    images = []
    for ext in ['*.jpg', '*.jpeg', '*.png', '*.webp']:
        images.extend(glob.glob(os.path.join(data_dir, '**', ext), recursive=True))
    return images


def test_detection():
    """Test food detection with available images."""
    print("\n" + "=" * 60)
    print("TEST 1: Food Detection")
    print("=" * 60)

    try:
        from ml.full_nutrition_analyzer import FullNutritionAnalyzer
        analyzer = FullNutritionAnalyzer()
        print("Analyzer initialized successfully!")

        images = find_test_images()
        if not images:
            print("WARNING: No test images found in data/raw/")
            return None

        print(f"Found {len(images)} test images\n")

        # Test with first 5 images
        for img_path in images[:5]:
            rel_path = os.path.relpath(img_path, os.path.dirname(__file__))
            print(f"  Image: {rel_path}")
            result = analyzer.detect_only(img_path)

            if result.get('detected'):
                foods = result['foods']
                for food in foods:
                    print(f"    DETECTED: {food['nom']} "
                          f"(confidence: {food['confiance']:.3f}, "
                          f"source: {food['source']})")
            else:
                print(f"    NOT DETECTED: {result.get('message', 'No message')}")

            print()

        return analyzer

    except Exception as e:
        import traceback
        print(f"ERROR in test_detection: {e}")
        traceback.print_exc()
        return None


def test_nutrition(analyzer):
    """Test nutrition calculation."""
    print("\n" + "=" * 60)
    print("TEST 2: Nutrition Calculation")
    print("=" * 60)

    try:
        # Test with known foods
        test_foods = [
            {"nom": "pomme", "quantite": 150, "unite": "g"},
            {"nom": "poulet_grille", "quantite": 200, "unite": "g"},
        ]

        print(f"Testing nutrition for: {[f['nom'] for f in test_foods]}")
        result = analyzer.calculate_nutrition(test_foods, "Hypocalorique")

        print(f"\n  Total nutrition:")
        for key, val in result['total_nutrition'].items():
            if val > 0:
                print(f"    {key}: {val}")

        print(f"\n  Diet compliance:")
        compliance = result['compliance']
        print(f"    Conforme: {compliance['conforme']}")
        if compliance['raisons']:
            for r in compliance['raisons']:
                print(f"    - {r}")
        if compliance.get('recommandations'):
            for r in compliance['recommandations']:
                print(f"    - {r}")

        print(f"\n  Nutritional score: {result['nutritional_score']['score']}/100")

    except Exception as e:
        import traceback
        print(f"ERROR in test_nutrition: {e}")
        traceback.print_exc()


def test_recipes(analyzer):
    """Test diet-based recipe suggestions."""
    print("\n" + "=" * 60)
    print("TEST 3: Diet-Based Recipe Suggestions")
    print("=" * 60)

    try:
        regimes = ["Hypocalorique", "Diabetique", "Sans Sucre", "Hyperproteine", "Standard"]

        for regime in regimes:
            print(f"\n  Regime: {regime} (800 kcal remaining)")
            result = analyzer._get_diet_based_recipes(800, regime)

            print(f"    Recommended categories: {result.get('categories_recommandees', [])}")
            print(f"    Forbidden categories: {result.get('categories_interdites', [])}")
            print(f"    Recipes found: {len(result.get('recipes', []))}")

            for recipe in result.get('recipes', [])[:3]:
                name = recipe.get('nom', recipe.get('name', 'Unknown'))
                cals = recipe.get('calories', recipe.get('estimated_calories', 0))
                source = recipe.get('source', 'unknown')
                print(f"      - {name} ({cals} kcal, source: {source})")

            if result.get('conseil'):
                print(f"    Conseil: {result['conseil']}")

    except Exception as e:
        import traceback
        print(f"ERROR in test_recipes: {e}")
        traceback.print_exc()


def test_not_detected(analyzer):
    """Test that non-detected foods return NO default data."""
    print("\n" + "=" * 60)
    print("TEST 4: Non-Detection Policy (STRICT)")
    print("=" * 60)

    try:
        # Create a blank/noise image that shouldn't match any food
        import numpy as np
        import cv2
        temp_path = os.path.join(os.path.dirname(__file__), 'temp_uploads', 'test_blank.jpg')
        os.makedirs(os.path.dirname(temp_path), exist_ok=True)

        # Create random noise image
        noise = np.random.randint(0, 255, (224, 224, 3), dtype=np.uint8)
        cv2.imwrite(temp_path, noise)

        result = analyzer.detect_only(temp_path)
        print(f"  Blank image detection result:")
        print(f"    detected: {result.get('detected')}")
        print(f"    message: {result.get('message', 'N/A')}")
        print(f"    foods: {result.get('foods', [])}")

        # Verify strict policy
        if not result.get('detected') and len(result.get('foods', [])) == 0:
            print("    PASS: Non-detected image returns NO food data")
        else:
            print("    FAIL: Non-detected image should return empty foods list")

        # Test full analysis with blank image
        print(f"\n  Full analysis with blank image:")
        full_result = analyzer.analyze_meal(temp_path, "Hypocalorique", 2000, 500)
        print(f"    status: {full_result.get('status')}")
        print(f"    message: {full_result.get('message', 'N/A')}")

        has_nutrition = 'analyse_nutritionnelle' in full_result
        has_recipes = 'recettes_suggerees' in full_result and full_result['recettes_suggerees']
        print(f"    Has nutrition data: {has_nutrition}")
        print(f"    Has recipes: {has_recipes}")

        if full_result.get('status') == 'not_detected' and not has_nutrition and not has_recipes:
            print("    PASS: Non-detected returns NOTHING extra (strict policy)")
        else:
            print("    FAIL: Should return only not_detected status with no extra data")

        # Cleanup
        if os.path.exists(temp_path):
            os.remove(temp_path)

    except Exception as e:
        import traceback
        print(f"ERROR in test_not_detected: {e}")
        traceback.print_exc()


def test_full_analysis(analyzer):
    """Test complete meal analysis."""
    print("\n" + "=" * 60)
    print("TEST 5: Full Meal Analysis")
    print("=" * 60)

    try:
        images = find_test_images()
        if not images:
            print("No test images available")
            return

        # Use first image
        img_path = images[0]
        rel_path = os.path.relpath(img_path, os.path.dirname(__file__))
        print(f"  Image: {rel_path}")
        print(f"  Regime: Hypocalorique, Limit: 2000 kcal, Consumed: 500 kcal\n")

        result = analyzer.analyze_meal(img_path, "Hypocalorique", 2000, 500)

        print(f"  Status: {result.get('status')}")

        if result.get('status') == 'success':
            # Detected foods
            aliments = result.get('aliments_detectes', [])
            print(f"  Foods detected: {len(aliments)}")
            for a in aliments:
                print(f"    - {a.get('nom', '?')}: {a.get('calories', 0)} kcal")

            # Nutrition
            nutrition = result.get('analyse_nutritionnelle', {})
            total = nutrition.get('total_nutrition', {})
            print(f"\n  Total calories: {total.get('calories', 0):.1f} kcal")
            print(f"  Nutritional score: {nutrition.get('nutritional_score', {}).get('score', 'N/A')}/100")

            # Diet compliance
            compliance = nutrition.get('compliance', {})
            print(f"  Diet compliant: {compliance.get('conforme', 'N/A')}")

            # Recipes
            recipes = result.get('recettes_suggerees', [])
            print(f"\n  Recipe suggestions: {len(recipes)}")
            for r in recipes[:3]:
                name = r.get('nom', r.get('name', '?'))
                cals = r.get('calories', r.get('estimated_calories', 0))
                print(f"    - {name} ({cals} kcal)")

            # Diet advice
            if result.get('conseil_regime'):
                print(f"\n  Diet advice: {result['conseil_regime']}")

        elif result.get('status') == 'not_detected':
            print(f"  Message: {result.get('message')}")
            print(f"  Conseil: {result.get('conseil', 'N/A')}")
            print("  (No nutrition/recipe data - correct strict behavior)")

    except Exception as e:
        import traceback
        print(f"ERROR in test_full_analysis: {e}")
        traceback.print_exc()


if __name__ == "__main__":
    print("WANNASNI ML Diagnostic Tests")
    print("=" * 60)

    # Test 1: Detection
    analyzer = test_detection()
    if analyzer is None:
        print("\nFATAL: Analyzer could not be initialized. Stopping tests.")
        sys.exit(1)

    # Test 2: Nutrition
    test_nutrition(analyzer)

    # Test 3: Recipes
    test_recipes(analyzer)

    # Test 4: Non-detection policy
    test_not_detected(analyzer)

    # Test 5: Full analysis
    test_full_analysis(analyzer)

    print("\n" + "=" * 60)
    print("ALL TESTS COMPLETED")
    print("=" * 60)
