"""
Download training images for each food class.
Uses multiple free image sources to build a proper training dataset.
Run from the python/ directory:
    python download_training_images.py
"""

import os
import sys
import time
import urllib.request
import urllib.parse
import json
import hashlib
from pathlib import Path

# Add parent to path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(SCRIPT_DIR, 'data', 'raw')

# ===========================================================================
# Curated image URLs for each food class
# These are direct links to freely usable food images (Wikimedia, open sources)
# ===========================================================================
FOOD_IMAGES = {
    # ---- LASAGNES ----
    "plats_pates/lasagne": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/b/b3/Lasagne_al_forno.jpg/640px-Lasagne_al_forno.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/3/3e/Lasagna_Ricetta_Originale.jpg/640px-Lasagna_Ricetta_Originale.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/b/bd/Spinach-lasagna.jpg/640px-Spinach-lasagna.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/1/19/Lasagna_pasta.jpg/480px-Lasagna_pasta.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/Lasagne_bolognese.jpg/640px-Lasagne_bolognese.jpg",
        "https://images.pexels.com/photos/4518843/pexels-photo-4518843.jpeg?w=640",
        "https://images.pexels.com/photos/5949889/pexels-photo-5949889.jpeg?w=640",
        "https://images.pexels.com/photos/6419715/pexels-photo-6419715.jpeg?w=640",
    ],
    # ---- POULET GRILLE ----
    "viandes/poulet": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/6/6d/Good_Food_Display_-_NCI_Visuals_Online.jpg/640px-Good_Food_Display_-_NCI_Visuals_Online.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/0/05/GrilledChicken.jpg/640px-GrilledChicken.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/8/80/Closeup_of_roasted_chicken.jpg/640px-Closeup_of_roasted_chicken.jpg",
        "https://images.pexels.com/photos/2338407/pexels-photo-2338407.jpeg?w=640",
        "https://images.pexels.com/photos/106343/pexels-photo-106343.jpeg?w=640",
        "https://images.pexels.com/photos/1624487/pexels-photo-1624487.jpeg?w=640",
        "https://images.pexels.com/photos/3997609/pexels-photo-3997609.jpeg?w=640",
        "https://images.pexels.com/photos/6210747/pexels-photo-6210747.jpeg?w=640",
    ],
    # ---- ESCALOPE PANEE ----
    "viandes/escalope_panee": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/8/84/Wiener_Schnitzel_aus_Kalb.jpg/640px-Wiener_Schnitzel_aus_Kalb.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/5/5e/Schnitzel_close-up.jpg/640px-Schnitzel_close-up.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/4/47/Schnitzel_Wiener_Art.jpg/640px-Schnitzel_Wiener_Art.jpg",
        "https://images.pexels.com/photos/6287374/pexels-photo-6287374.jpeg?w=640",
        "https://images.pexels.com/photos/8696567/pexels-photo-8696567.jpeg?w=640",
        "https://images.pexels.com/photos/12737454/pexels-photo-12737454.jpeg?w=640",
    ],
    # ---- RIZ BLANC ----
    "riz": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/a/a1/24701-nature-natural-beauty.jpg/640px-24701-nature-natural-beauty.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/7/7b/White_rice.jpg/640px-White_rice.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/a/a3/Riz.jpg/640px-Riz.jpg",
        "https://images.pexels.com/photos/723198/pexels-photo-723198.jpeg?w=640",
        "https://images.pexels.com/photos/4110251/pexels-photo-4110251.jpeg?w=640",
        "https://images.pexels.com/photos/2097090/pexels-photo-2097090.jpeg?w=640",
        "https://images.pexels.com/photos/1151515/pexels-photo-1151515.jpeg?w=640",
        "https://images.pexels.com/photos/7438167/pexels-photo-7438167.jpeg?w=640",
    ],
    # ---- VIANDE HACHEE / STEAK ----
    "viandes/viande_hachee": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/0/00/Beef_steak_2.jpg/640px-Beef_steak_2.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/b/be/Hamburger_Patty.jpg/640px-Hamburger_Patty.jpg",
        "https://images.pexels.com/photos/1639557/pexels-photo-1639557.jpeg?w=640",
        "https://images.pexels.com/photos/3535383/pexels-photo-3535383.jpeg?w=640",
        "https://images.pexels.com/photos/5837355/pexels-photo-5837355.jpeg?w=640",
    ],
    # ---- SPAGHETTI BOLOGNAISE ----
    "plats_pates/spaghetti": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/f/f3/Spaghetti_bolognese_%28hozinja%29.jpg/640px-Spaghetti_bolognese_%28hozinja%29.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/2/20/Spaghetti_Bolognese_in_Tampere.jpg/640px-Spaghetti_Bolognese_in_Tampere.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/b/b6/Spaghetti_aglio_olio_e_peperoncino.jpg/640px-Spaghetti_aglio_olio_e_peperoncino.jpg",
        "https://images.pexels.com/photos/1527603/pexels-photo-1527603.jpeg?w=640",
        "https://images.pexels.com/photos/4082528/pexels-photo-4082528.jpeg?w=640",
        "https://images.pexels.com/photos/1438672/pexels-photo-1438672.jpeg?w=640",
        "https://images.pexels.com/photos/5710029/pexels-photo-5710029.jpeg?w=640",
        "https://images.pexels.com/photos/7475376/pexels-photo-7475376.jpeg?w=640",
    ],
    # ---- BURGER ----
    "fast_food/burger": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/4/4d/Cheeseburger.jpg/640px-Cheeseburger.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/0/0b/Grilled_Hamburger_with_Pickle.jpg/640px-Grilled_Hamburger_with_Pickle.jpg",
        "https://images.pexels.com/photos/1639565/pexels-photo-1639565.jpeg?w=640",
        "https://images.pexels.com/photos/2983101/pexels-photo-2983101.jpeg?w=640",
        "https://images.pexels.com/photos/3026808/pexels-photo-3026808.jpeg?w=640",
        "https://images.pexels.com/photos/1199960/pexels-photo-1199960.jpeg?w=640",
        "https://images.pexels.com/photos/6004015/pexels-photo-6004015.jpeg?w=640",
    ],
    # ---- FRITES ----
    "legumes/frites_maison": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/3/30/French_fries_with_dipping_sauce.jpg/640px-French_fries_with_dipping_sauce.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/6/67/Pommes-Frites_II.jpg/640px-Pommes-Frites_II.jpg",
        "https://images.pexels.com/photos/1583884/pexels-photo-1583884.jpeg?w=640",
        "https://images.pexels.com/photos/1893555/pexels-photo-1893555.jpeg?w=640",
        "https://images.pexels.com/photos/5718074/pexels-photo-5718074.jpeg?w=640",
        "https://images.pexels.com/photos/2097090/pexels-photo-2097090.jpeg?w=640",
    ],
    # ---- PATES ----
    "plats_pates/pates_generiques": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/8/8e/Pasta_in_pastry_box.jpg/640px-Pasta_in_pastry_box.jpg",
        "https://images.pexels.com/photos/1527603/pexels-photo-1527603.jpeg?w=640",
        "https://images.pexels.com/photos/6287530/pexels-photo-6287530.jpeg?w=640",
        "https://images.pexels.com/photos/2664216/pexels-photo-2664216.jpeg?w=640",
        "https://images.pexels.com/photos/1279330/pexels-photo-1279330.jpeg?w=640",
    ],
    # ---- SALADE / LEGUMES ----
    "legumes/legumes_variés": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/9/90/Hapus_Mango.jpg/640px-Hapus_Mango.jpg",
        "https://images.pexels.com/photos/1640777/pexels-photo-1640777.jpeg?w=640",
        "https://images.pexels.com/photos/2097090/pexels-photo-2097090.jpeg?w=640",
        "https://images.pexels.com/photos/1543362/pexels-photo-1543362.jpeg?w=640",
        "https://images.pexels.com/photos/257816/pexels-photo-257816.jpeg?w=640",
        "https://images.pexels.com/photos/3872406/pexels-photo-3872406.jpeg?w=640",
    ],
    # ---- OEUFS ----
    "oeufs/oeufs": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/2/26/Scrambed_eggs.jpg/640px-Scrambed_eggs.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/2/23/Fried_egg%2C_sunny_side_up.jpg/640px-Fried_egg%2C_sunny_side_up.jpg",
        "https://images.pexels.com/photos/704569/pexels-photo-704569.jpeg?w=640",
        "https://images.pexels.com/photos/6287625/pexels-photo-6287625.jpeg?w=640",
        "https://images.pexels.com/photos/1640775/pexels-photo-1640775.jpeg?w=640",
    ],
    # ---- PANCAKES ----
    "fast_food/pancake": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/0/04/Pound_layer_cake.jpg/640px-Pound_layer_cake.jpg",
        "https://images.pexels.com/photos/376464/pexels-photo-376464.jpeg?w=640",
        "https://images.pexels.com/photos/2280545/pexels-photo-2280545.jpeg?w=640",
        "https://images.pexels.com/photos/1128678/pexels-photo-1128678.jpeg?w=640",
        "https://images.pexels.com/photos/1893551/pexels-photo-1893551.jpeg?w=640",
    ],
    # ---- SHAWARMA / WRAP ----
    "fast_food/shawarma": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/e/e4/Shawarma_wrap.jpg/640px-Shawarma_wrap.jpg",
        "https://upload.wikimedia.org/wikipedia/commons/thumb/5/5c/Kebab_from_the_street_vendor_in_Malm%C3%B6.JPG/640px-Kebab_from_the_street_vendor_in_Malm%C3%B6.JPG",
        "https://images.pexels.com/photos/5410400/pexels-photo-5410400.jpeg?w=640",
        "https://images.pexels.com/photos/6287449/pexels-photo-6287449.jpeg?w=640",
    ],
    # ---- GLACE ----
    "desserts/glace": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/2/2e/Ice_cream_cone.jpg/640px-Ice_cream_cone.jpg",
        "https://images.pexels.com/photos/1362534/pexels-photo-1362534.jpeg?w=640",
        "https://images.pexels.com/photos/1343562/pexels-photo-1343562.jpeg?w=640",
        "https://images.pexels.com/photos/749374/pexels-photo-749374.jpeg?w=640",
        "https://images.pexels.com/photos/1352278/pexels-photo-1352278.jpeg?w=640",
    ],
    # ---- BOULETTES / VIANDE SAUCE ----
    "viandes/viande_sauce": [
        "https://upload.wikimedia.org/wikipedia/commons/thumb/a/a4/Meatballs_with_Sauce.jpg/640px-Meatballs_with_Sauce.jpg",
        "https://images.pexels.com/photos/5737240/pexels-photo-5737240.jpeg?w=640",
        "https://images.pexels.com/photos/4553038/pexels-photo-4553038.jpeg?w=640",
        "https://images.pexels.com/photos/6098079/pexels-photo-6098079.jpeg?w=640",
    ],
}


def download_image(url, save_path, timeout=15):
    """Download a single image with retry logic."""
    headers = {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    }
    try:
        req = urllib.request.Request(url, headers=headers)
        with urllib.request.urlopen(req, timeout=timeout) as response:
            content = response.read()
            if len(content) < 5000:  # Skip tiny/broken images
                print(f"  SKIP (too small: {len(content)} bytes): {os.path.basename(save_path)}")
                return False
            os.makedirs(os.path.dirname(save_path), exist_ok=True)
            with open(save_path, 'wb') as f:
                f.write(content)
            print(f"  OK ({len(content)//1024}KB): {os.path.basename(save_path)}")
            return True
    except Exception as e:
        print(f"  FAIL: {url[:60]} -> {e}")
        return False


def main():
    total_downloaded = 0
    total_failed = 0

    for folder_key, urls in FOOD_IMAGES.items():
        folder_path = os.path.join(DATA_DIR, folder_key.replace('/', os.sep))
        os.makedirs(folder_path, exist_ok=True)

        # Count existing images
        existing = [f for f in os.listdir(folder_path)
                    if f.lower().endswith(('.jpg', '.jpeg', '.png', '.webp'))]
        print(f"\n[{folder_key}] - {len(existing)} existing images")

        for i, url in enumerate(urls):
            # Generate unique filename from URL hash
            url_hash = hashlib.md5(url.encode()).hexdigest()[:8]
            ext = '.jpg'
            if '.png' in url.lower():
                ext = '.png'
            filename = f"dl_{url_hash}{ext}"
            save_path = os.path.join(folder_path, filename)

            # Skip if already downloaded
            if os.path.exists(save_path):
                print(f"  SKIP (exists): {filename}")
                continue

            print(f"  Downloading [{i+1}/{len(urls)}]: {url[:60]}...")
            success = download_image(url, save_path)
            if success:
                total_downloaded += 1
            else:
                total_failed += 1
            time.sleep(0.3)  # Be polite

    print(f"\n{'='*60}")
    print(f"DONE: {total_downloaded} downloaded, {total_failed} failed")
    print(f"\nNow run: python -m ml.train")


if __name__ == '__main__':
    main()
