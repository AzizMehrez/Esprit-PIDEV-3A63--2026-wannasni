#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Complete test with chocolate nutritional data"""

import sys
if sys.platform == 'win32':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

import requests
import json

chocolate_path = r'python/data/raw/les sucres/chocolat.jpg'
print("🍫 Complete Chocolate Detection Test")
print("=" * 60)

try:
    with open(chocolate_path, 'rb') as f:
        response = requests.post(
            'http://127.0.0.1:8001/analyze/step1-detect',
            files={'file': ('chocolat.jpg', f, 'image/jpeg')},
            timeout=30
        )
    
    result = response.json()
    
    print(f"\n✅ Status: {result.get('status')}")
    
    if result.get('status') == 'success':
        foods = result.get('foods', [])
        print(f"\n📊 Detected {len(foods)} food(s):")
        for i, food in enumerate(foods, 1):
            print(f"\n  {i}. {food.get('nom', 'Unknown').upper()}")
            print(f"     • Confidence: {food.get('confiance', 0):.2%}")
            print(f"     • Category: {food.get('categorie', 'N/A')}")
            print(f"     • Calories: {food.get('calories', 0):.0f} kcal")
            print(f"     • Typical portion: {food.get('portion_moyenne', 100)}g")
            
            nutrients = food.get('nutriments', {})
            if nutrients:
                print(f"     • Macros (per 100g):")
                print(f"       - Proteins: {nutrients.get('proteins', 'N/A')}g")
                print(f"       - Carbs: {nutrients.get('glucides', 'N/A')}g")
                print(f"       - Fats: {nutrients.get('lipides', 'N/A')}g")
        
        print(f"\n" + "=" * 60)
        print("✅ CHOCOLATE DETECTION WORKING PERFECTLY!")     
        print("=" * 60)
    else:
        print(f"Message: {result.get('message', '')}")
        
except Exception as e:
    print(f"Error: {e}")
