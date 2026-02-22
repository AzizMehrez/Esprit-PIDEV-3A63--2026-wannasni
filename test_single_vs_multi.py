"""Test the single-vs-multi food classifier with various images."""
import requests
import sys
import os
import glob

API_URL = "http://localhost:8001/analyze/step1-detect"
DATA_DIR = r"C:\Users\bacco\OneDrive\Bureau\MonProjetFinal\python\data\raw"

def test_image(image_path, expected_food):
    """Test a single image and show results."""
    print(f"\n{'='*60}")
    print(f"TEST: {os.path.basename(image_path)}")
    print(f"EXPECTED: {expected_food}")
    print(f"{'='*60}")
    
    try:
        with open(image_path, 'rb') as f:
            r = requests.post(API_URL, files={'file': f})
        
        data = r.json()
        if data.get('status') == 'success':
            foods = data.get('foods', [])
            print(f"DETECTED ({len(foods)} items):")
            for food in foods:
                print(f"  - {food['nom']} (conf={food['confiance']:.2f}, source={food['source']})")
            
            if len(foods) == 1 and expected_food.lower() in foods[0]['nom'].lower():
                print(">>> PASS")
                return True
            elif len(foods) > 1:
                print(f">>> FAIL - Got {len(foods)} foods instead of 1")
                return False
            else:
                print(f">>> CHECK - Got {foods[0]['nom'] if foods else 'nothing'}")
                return foods[0]['nom'] if foods else None
        else:
            print(f"ERROR: {data}")
            return False
    except Exception as e:
        print(f"ERROR: {e}")
        return False

# Test 1: Frites (single food)
frites_images = glob.glob(os.path.join(DATA_DIR, "fast_food", "fries", "aug_color_0_chips.jpg"))
if frites_images:
    test_image(frites_images[0], "frites")

# Test 2: Burger (single food)
burger_images = glob.glob(os.path.join(DATA_DIR, "fast_food", "burger", "*.jpg"))
burger_originals = [b for b in burger_images if 'aug_' not in os.path.basename(b)]
if burger_originals:
    test_image(burger_originals[0], "burger")
elif burger_images:
    test_image(burger_images[0], "burger")

# Test 3: Pomme (single food)
pomme_images = glob.glob(os.path.join(DATA_DIR, "fruits", "pomme", "*.jpg"))
pomme_originals = [p for p in pomme_images if 'aug_' not in os.path.basename(p)]
if pomme_originals:
    test_image(pomme_originals[0], "pomme")
elif pomme_images:
    test_image(pomme_images[0], "pomme")

# Test 4: Poulet (single food)
poulet_images = glob.glob(os.path.join(DATA_DIR, "proteines_generiques", "poulet", "*.jpg"))
poulet_originals = [p for p in poulet_images if 'aug_' not in os.path.basename(p)]
if poulet_originals:
    test_image(poulet_originals[0], "poulet")
elif poulet_images:
    test_image(poulet_images[0], "poulet")

print("\n" + "="*60)
print("Tests completed!")
