#!/usr/bin/env python3
"""
text_moderation_service.py  –  AI-driven multilingual text content moderation.

Called by PHP TextModerationService via proc_open.

Usage:
    python text_moderation_service.py check <text_file_path>

Input:  A UTF-8 text file containing the content to moderate.
Output: JSON to stdout:
    { "safe": true }
    or
    { "safe": false, "reason": "...", "categories": ["toxicity", ...], "confidence": 0.92 }

Supports: English, French, Arabic (and most Latin/Arabic-script languages).

Detection approach (AI-only, layered):
  1. Transformer-based toxicity classifier (multilingual)
  2. Zero-shot classification for nuanced categories
  3. Fallback heuristic safety net if models fail to load
"""

import sys
import json
import os
import re
import warnings

# Suppress noisy library output
os.environ['TRANSFORMERS_VERBOSITY'] = 'error'
os.environ['TOKENIZERS_PARALLELISM'] = 'false'
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
warnings.filterwarnings('ignore')

# ── Configuration ──────────────────────────────────────────────────────
TOXICITY_THRESHOLD = 0.55        # confidence above this = toxic
ZERO_SHOT_THRESHOLD = 0.60      # zero-shot label confidence
MAX_TEXT_LENGTH = 5000           # truncate very long inputs

# Categories we care about
MODERATION_CATEGORIES = [
    'toxicity', 'hate_speech', 'harassment', 'sexual_content',
    'violence', 'profanity', 'spam', 'threat'
]

ZERO_SHOT_LABELS = [
    'toxic content', 'hate speech', 'harassment', 'sexual content',
    'violent threat', 'profanity and insults', 'spam',
    'normal respectful message'
]

# ── Multilingual bad-word patterns (heuristic fallback) ────────────────
# These are used ONLY when ML models cannot load. They catch the most
# egregious terms across EN/FR/AR to provide a safety net.
HEURISTIC_PATTERNS_EN = [
    r'\bf+u+c+k+', r'\bs+h+i+t+\b', r'\ba+s+s+h+o+l+e', r'\bb+i+t+c+h',
    r'\bn+i+g+g', r'\bf+a+g+', r'\bc+u+n+t\b', r'\bd+i+c+k\b',
    r'\bwh+o+r+e', r'\bk+i+l+l\s+(you|u|him|her|them)',
    r'\br+a+p+e\b', r'\bsuicide\b.*\b(do|commit|try)',
]

HEURISTIC_PATTERNS_FR = [
    r'\bp+u+t+a+i+n', r'\bm+e+r+d+e', r'\be+n+c+u+l+[eé]',
    r'\bn+i+q+u+e', r'\bs+a+l+o+p+', r'\bc+o+n+n+a+r+d',
    r'\bb+â+t+a+r+d', r'\bp+[eé]+d+[eé]', r'\bt+a\s+g+u+e+u+l+e',
    r'\bj+e\s+v+a+i+s\s+t+e\s+t+u+e+r', r'\bf+i+l+s\s+d+e\s+p+u+t+e',
]

HEURISTIC_PATTERNS_AR = [
    r'كس\s*أم', r'ابن\s*(ال)?شرموط', r'يا?\s*حمار', r'يا?\s*كلب',
    r'عرص', r'شرموط', r'منيو?ك', r'زان[ىي]', r'لعن',
    r'يلعن\s*(أبو|دين)', r'كس\s*اخت', r'طيز',
    r'سأقتل', r'هقتل', r'نيك',
]

ALL_HEURISTIC_PATTERNS = (
    HEURISTIC_PATTERNS_EN + HEURISTIC_PATTERNS_FR + HEURISTIC_PATTERNS_AR
)


# ── Model configuration ───────────────────────────────────────────────
TOXICITY_MODEL = 'textdetox/xlmr-large-toxicity-classifier'
TOXICITY_MODEL_FALLBACK = 'unitary/toxic-bert'
ZERO_SHOT_MODEL = 'joeddav/xlm-roberta-large-xnli'


# ── Model loading (lazy, cached, NO auto-download) ───────────────────
_toxicity_pipeline = None
_zero_shot_pipeline = None
_models_available = None


def _try_load_pipeline(task, model_name, **kwargs):
    """Try to load a pipeline from local cache only (no download)."""
    from transformers import pipeline
    try:
        return pipeline(
            task,
            model=model_name,
            local_files_only=True,
            truncation=True,
            max_length=512,
            **kwargs,
        )
    except Exception:
        return None


def _load_toxicity_model():
    """Load multilingual toxicity classifier from cache."""
    global _toxicity_pipeline
    if _toxicity_pipeline is not None:
        return _toxicity_pipeline

    try:
        pipe = _try_load_pipeline('text-classification', TOXICITY_MODEL)
        if pipe is None:
            pipe = _try_load_pipeline('text-classification', TOXICITY_MODEL_FALLBACK)
        if pipe is None:
            sys.stderr.write('[text_moderation] No toxicity model cached. Run: python text_moderation_service.py download\n')
            _toxicity_pipeline = False
        else:
            _toxicity_pipeline = pipe
    except Exception as e:
        sys.stderr.write(f'[text_moderation] toxicity model load failed: {e}\n')
        _toxicity_pipeline = False
    return _toxicity_pipeline


def _load_zero_shot_model():
    """Load zero-shot classifier from cache."""
    global _zero_shot_pipeline
    if _zero_shot_pipeline is not None:
        return _zero_shot_pipeline

    try:
        pipe = _try_load_pipeline('zero-shot-classification', ZERO_SHOT_MODEL)
        if pipe is None:
            sys.stderr.write('[text_moderation] No zero-shot model cached. Run: python text_moderation_service.py download\n')
            _zero_shot_pipeline = False
        else:
            _zero_shot_pipeline = pipe
    except Exception as e:
        sys.stderr.write(f'[text_moderation] zero-shot model load failed: {e}\n')
        _zero_shot_pipeline = False
    return _zero_shot_pipeline


def _are_models_available():
    """Check if at least one ML model is usable."""
    global _models_available
    if _models_available is not None:
        return _models_available

    tox = _load_toxicity_model()
    _models_available = tox not in (None, False)
    return _models_available


# ── Core moderation logic ─────────────────────────────────────────────

def moderate_text(text: str) -> dict:
    """
    Analyze text for harmful/toxic content.

    Returns:
        dict with keys: safe (bool), reason (str), categories (list), confidence (float)
    """
    if not text or not text.strip():
        return {'safe': True}

    # Truncate to avoid memory issues
    text = text[:MAX_TEXT_LENGTH].strip()

    # Try AI-based moderation first
    if _are_models_available():
        return _ai_moderation(text)
    else:
        # Fallback to heuristic patterns
        sys.stderr.write('[text_moderation] ML models unavailable, using heuristic fallback\n')
        return _heuristic_moderation(text)


def _ai_moderation(text: str) -> dict:
    """Full AI pipeline: toxicity classifier + zero-shot categories."""
    detected_categories = []
    max_confidence = 0.0
    reasons = []

    # ── Step 1: Toxicity classifier ──
    tox = _load_toxicity_model()
    if tox and tox is not False:
        try:
            results = tox(text)
            if results:
                result = results[0] if isinstance(results, list) else results
                label = result.get('label', '').lower()
                score = result.get('score', 0.0)

                # The model returns 'toxic' or 'non-toxic' (or similar)
                is_toxic = ('toxic' in label or 'hate' in label or
                           'offensive' in label or 'obscene' in label)

                # If label is negative-class, invert the score
                if not is_toxic and score > 0.5:
                    # E.g., label='non-toxic' with score=0.95 means text is NOT toxic
                    pass
                elif is_toxic and score >= TOXICITY_THRESHOLD:
                    detected_categories.append('toxicity')
                    max_confidence = max(max_confidence, score)
                    reasons.append(f'Toxic content detected (confidence: {score:.0%})')
        except Exception as e:
            sys.stderr.write(f'[text_moderation] toxicity check error: {e}\n')

    # ── Step 2: Zero-shot classification for specific categories ──
    zs = _load_zero_shot_model()
    if zs and zs is not False:
        try:
            result = zs(text, ZERO_SHOT_LABELS, multi_label=True)
            labels = result.get('labels', [])
            scores = result.get('scores', [])

            for lbl, sc in zip(labels, scores):
                if lbl == 'normal respectful message':
                    continue
                if sc >= ZERO_SHOT_THRESHOLD:
                    cat = lbl.replace(' ', '_').replace('and_', '')
                    detected_categories.append(cat)
                    max_confidence = max(max_confidence, sc)
                    reasons.append(f'{lbl} (confidence: {sc:.0%})')
        except Exception as e:
            sys.stderr.write(f'[text_moderation] zero-shot check error: {e}\n')

    # ── Step 3: Heuristic boost – catch obvious bypasses ──
    heuristic_result = _heuristic_moderation(text)
    if not heuristic_result['safe']:
        for cat in heuristic_result.get('categories', []):
            if cat not in detected_categories:
                detected_categories.append(cat)
        max_confidence = max(max_confidence, heuristic_result.get('confidence', 0.8))
        reasons.extend(heuristic_result.get('_reasons', []))

    # ── Final verdict ──
    if detected_categories:
        unique_cats = list(dict.fromkeys(detected_categories))  # preserve order, remove dups
        return {
            'safe': False,
            'reason': '; '.join(reasons[:3]) if reasons else 'Inappropriate content detected.',
            'categories': unique_cats,
            'confidence': round(max_confidence, 3),
        }

    return {'safe': True}


def _heuristic_moderation(text: str) -> dict:
    """Pattern-based fallback when ML models are unavailable."""
    text_lower = text.lower()
    matched = []

    for pattern in ALL_HEURISTIC_PATTERNS:
        try:
            if re.search(pattern, text_lower, re.IGNORECASE | re.UNICODE):
                matched.append(pattern)
        except re.error:
            continue

    if matched:
        return {
            'safe': False,
            'reason': 'Content contains inappropriate language.',
            'categories': ['profanity'],
            'confidence': 0.85,
            '_reasons': ['Matched heuristic pattern'],
        }

    return {'safe': True}


# ── CLI entry point ───────────────────────────────────────────────────

def download_models():
    """Pre-download all required models (run once during setup)."""
    from transformers import pipeline
    print('[text_moderation] Downloading toxicity model...', flush=True)
    try:
        pipeline('text-classification', model=TOXICITY_MODEL, truncation=True, max_length=512)
        print(f'  ✓ {TOXICITY_MODEL}', flush=True)
    except Exception as e:
        print(f'  ✗ {TOXICITY_MODEL}: {e}', flush=True)
        print(f'  → Trying fallback: {TOXICITY_MODEL_FALLBACK}', flush=True)
        try:
            pipeline('text-classification', model=TOXICITY_MODEL_FALLBACK, truncation=True, max_length=512)
            print(f'  ✓ {TOXICITY_MODEL_FALLBACK}', flush=True)
        except Exception as e2:
            print(f'  ✗ {TOXICITY_MODEL_FALLBACK}: {e2}', flush=True)

    print('[text_moderation] Downloading zero-shot model...', flush=True)
    try:
        pipeline('zero-shot-classification', model=ZERO_SHOT_MODEL, truncation=True, max_length=512)
        print(f'  ✓ {ZERO_SHOT_MODEL}', flush=True)
    except Exception as e:
        print(f'  ✗ {ZERO_SHOT_MODEL}: {e}', flush=True)

    print('[text_moderation] Download complete.', flush=True)


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'Usage: text_moderation_service.py <check|download> [text_file_path]'}))
        sys.exit(1)

    command = sys.argv[1]

    if command == 'download':
        download_models()
        return

    if command != 'check' or len(sys.argv) < 3:
        print(json.dumps({'error': 'Usage: text_moderation_service.py check <text_file_path>'}))
        sys.exit(1)

    text_file_path = sys.argv[2]

    if not os.path.isfile(text_file_path):
        print(json.dumps({'error': f'File not found: {text_file_path}'}))
        sys.exit(1)

    try:
        with open(text_file_path, 'r', encoding='utf-8') as f:
            text = f.read()
    except Exception as e:
        print(json.dumps({'error': f'Failed to read file: {str(e)}'}))
        sys.exit(1)

    try:
        result = moderate_text(text)
        print(json.dumps(result, ensure_ascii=False))
    except Exception as e:
        sys.stderr.write(f'[text_moderation] unexpected error: {e}\n')
        print(json.dumps({
            'safe': True,
            'warning': 'moderation_error',
        }))


if __name__ == '__main__':
    main()
