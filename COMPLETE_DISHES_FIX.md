# 🔧 Rebalancing - Allow Complete Dishes (Plats with Multiple Components)

## Issue Found
System was **rejecting legitimate secondary foods** in complete plates:
- Image: Steak + rice + white sauce + salad  
- Detected: ONLY "steak_hache" ❌
- Missing: Rice, salad, sauce

## Root Cause
Two issues combined:
1. **SECONDARY_THRESHOLD = 0.65** too high - rice/salad won't reach it
2. **Filtering logic** rejected ALL secondaries if primary >= 0.65

Code was treating every strong detection as "simple dish" (primary only)
But reality: steak at 0.80 is legitimate + rice at 0.58 is legitimate too!

---

## ✅ Fixes Applied

### 1. Lower SECONDARY_THRESHOLD
```diff
- SECONDARY_THRESHOLD = 0.65  # Too strict
+ SECONDARY_THRESHOLD = 0.55  # Accept rice/salad/sauce in complete dishes
```
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L42)

### 2. Increase MAX_SECONDARY_FOODS
```diff
- MAX_SECONDARY_FOODS = 1  # Only 1 secondary
+ MAX_SECONDARY_FOODS = 3  # Allow full plat: primary + 3 components
```
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L54)

### 3. Remove Restrictive Logic
```diff
# OLD (Wrong):
if main_primary >= 0.65:
    return [primary_only]  # ❌ Rejects rice/salad!

# NEW (Better):
return filtered_result[:1 + MAX_SECONDARY_FOODS]  # ✅ Returns up to 4 items
```
**File**: [`python/ml/full_nutrition_analyzer.py`](python/ml/full_nutrition_analyzer.py#L1394-L1396)

### 4. Filtering Logic
Secondaries are rejected ONLY if:
- Source = color_detection (unreliable)
- Confidence < 0.55 (too weak)
- Already have 3 secondaries (limit reached)

**NOT** rejected just because primary is strong! ✓

---

## Current Configuration

| Setting | Value | Purpose |
|---------|-------|---------|
| `CONFIDENCE_THRESHOLD` | 0.50 | Primary food minimum |
| `SECONDARY_THRESHOLD` | 0.55 | Secondary foods (lowered from 0.65) |
| `MAX_SECONDARY_FOODS` | 3 | Up to 3 components (was 1) |
| Composition Penalty | x0.50 | Still blocks mismatches (red pasta → bolognaise) |

---

## Expected Results Now

### Test: Steak + Rice + Salad + Sauce
```
Before: [{"nom": "steak_hache", ...}]  ❌ Missing sides

After: [
    {"nom": "steak_hache", "confiance": 0.80},     ✅ Primary
    {"nom": "riz_blanc", "confiance": 0.58},       ✅ Component
    {"nom": "salade_verte", "confiance": 0.62},    ✅ Component
    {"nom": "sauce_blanche", "confiance": 0.55}    ✅ Component
]
```

### Test: Simple Red Pasta (Should STILL reject "spaghetti bolognaise")
```
Before: [{"nom": "spaghetti_bolognaise"}, ...]  ❌ False positive
After:  [{"nom": "pates_simples", "confiance": 0.52}]  ✅ Correct
        (Composition penalty x0.50 still active)
```

### Test: Burger Alone
```
Before: [{"nom": "burger_classique"}, ...]
After:  [{"nom": "burger_classique"}, ...]
        (No change - burger is primary, no valid secondaries)
```

---

## Balance Achieved

| Scenario | Previous | Now |
|----------|----------|-----|
| **Complete dish** | Rejects sides ❌ | Accepts up to 3 ✅ |
| **Simple ingredient** | Over-detects ❌ | Composition penalty blocks ✓ |
| **False positives** | Some stop ✓ | Still blocked (x0.50 penalty) ✓ |
| **Real foods** | Some blocked ❌ | Lowered threshold accepts ✓ |

---

## Logic Flow

**For each detected food:**
1. Primary (rank 0):
   - Confirm: confiance >= 0.50 ✓
   - Return it

2. Secondary (rank 1-3):
   - Reject if: `source == 'color_detection'`
   - Reject if: `confiance < 0.55`  
   - Reject if: Already have 3 secondaries
   - Accept: Everything else (even if primary is strong!)

**Return**: 1 primary + up to 3 secondaries

---

## Testing Next Steps

1. **Your steak plate**: Should now detect steak + riz + salade + sauce
2. **Simple red pasta**: Should reject "spaghetti bolognaise" (composition penalty active)
3. **Burger alone**: Clean detection (no fake sides)
4. **Mixed vegetables**: Intelligent multi-detection

Test and let me know the results!
