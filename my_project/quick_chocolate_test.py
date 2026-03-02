#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Quick test: does chocolate detection work now?"""

import sys
if sys.platform == 'win32':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

import requests

chocolate_path = r'python/data/raw/les sucres/chocolat.jpg'
print("🍫 Testing Chocolate Detection")
print("=" * 50)

try:
    with open(chocolate_path, 'rb') as f:
        response = requests.post(
            'http://127.0.0.1:8001/analyze/step1-detect',
            files={'file': ('chocolat.jpg', f, 'image/jpeg')},
            timeout=30
        )
    
    result = response.json()
    status = result.get('status')
    
    print(f"Status: {status}")
    
    if status == 'success':
        foods = result.get('foods', [])
        print(f"✅ SUCCESS! {len(foods)} foods detected:")
        for food in foods:
            nom = food.get('nom', 'Unknown')
            conf = food.get('confiance', 0)
            print(f"   • {nom}: {conf:.2f}")
    else:
        msg = result.get('message', 'No message')
        print(f"❌ NOT DETECTED")
        print(f"Message: {msg[:100]}")
        
except Exception as e:
    print(f"Error: {e}")
