#!/usr/bin/env python3
import sys
sys.path.insert(0, 'python/ml')

from strict_false_positive_filter import StrictFalsePositiveFilter

# Test the filter method
filter_strict = StrictFalsePositiveFilter()

# Test with 2-3 items (where our new keyword detection is used)
test_candidates = [
    {'name': 'poulet_grille', 'confidence': 0.74, 'source': 'cnn'},
    {'name': 'pancakes_classiques', 'confidence': 0.72, 'source': 'similarity'},
    {'name': 'wrap_poulet', 'confidence': 0.70, 'source': 'cnn'},
]

try:
    result = filter_strict.filter_detections(test_candidates)
    print(f"OK: Filter returned {len(result)} items")
except Exception as e:
    import traceback
    print(f"ERROR: {type(e).__name__}: {e}")
    traceback.print_exc()
