"""Comprehensive diagnostic: trace exactly why frites gets detected as burger."""
import sys, os, io
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '2'
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')
import logging
logging.basicConfig(level=logging.INFO, format='%(name)s - %(levelname)s - %(message)s')

sys.path.insert(0, 'python')

from ml.full_nutrition_analyzer import FullNutritionAnalyzer
from ml.nutrition_knowledge import NUTRITION_DATA
from ml.similarity_matcher import CATEGORY_FOOD_MAPPING

print("="*80)
print("DIAGNOSTIC COMPLET - Détection frites")
print("="*80)

# 1. Check mappings
print("\n--- MAPPINGS ---")
for key in ['frites', 'frites_maison', 'fast food', 'fast_food']:
    if key in CATEGORY_FOOD_MAPPING:
        print(f"  CATEGORY_FOOD_MAPPING['{key}'] = {CATEGORY_FOOD_MAPPING[key]}")

# 2. Check NUTRITION_DATA categories
print("\n--- CATEGORIES fast_food dans NUTRITION_DATA ---")
for k, v in NUTRITION_DATA.items():
    if v.get('categorie') == 'fast_food':
        print(f"  {k}: categorie={v.get('categorie')}, calories={v.get('calories')}")

# 3. Init analyzer
print("\n--- INITIALISATION ANALYSEUR ---")
analyzer = FullNutritionAnalyzer()

# 4. Check labels
print(f"\nCNN Labels: {analyzer.labels}")

# 5. Test map_category_to_food for frites
matcher = analyzer.similarity_matcher
for cat in ['frites_maison', 'frites', 'fast food', 'fast_food', 'burger', 'poulet', 'escalope_panee', 'riz']:
    mapped = matcher.map_category_to_food(cat)
    print(f"  map_category_to_food('{cat}') → {mapped}")

# 6. Test with a frites image
test_images = [
    os.path.join('python', 'data', 'raw', 'fast food', 'frites aussi.jpg'),
    os.path.join('python', 'data', 'raw', 'fast food', 'frites seulement.jpg'),
]

for test_img in test_images:
    if not os.path.exists(test_img):
        print(f"\n⚠ Image not found: {test_img}")
        continue
    
    print(f"\n{'='*80}")
    print(f"TEST: {os.path.basename(test_img)}")
    print(f"{'='*80}")
    
    # Step A: Similarity match (raw)
    print("\n--- A. find_match (raw) ---")
    sim_match = matcher.find_match(test_img)
    if sim_match:
        print(f"  Best category: {sim_match['category']} (conf: {sim_match['confidence']:.3f})")
        mapped_food = matcher.map_category_to_food(sim_match['category'])
        print(f"  Mapped to food: {mapped_food}")
        if 'all_sorted' in sim_match:
            print(f"  Top 5 categories:")
            for cat, conf in sim_match['all_sorted'][:5]:
                mapped = matcher.map_category_to_food(cat)
                print(f"    {cat} ({conf:.3f}) → {mapped}")
    else:
        print("  NO MATCH")
    
    # Step B: detect_multiple_foods
    print("\n--- B. detect_multiple_foods ---")
    multi = matcher.detect_multiple_foods(test_img)
    for i, r in enumerate(multi):
        mapped = matcher.map_category_to_food(r['category'])
        print(f"  [{i}] {r['category']} (conf: {r['confidence']:.3f}, src: {r['source']}) → mapped: {mapped}")
    
    # Step C: map_categories_to_foods
    print("\n--- C. map_categories_to_foods ---")
    mapped_foods = matcher.map_categories_to_foods(multi)
    for m in mapped_foods:
        cat_info = NUTRITION_DATA.get(m['food_key'], {}).get('categorie', '?')
        print(f"  {m['food_key']} (conf: {m['confidence']:.3f}, cat: {cat_info})")
    
    # Step D: Full detect_only
    print("\n--- D. detect_only (FULL PIPELINE) ---")
    result = analyzer.detect_only(test_img)
    print(f"  detected: {result.get('detected')}")
    if result.get('foods'):
        for f in result['foods']:
            print(f"  → {f.get('nom')} (conf: {f.get('confiance', 0):.3f}, cat: {f.get('categorie', '?')})")
    else:
        print(f"  message: {result.get('message', '')[:100]}")

print("\n" + "="*80)
print("DIAGNOSTIC TERMINÉ")
print("="*80)
