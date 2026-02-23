#!/usr/bin/env python3
"""
Simple verification of ML module imports and configuration.
No index building - just dependency checks.
"""

import sys
import os

# Add python directory to path
python_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'python')
sys.path.insert(0, python_dir)

print()
print("="*70)
print("ML MODULE VERIFICATION (Cleaned System)")
print("="*70)
print()

# Test imports
errors = []

print("[1/4] Checking imports...")

# Import 1: NutritionKnowledge (simplest, no model loading)
try:
    from ml.nutrition_knowledge import NUTRITION_DATA
    print(f"      ✓ NutritionKnowledge ({len(NUTRITION_DATA)} foods)")
except Exception as e:
    print(f"      ✗ NutritionKnowledge: {e}")
    errors.append(("NutritionKnowledge", str(e)[:100]))

# Import 2: ImageSimilarityMatcher without index
try:
    import ml.similarity_matcher as sm
    print("      ✓ ImageSimilarityMatcher module loaded")
except Exception as e:
    print(f"      ✗ ImageSimilarityMatcher: {e}")
    errors.append(("ImageSimilarityMatcher", str(e)[:100]))

# Import 3: FullNutritionAnalyzer (depends on both above)
try:
    import ml.full_nutrition_analyzer as fna
    print("      ✓ FullNutritionAnalyzer module loaded")
except Exception as e:
    print(f"      ✗ FullNutritionAnalyzer: {e}")
    errors.append(("FullNutritionAnalyzer", str(e)[:100]))

print()
print("[2/4] Checking data integrity...")

# Check dataset
data_dir = os.path.join(python_dir, 'data', 'raw')
if os.path.exists(data_dir):
    image_count = len([f for f in os.walk(data_dir) for img in f[2] if img.lower().endswith(('.jpg', '.png', '.jpeg'))])
    print(f"      ✓ Dataset found ({image_count} images in data/raw)")
else:
    print("      ✗ Dataset not found")
    errors.append(("Dataset", "data/raw directory missing"))

print()
print("[3/4] Checking FastAPI...")

try:
    import app
    print("      ✓ FastAPI app module loaded")
except Exception as e:
    print(f"      ✗ FastAPI: {e}")
    errors.append(("FastAPI", str(e)[:100]))

print()
print("[4/4] Summary...")

if errors:
    print(f"      ✗ {len(errors)} error(s) detected:")
    for name, err in errors:
        print(f"         - {name}: {err[:80]}")
    sys.exit(1)
else:
    print("      ✓ All essential modules and files present")
    print()
    print("="*70)
    print("✓ SYSTEM READY FOR DEPLOYMENT")
    print("  - Use: python -m uvicorn app:app --host 127.0.0.1 --port 8001")
    print("="*70)
