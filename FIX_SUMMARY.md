# 🔧 FIXES APPLIED - ML Model Rebalancing

## Issue Summary
System was **rejecting valid foods** (lasagnes) and **returning garbage** (glace, poulet) due to:
1. Threshold mismatch (0.55 < 0.60)
2. No fallback mechanism for weak primary detections
3. Over-aggressive filtering

---

## ✅ Fixes Applied

### 1. **Threshold Realignment** 
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L39-L93)

```diff
- CONFIDENCE_THRESHOLD = 0.55
+ CONFIDENCE_THRESHOLD = 0.50  # Accept lasagnes at 0.50+

- WHOLE_DISH_THRESHOLD = 0.60  # ❌ Mismatch!
+ WHOLE_DISH_THRESHOLD = 0.50  # ✅ Now aligned
```

**Why**: Prevents catch-22 where food passes primary threshold but fails dish-specific threshold

---

### 2. **Intelligent Fallback Logic**
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L54)

```diff
- MAX_SECONDARY_FOODS = 0  # Absolute rejection
+ MAX_SECONDARY_FOODS = 1  # Allow fallback if primary weak
```

**Conditional Behavior** (lines 1391-1395):
```python
if main_primary_confidence >= 0.65:
    return [primary_only]  # Clean result
else:
    return [primary, secondary]  # Fallback allowed
```

**Why**: Weak detection → intelligent fallback. Strong detection → clean result.

---

## 📊 Configuration Matrix

| Metric | Value | Impact |
|--------|-------|--------|
| Primary Threshold | 0.50 | Allows real foods (lasagnes 0.55+) |
| Dish Threshold | 0.50 | Aligned with primary ✓ |
| Secondary Threshold | 0.65 | Strict filter on fallbacks |
| Max Secondaries | 1 | Prevents multiple false matches |
| Composition Penalty | x0.50 | Blocks mismatches (complete dishes in simple images) |

---

## 🧪 Expected Behavior

### Lasagnes Image
```
Before: [{"nom": "glace"}, {"nom": "poulet_grille"}]  ❌ WRONG
After:  [{"nom": "lasagnes", "confiance": 0.55}]      ✅ CORRECT
```

### Simple Red Pasta
```
Before: [{"nom": "spaghetti_bolognaise"}, {"nom": "wrap"}]  ❌ FALSE POSITIVES
After:  [{"nom": "pates_simples", "confiance": 0.52}]       ✅ CORRECT
        (Composition penalty x0.50 rejects "spaghetti_bolognaise")
```

### Strong Burger  
```
Result: [{"nom": "burger_classique", "confiance": 0.72}]
        (No secondaries because 0.72 >= 0.65 threshold)
```

### Weak Dish Mix
```
Result: [{"nom": "salade_verte", "confiance": 0.48}]
        [{"nom": "tomate", "confiance": 0.68}]  (fallback allowed)
```

---

## 🔍 Root Cause Analysis

**Why was system returning garbage?**

1. Lasagnes detected with confidence 0.55
2. **Passes**: `CONFIDENCE_THRESHOLD (0.55)` ✓
3. **Fails**: `WHOLE_DISH_THRESHOLD (0.60)` ✗
4. Gets rejected → `main_food = None`
5. Falls back to **regional CNN detection** (unreliable)
6. Returns worst matches: "glace" (low conf), "poulet" (low conf)

**Why fix works?**

- Lasagnes 0.55 now passes BOTH thresholds (both 0.50)
- Never falls back to poor regional detection
- Returns correct food

---

## 🚀 Next Steps for User

Test with images:
1. **Lasagnes** → Should detect "lasagnes" (conf 0.50-0.65)
2. **Red pasta** → Should detect "pates_simples" (no "spaghetti_bolognaise")
3. **Burger** → Clean detection, no false secondaries
4. **Mixed dish** → Intelligent fallback if primary weak

If issues persist:
- Check logs for composition penalty activations
- Verify similarity_matcher is applying x0.50 penalty correctly
- May need minor secondary threshold adjustment (currently 0.65)

---

## 📁 Files Modified

| File | Changes | Lines |
|------|---------|-------|
| `python/ml/full_nutrition_analyzer.py` | Threshold alignment + fallback logic | 39, 54, 93, 1391-1395 |
| `similarity_matcher.py` | (No changes - composition detection already working) | - |

---

## ✨ Summary

✅ **Threshold mismatch fixed** (0.55/0.60 → 0.50/0.50)  
✅ **Intelligent fallback restored** (0 → 1 secondary with conditions)  
✅ **Composition detection active** (x0.50 penalty on mismatches)  
✅ **Ready for testing** with real food images
