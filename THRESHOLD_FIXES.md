# Threshold Rebalancing - Fix for False Rejections

## Problem Identified
System was rejecting valid foods like lasagnes and returning completely wrong foods (glace, poulet).

**Root Cause**: Threshold mismatch between CONFIDENCE_THRESHOLD (0.55) and WHOLE_DISH_THRESHOLD (0.60)
- Food passes CONFIDENCE_THRESHOLD (0.55)
- But fails WHOLE_DISH_THRESHOLD (0.60)
- Gets rejected anyway → falls back to poor regional detection

## Solutions Applied

### 1. Lower CONFIDENCE_THRESHOLD (0.55 → 0.50)
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L39)
- Allows real dishes with confidence 0.50-0.55 to pass through
- Still rejects obvious non-food images
- Sensitivity now matches composition detection's precision

### 2. Align WHOLE_DISH_THRESHOLD with CONFIDENCE_THRESHOLD
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L93)
- **Before**: CONFIDENCE_THRESHOLD=0.55, WHOLE_DISH_THRESHOLD=0.60 (mismatch!)
- **After**: Both = 0.50 (aligned)
- Prevents catch-22 rejection where food passes primary threshold but fails dish threshold

### 3. Restore Intelligent Fallback Logic
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L54)
- **Before**: MAX_SECONDARY_FOODS = 0 (absolute rejection)
- **After**: MAX_SECONDARY_FOODS = 1 (with conditions)

**Conditional Logic** (lines 1391-1395):
```python
# If main food is strong (>= 0.65): return ONLY primary
# If main food is weak (< 0.65): allow 1 secondary as fallback
```

Benefits:
- Strong detection = clean result (no false secondaries)
- Weak detection = intelligent fallback to secondary matches
- No garbage results like "glace + poulet" on lasagnes

## Current Configuration

| Setting | Value | Purpose |
|---------|-------|---------|
| `CONFIDENCE_THRESHOLD` | 0.50 | Primary food detection minimum |
| `WHOLE_DISH_THRESHOLD` | 0.50 | Whole dish detection (aligned) |
| `SECONDARY_THRESHOLD` | 0.65 | Secondary foods (strict filter) |
| `MAX_SECONDARY_FOODS` | 1 | Max 1 secondary if primary weak |
| Composition Penalty | x0.50 | Reject mismatches (spaghetti bolognaise in simple image) |

## Expected Results

### Test Case: Lasagnes Image
- **Before Fix**: Returns "glace" + "poulet grille" (wrong)
- **After Fix**: Returns "lasagnes" or "lasagnes_legumes" with conf 0.50-0.65
- **Mechanism**: Composition detection prevents false "complete dish" matches

### Test Case: Simple Red Pasta
- **Before Initial Fix**: Returns "spaghetti bolognaise" + "wrap poulet" (false positives)
- **After Fix**: Returns only "pates_simples" or similar
**Mechanism**: Composition penalty (x0.50) on complete dish match = rejected

### Test Case: Strong Burger Image
- **Behavior**: Returns ONLY "burger_classique" (no secondaries)
- **Reason**: High confidence (0.70+) >= 0.65 threshold

### Test Case: Weak Vegetable Mix
- **Behavior**: Returns "salade_verte" + 1 secondary like "tomate"
- **Reason**: Low primary confidence (0.45-0.55) triggers fallback

## Key Improvements Over Previous Versions

| Version | CONF_THR | WHOLE_DISH | MAX_SEC | Issues |
|---------|----------|-----------|---------|--------|
| Initial | 0.45 | - | N/A | Too permissive, spaghetti+wrap false positives |
| Phase 3 (Overcorrect) | 0.60 | 0.72 | 0 | TOO STRICT, rejects lasagnes, returns garbage |
| Current (Balanced) | 0.50 | 0.50 | 1 | ✅ Coherent thresholds + intelligent fallback |

## Testing Next Steps

1. Test with lasagnes image - should detect it properly
2. Test with simple red pasta - should NOT detect "spaghetti bolognaise"
3. Test with burger - should return clean single detection
4. Monitor logs for composition penalty activations

## Files Modified
- [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py) - Lines 39, 54, 93, 1391-1395
- No changes needed to similarity_matcher.py (composition detection already working)
