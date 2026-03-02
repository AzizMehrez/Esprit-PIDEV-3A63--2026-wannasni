#!/usr/bin/env python3
"""
Test script to verify chocolate detection with nutrition data
"""
import requests
import json
from pathlib import Path
import glob

# Find chocolate test image
sucres_dir = Path("python/data/raw/les sucres")
if sucres_dir.exists():
    all_sucres = list(sucres_dir.glob("*.jpg"))
    if all_sucres:
        test_image = str(all_sucres[0])
        print(f"[OK] Using image from les sucres: {test_image}")
    else:
        print(f"❌ No images found in {sucres_dir}")
        exit(1)
else:
    print(f"❌ Directory not found: {sucres_dir}")
    exit(1)

try:
    # Test Step 1 Detection
    print("\n[*] Testing Chocolate Detection (step1-detect)...")
    with open(test_image, 'rb') as f:
        files = {'file': f}
        response = requests.post(
            'http://localhost:8001/analyze/step1-detect',
            files=files,
            timeout=30
        )
    
    print(f"Status Code: {response.status_code}")
    
    try:
        data = response.json()
        print(f"\n[DATA] Detection Result:")
        print(json.dumps(data, indent=2, ensure_ascii=False))
        
        if data.get('foods'):
            food = data['foods'][0]
            print(f"\n[+] DETECTED: {food.get('nom')} (confidence: {food.get('confiance')})")
            print(f"[CALORIES] {food.get('calories')} kcal")
            print(f"[CATEGORY] {food.get('categorie')}")
            print(f"[PORTION] {food.get('portion_moyenne')}{food.get('unite', 'g')}")
            
            nutriments = food.get('nutriments', {})
            if nutriments:
                print(f"[NUTRIENTS] {nutriments}")
        
    except Exception as e:
        print(f"[ERROR] Failed to parse response: {e}")
        print(f"Response: {response.text}")
    
except Exception as e:
    print(f"[ERROR] Connection error: {e}")
    print("Make sure FastAPI is running on http://localhost:8001")
