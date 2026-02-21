#!/usr/bin/env python
"""Download training images from Pexels API for underrepresented food categories"""
import requests
import os
import time
from pathlib import Path
from PIL import Image
from io import BytesIO

# API key for Pexels (free tier: 50 req/hour)
PEXELS_API_KEY = '563492ad6f9170000100000118d7c79f7d1046c5ad2aaccce0ba99c7'

# Categories + foods to enhance
ADDITIONS = {
    'fruits': {'apple': 8, 'banana': 8, 'orange': 8, 'strawberry': 8, 'kiwi': 6, 'grapes': 6},
    'desserts': {'cake': 6, 'chocolate': 6, 'donut': 6, 'cookie': 6, 'candy': 6, 'ice cream': 4},
    'legumes': {'carrot': 6, 'broccoli': 6, 'tomato': 6, 'cucumber': 6, 'lettuce': 5},
    'viandes': {'steak': 6, 'chicken': 6, 'fish': 6, 'pork': 5, 'beef': 5},
    'fast_food': {'fries': 8, 'burger': 8, 'chicken nuggets': 6, 'salad': 6, 'sandwich': 5},
    'oeufs': {'eggs': 8, 'omelette': 6, 'fried eggs': 5},
}

def download_images(keyword, folder, count=5):
    """Download images from Pexels"""
    os.makedirs(folder, exist_ok=True)
    downloaded = 0
    
    for page in range(1, count + 2):  # Try up to count+1 pages
        if downloaded >= count:
            break
            
        try:
            # Rate limiting: 1.5 sec between requests
            time.sleep(1.5)
            
            response = requests.get(
                'https://api.pexels.com/v1/search',
                headers={'Authorization': PEXELS_API_KEY},
                params={'query': keyword, 'per_page': 1, 'page': page},
                timeout=10
            )
            
            if response.status_code != 200:
                print(f"  ✗ {keyword}: API returned {response.status_code}")
                break
            
            data = response.json()
            if not data.get('photos'):
                break  # No more images available
            
            photo = data['photos'][0]
            img_url = photo['src']['medium']
            
            # Download image
            img_response = requests.get(img_url, timeout=10)
            img = Image.open(BytesIO(img_response.content)).convert('RGB')
            
            # Save
            filename = f"{keyword.replace(' ', '_')}_{downloaded}.jpg"
            filepath = os.path.join(folder, filename)
            img.save(filepath, 'JPEG', quality=85)
            print(f"  ✓ {filename}")
            downloaded += 1
            
        except Exception as e:
            print(f"  ✗ {keyword}_{page}: {str(e)[:40]}")
            continue
    
    return downloaded

print("📥 Downloading training images for weak categories...\n")
total_downloaded = 0

for category, foods in ADDITIONS.items():
    cat_dir = f'data/raw/{category}'
    print(f"\n📁 {category}:")
    
    for food, target_count in foods.items():
        # Check existing count
        folder = os.path.join(cat_dir, food.replace(' ', '_'))
        existing = len(list(Path(folder).glob('*.[jJ][pP][gG]'))) if os.path.exists(folder) else 0
        
        to_download = max(0, target_count - existing)
        if to_download > 0:
            print(f"  {food} ({existing} exist, need {to_download} more):")
            downloaded = download_images(food, folder, to_download)
            total_downloaded += downloaded
        else:
            print(f"  {food}: ✓ Already has {existing} images")

print(f"\n✅ Downloaded {total_downloaded} new images total!")
print("\n⚡ Ready to retrain the model with: python ml/train.py")
