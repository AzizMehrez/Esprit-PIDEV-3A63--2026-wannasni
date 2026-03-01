#!/usr/bin/env python
"""
Download lasagne and other food images from Unsplash (free API, no auth required)
"""
import requests
import os
from pathlib import Path
from PIL import Image
from io import BytesIO
import time

def download_from_unsplash(query, folder, count=6):
    """Download images from Unsplash API (free tier)"""
    os.makedirs(folder, exist_ok=True)
    downloaded = 0
    
    # Unsplash uses random endpoint for free apps
    for page in range(1, count + 2):
        try:
            url = f"https://api.unsplash.com/search/photos?query={query}&page={page}&per_page=1&client_id=pIEL8FVJQJeVl-5qsxBILWlLNRDPZNzLArYfGXV7zc0"
            
            time.sleep(1)  # Rate limiting
            
            response = requests.get(url, timeout=10)
            if response.status_code != 200:
                print(f"  API error {response.status_code} for {query}")
                break
            
            data = response.json()
            if not data.get('results'):
                print(f"  No more results for {query}")
                break
            
            photo = data['results'][0]
            img_url = photo['urls']['regular']
            
            img_data = requests.get(img_url, timeout=10).content
            img = Image.open(BytesIO(img_data)).convert('RGB')
            img = img.resize((512, 512))
            
            filename = f"{query.replace(' ', '_')}_{page}.jpg"
            filepath = os.path.join(folder, filename)
            img.save(filepath, 'JPEG', quality=90)
            print(f"  ✓ {filename}")
            downloaded += 1
            
        except Exception as e:
            print(f"  ✗ Error page {page}: {str(e)[:40]}")
            continue
    
    return downloaded

print("📥 Downloading food images from Unsplash...\n")

queries = {
    'plats_pates/lasagne': [
        'italian lasagne pasta',
        'homemade lasagne baked',
        'beef lasagne dinner',
        'layered lasagne food'
    ],
    'plats_pates/spaghetti_bolognaise': [
        'spaghetti bolognese', 
        'pasta bolognaise',
        'spaghetti meat sauce'
    ],
    'viandes/escalope_panee': [
        'breaded veal escalope',
        'fried breaded chicken',
        'scaloppine breaded'
    ],
}

total = 0
for folder_path, query_list in queries.items():
    full_path = f"data/raw/{folder_path}"
    print(f"\n📁 {folder_path}:")
    
    for query in query_list:
        print(f"  Searching: {query}")
        count = download_from_unsplash(query, full_path, count=2)
        total += count
        
        if total > 20:  # Safety limit
            print("\nReached download limit. Stopping.")
            break
    
    if total > 20:
        break

print(f"\n✅ Downloaded {total} new images!")
print("Next: Run augmentation + re-index")
