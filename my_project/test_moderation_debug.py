#!/usr/bin/env python3
"""
Diagnostic script: tests the image moderation on a real image and prints
ALL raw scores so we can calibrate thresholds correctly.
"""
import sys, os, json

os.environ['TRANSFORMERS_VERBOSITY']           = 'error'
os.environ['HF_HUB_DISABLE_PROGRESS_BARS']     = '1'
os.environ['TF_CPP_MIN_LOG_LEVEL']             = '3'
os.environ['TOKENIZERS_PARALLELISM']           = 'false'
os.environ['HF_HUB_DISABLE_SYMLINKS_WARNING']  = '1'

from PIL import Image

# Pick a test image – use a known-good networking upload or profile pic
TEST_IMAGES = [
    "public/uploads/networking/net_6994e21abc3c7.png",
    "public/images/profiles/6994f5ed9121a.png",
    "public/images/care.png",
]

# Filter to only images that exist
base = os.path.dirname(os.path.abspath(__file__))
existing = []
for p in TEST_IMAGES:
    full = os.path.join(base, p)
    if os.path.exists(full):
        existing.append(full)
if not existing:
    print("No test images found! Please provide a path as argument.")
    sys.exit(1)

# Allow override via command line
if len(sys.argv) > 1 and os.path.exists(sys.argv[1]):
    existing = [sys.argv[1]]

print(f"Testing {len(existing)} image(s)...\n")

for img_path in existing:
    print(f"{'='*70}")
    print(f"IMAGE: {os.path.basename(img_path)}")
    print(f"{'='*70}")
    
    img = Image.open(img_path).convert("RGB")
    
    # ── Test 1: Falconsai NSFW model ──────────────────────────────────
    print("\n--- Falconsai NSFW Model ---")
    try:
        from transformers import pipeline
        pipe = pipeline("image-classification", model="Falconsai/nsfw_image_detection", device=-1)
        results = pipe(img)
        for r in results:
            marker = " <<<" if r["label"] == "nsfw" else ""
            print(f"  {r['label']:>10s}: {r['score']:.4f}{marker}")
    except Exception as e:
        print(f"  ERROR: {e}")
    
    # ── Test 2: CLIP binary per category ──────────────────────────────
    print("\n--- CLIP Binary Classification ---")
    try:
        classifier = None
        for model_id in ["openai/clip-vit-large-patch14", "openai/clip-vit-base-patch32"]:
            try:
                classifier = pipeline("zero-shot-image-classification", model=model_id, device=-1)
                print(f"  Using model: {model_id}")
                break
            except:
                continue
        
        if classifier is None:
            print("  ERROR: No CLIP model available")
        else:
            categories = [
                ("nsfw",
                 "explicit nudity or pornographic sexual content",
                 "a fully clothed person posing for a normal photo"),
                ("violence",
                 "a graphic bloody wound, a dead body, or extreme gore",
                 "a normal healthy person smiling in a calm everyday setting"),
                ("weapons",
                 "a person aiming a loaded firearm or assault rifle at someone",
                 "a person with empty hands posing for a normal portrait photo"),
                ("political",
                 "a Nazi swastika or white supremacist hate symbol displayed prominently",
                 "a normal person or group in an everyday photograph"),
                ("drugs",
                 "lines of cocaine, heroin with a syringe, or illegal drug use",
                 "a normal person in an everyday setting with no drugs or paraphernalia"),
            ]
            
            for cat_id, harmful, safe in categories:
                outputs = classifier(img, candidate_labels=[harmful, safe])
                scores = {item["label"]: item["score"] for item in outputs}
                h_score = scores.get(harmful, 0)
                s_score = scores.get(safe, 0)
                verdict = "FLAGGED" if h_score > s_score else "safe"
                print(f"\n  [{cat_id}]")
                print(f"    harmful: {h_score:.4f}  |  safe: {s_score:.4f}  →  {verdict}")
    except Exception as e:
        print(f"  ERROR: {e}")
    
    # ── Test 3: Run the actual check_image function ───────────────────
    print(f"\n--- Final check_image() result ---")
    try:
        from image_moderation_service import check_image
        result = check_image(img_path)
        print(f"  {json.dumps(result, indent=2, ensure_ascii=False)}")
    except Exception as e:
        print(f"  ERROR: {e}")
    
    print()
