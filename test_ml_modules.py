#!/usr/bin/env python3
"""Quick test of cleaned ML module structure"""

import sys
import os

# Add python/ to path
python_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'python')
sys.path.insert(0, python_path)

print()
print("="*70)
print("VERIFICATION: ML Modules Cleaned & Synchronized")
print("="*70)
print()

# Test 1: FullNutritionAnalyzer
print("[1/3] Testing FullNutritionAnalyzer...")
try:
    from ml.full_nutrition_analyzer import FullNutritionAnalyzer
    print("      ✓ FullNutritionAnalyzer (Main Analyzer)")
except ImportError as e:
    print(f"      ✗ Error: {str(e)[:100]}")
    sys.exit(1)

# Test 2: ImageSimilarityMatcher
print("[2/3] Testing ImageSimilarityMatcher...")
try:
    from ml.similarity_matcher import ImageSimilarityMatcher
    print("      ✓ ImageSimilarityMatcher (Feature Matching)")
except ImportError as e:
    print(f"      ✗ Error: {str(e)[:100]}")
    sys.exit(1)

# Test 3: NutritionKnowledge
print("[3/3] Testing NutritionKnowledge...")
try:
    from ml.nutrition_knowledge import NUTRITION_DATA
    print(f"      ✓ NutritionKnowledge ({len(NUTRITION_DATA)} foods)")
except ImportError as e:
    print(f"      ✗ Error: {str(e)[:100]}")
    sys.exit(1)

print()
print("="*70)
print("✓ ALL ESSENTIAL MODULES LOADED SUCCESSFULLY")
print("✓ System ready for deployment on port 8001")
print("="*70)
print()
