#!/usr/bin/env python3
"""Test multiple food types to ensure model works correctly."""

from ml.full_nutrition_analyzer import FullNutritionAnalyzer
import os

analyzer = FullNutritionAnalyzer()

print("\n" + "="*70)
print("TESTING MULTIPLE FOOD TYPES")
print("="*70)

test_cases = [
    ('data/raw/fast_food/burger', 'burger'),
    ('data/raw/fast_food/pizza', 'pizza'),
    ('data/raw/viandes/escalope_panee', 'escalope'),
]

for path, label in test_cases:
    if not os.path.exists(path):
        print(f"\n[{label.upper()}] Path not found: {path}")
        continue
    
    images = [f for f in os.listdir(path) if f.endswith(('.jpg', '.png'))]
    if not images:
        print(f"\n[{label.upper()}] No images found in {path}")
        continue
    
    img_name = images[0]
    img_path = os.path.join(path, img_name)
    
    print(f"\n[{label.upper()}] Testing: {img_name}")
    try:
        results = analyzer.detect_only(img_path)
        
        if not results:
            print("  No results!")
            continue
        
        # Handle both tuple and dict format
        for i, result in enumerate(results[:2], 1):  # Show top 2
            if isinstance(result, tuple):
                nom, conf = result
            else:
                nom = result.get('nom')
                conf = result.get('confiance')
            print(f"  {i}. {nom}: {conf:.3f}")
            
    except Exception as e:
        print(f"  ERROR: {e}")

print("\n" + "="*70)
