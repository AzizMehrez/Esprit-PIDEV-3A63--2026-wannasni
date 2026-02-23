#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Image Content Moderation Service for WANNASNI
Detects: nudity/NSFW, violence/gore/blood/dead bodies, guns/weapons,
         political extremism, drugs.

Usage:
    python image_moderation_service.py check <image_path>

Output: JSON to stdout
    {"safe": true}
    {"safe": false, "reason": "...", "categories": ["weapons"], "confidence": 0.87}

Dependencies:
    pip install torch torchvision transformers pillow accelerate
"""

import sys
import json
import os

# Windows: force UTF-8 stdout so PHP proc_open reads correctly
if os.name == 'nt':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

os.environ['TRANSFORMERS_VERBOSITY']           = 'error'
os.environ['HF_HUB_DISABLE_PROGRESS_BARS']     = '1'
os.environ['TF_CPP_MIN_LOG_LEVEL']             = '3'
os.environ['TOKENIZERS_PARALLELISM']           = 'false'
os.environ['HF_HUB_DISABLE_SYMLINKS_WARNING']  = '1'


# ─────────────────────────────────────────────────────────────────────────────
#  ARCHITECTURE
#
#  Check 1 – Falconsai/nsfw_image_detection (dedicated binary NSFW classifier)
#             Very accurate for nudity/adult content.  Threshold: 0.60.
#
#  Check 2 – CLIP binary classification per-category.
#             Each category uses TWO carefully chosen labels:
#               • a HARMFUL label   (describes what we want to detect)
#               • a SAFE label      (category-specific contrast, not generic)
#             CLIP scores the image between these two; we flag if the harmful
#             label wins by >= threshold.
#
#             Using a category-specific safe label instead of one global
#             "normal photograph" label gives much sharper, more reliable
#             separation between harmful and normal content.
# ─────────────────────────────────────────────────────────────────────────────

# ── Per-category binary CLIP config ──────────────────────────────────────────
#   harmful_label : what the image would look like if it violates the rule
#   safe_label    : what the same scene would look like if it is fine
#   threshold     : harmful_score >= threshold → flag  (0-1 softmax)
# ─────────────────────────────────────────────────────────────────────────────
CLIP_CATEGORIES = [
    {
        "id":           "nsfw",
        "description":  "explicit / adult content (nudity)",
        # Specific safe label reduces false positives on beach/gym/medical photos
        "harmful":   "explicit nudity or pornographic sexual content",
        "safe":      "a fully clothed person posing for a normal photo",
        "threshold": 0.85,   # high bar — Falconsai already handles lower-score NSFW
    },
    {
        "id":          "violence",
        "description": "violent or graphic content (blood / gore / dead bodies)",
        "harmful":   "a graphic bloody wound, a dead body, or extreme gore",
        "safe":      "a normal healthy person smiling in a calm everyday setting",
        "threshold": 0.80,
    },
    {
        "id":          "weapons",
        "description": "dangerous weapons (firearms, assault rifles)",
        "harmful":   "a person aiming a loaded firearm or assault rifle at someone",
        "safe":      "a person with empty hands posing for a normal portrait photo",
        "threshold": 0.80,
    },
    {
        "id":          "political",
        "description": "political extremism or hate symbols",
        "harmful":   "a Nazi swastika or white supremacist hate symbol displayed prominently",
        "safe":      "a normal person or group in an everyday photograph",
        "threshold": 0.82,
    },
    {
        "id":          "drugs",
        "description": "illegal drug content",
        "harmful":   "lines of cocaine, heroin with a syringe, or illegal drug use",
        "safe":      "a normal person in an everyday setting with no drugs or paraphernalia",
        "threshold": 0.88,
    },
]

# Falconsai dedicated NSFW classifier threshold.
# 0.70 catches genuine NSFW; normal photos (portraits, gym, beach) typically
# score 0.25–0.55 so there is comfortable separation.  Raised from 0.60 to
# reduce false positives on everyday human photos.
NSFW_MODEL_THRESHOLD = 0.70

# Category description map for PHP message formatting
CATEGORY_DESCRIPTIONS = {c["id"]: c["description"] for c in CLIP_CATEGORIES}


def _load_image(image_path: str):
    from PIL import Image
    try:
        return Image.open(image_path).convert("RGB")
    except Exception as exc:
        raise RuntimeError(f"Cannot open image: {exc}") from exc


# ─── Check 1: Falconsai dedicated NSFW classifier ─────────────────────────────
def _check_nsfw_model(img) -> tuple:
    """
    Returns (flagged_list, ran_successfully).

    - flagged_list : [("nsfw", score, description)] when NSFW detected, else []
    - ran_successfully : True  → Falconsai ran and gave a verdict (safe OR unsafe)
                         False → model load / inference failed (CLIP will act as fallback)

    IMPORTANT: when ran_successfully is True the caller should treat the nsfw
    category as already handled — even if the image is safe — so that CLIP's
    zero-shot nsfw probe (which often over-fires on normal human photos) is
    never used when Falconsai is available.
    """
    try:
        from transformers import pipeline
        pipe = pipeline(
            "image-classification",
            model="Falconsai/nsfw_image_detection",
            device=-1,
        )
        for r in pipe(img):
            if r["label"] == "nsfw" and r["score"] >= NSFW_MODEL_THRESHOLD:
                return [("nsfw", float(r["score"]), CATEGORY_DESCRIPTIONS["nsfw"])], True
        # Falconsai ran and says the image is safe
        return [], True
    except Exception:
        pass
    # Model failed to load — let CLIP handle nsfw as fallback
    return [], False


# ─── Check 2: CLIP binary classification per category ─────────────────────────
#
#  For each category we run a single CLEAN binary CLIP call:
#    candidate_labels = [harmful_label, safe_label]
#
#  The score returned for the harmful label is the probability that the image
#  matches the harmful description over the safe one.  This is far more stable
#  than multi-probe softmax (where safe dilutes to ~1/N when many labels exist).
#
#  We skip the nsfw category if Falconsai already evaluated it (flagged OR safe).
#  CLIP's zero-shot nsfw probe is only a fallback for when Falconsai fails to load.
def _check_clip(img, already_flagged: set) -> list:
    classifier = None
    for model_id in ["openai/clip-vit-large-patch14", "openai/clip-vit-base-patch32"]:
        try:
            from transformers import pipeline
            classifier = pipeline(
                "zero-shot-image-classification",
                model=model_id,
                device=-1,
            )
            break
        except Exception:
            continue

    if classifier is None:
        return []

    flagged = []
    for cat in CLIP_CATEGORIES:
        cat_id    = cat["id"]
        threshold = cat["threshold"]

        # Skip categories already caught by a dedicated model
        if cat_id in already_flagged:
            continue

        try:
            outputs = classifier(
                img,
                candidate_labels=[cat["harmful"], cat["safe"]],
            )
        except Exception:
            continue

        scores = {item["label"]: item["score"] for item in outputs}
        harmful_score = scores.get(cat["harmful"], 0.0)

        if harmful_score >= threshold:
            flagged.append((cat_id, float(harmful_score), cat["description"]))

    return flagged


# ─── Main entry ───────────────────────────────────────────────────────────────
def check_image(image_path: str) -> dict:
    try:
        img = _load_image(image_path)
    except RuntimeError as exc:
        return {"safe": False, "reason": str(exc), "categories": ["invalid_image"]}

    # Run Falconsai first — fast dedicated NSFW model
    nsfw_raw, falconsai_ran = _check_nsfw_model(img)
    nsfw_cats = {cat for cat, _, _ in nsfw_raw}

    # If Falconsai ran successfully (even with a "safe" verdict) treat "nsfw" as
    # already evaluated so CLIP does NOT run its own nsfw probe.  CLIP's zero-shot
    # nsfw label frequently over-fires on normal human photos (selfies, portraits)
    # and should only serve as a fallback when Falconsai is unavailable.
    if falconsai_ran:
        nsfw_cats.add("nsfw")

    # Run CLIP for remaining categories only
    clip_raw = _check_clip(img, nsfw_cats)

    raw = nsfw_raw + clip_raw

    # Deduplicate — keep highest score per category
    best: dict = {}
    for cat, score, desc in raw:
        if cat not in best or score > best[cat][0]:
            best[cat] = (score, desc)

    if not best:
        return {"safe": True}

    ranked = sorted(best.items(), key=lambda x: x[1][0], reverse=True)
    top_cat, (top_score, top_desc) = ranked[0]

    return {
        "safe":       False,
        "reason":     f"⚠️ This image contains {top_desc} and cannot be posted. Please share only appropriate, respectful content.",
        "categories": [cat for cat, _ in ranked],
        "confidence": round(top_score, 3),
    }


def main():
    if len(sys.argv) < 3:
        print(json.dumps({"safe": False, "reason": "Usage: image_moderation_service.py check <image_path>"}, ensure_ascii=False))
        sys.exit(1)

    command    = sys.argv[1]
    image_path = sys.argv[2]

    if command != "check":
        print(json.dumps({"safe": False, "reason": f"Unknown command: {command}"}, ensure_ascii=False))
        sys.exit(1)

    if not os.path.exists(image_path):
        print(json.dumps({"safe": False, "reason": f"File not found: {image_path}", "categories": ["not_found"]}, ensure_ascii=False))
        sys.exit(1)

    print(json.dumps(check_image(image_path), ensure_ascii=False))


if __name__ == "__main__":
    main()
