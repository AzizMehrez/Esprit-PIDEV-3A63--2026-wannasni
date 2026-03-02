#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Comprehensive pipeline test: Symfony + FastAPI"""

import sys
import os

if sys.platform == 'win32':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

import requests  
import json
from PIL import Image
import io as io_module
import numpy as np

def create_test_image():
    """Create a test image"""
    img_array = np.random.randint(0, 256, (224, 224, 3), dtype=np.uint8)
    img = Image.fromarray(img_array.astype('uint8'), 'RGB')
    img_bytes = io_module.BytesIO()
    img.save(img_bytes, format='JPEG')
    img_bytes.seek(0)
    return img_bytes

print("=" * 60)
print("🔍 WANNASNI Pipeline End-to-End Test")
print("=" * 60)

# Test 1: FastAPI Health Check
print("\n1️⃣  FastAPI Server Health Check")
print("-" * 40)
try:
    response = requests.get('http://127.0.0.1:8001/', timeout=5)
    if response.status_code == 200:
        data = response.json()
        print(f"   ✅ FastAPI Online")
        print(f"   → Status: {data['status']}")
        print(f"   → Model: {data['model']}")
    else:
        print(f"   ❌ Unexpected status: {response.status_code}")
except Exception as e:
    print(f"   ❌ FastAPI Error: {e}")

# Test 2: Symfony Health Check
print("\n2️⃣  Symfony Backend Health Check")
print("-" * 40)
try:
    response = requests.get('http://127.0.0.1:8000/', timeout=5)
    if response.status_code in [200, 302]:  # 302 = redirect to login
        print(f"   ✅ Symfony Online")
        print(f"   → Status: {response.status_code}")
    else:
        print(f"   ⚠️  Unexpected status: {response.status_code}")
except Exception as e:
    print(f"   ❌ Symfony Error: {e}")

# Test 3: UTF-8 Character Support
print("\n3️⃣  UTF-8 Character Encoding Test")
print("-" * 40)
try:
    messages = [
        "→ Arrow character (right arrow)",
        "✓ Checkmark",
        "💡 Emoji (lightbulb)",
        "ç è é Special French chars",
    ]
    for msg in messages:
        print(f"   ✓ {msg}")
    print("   ✅ UTF-8 encoding works!")
except Exception as e:
    print(f"   ❌ UTF-8 Error: {e}")

# Test 4: FastAPI Detection Endpoint
print("\n4️⃣  FastAPI Detection Endpoint Test")
print("-" * 40)
try:
    test_image = create_test_image()
    response = requests.post(
        'http://127.0.0.1:8001/analyze/step1-detect',
        files={'file': ('test.jpg', test_image, 'image/jpeg')},
        timeout=30
    )
    
    if response.status_code == 200:
        result = response.json()
        status = result.get('status', 'unknown')
        print(f"   ✅ Detection endpoint working")
        print(f"   → Status: {status}")
        print(f"   → Source: {result.get('detection_source', 'N/A')}")
        
        if status == 'not_detected':
            print(f"   → Message: {result.get('message', 'No message')[:50]}...")
        elif status == 'success':
            foods = result.get('foods', [])
            print(f"   → Foods detected: {len(foods)}")
            for food in foods[:3]:
                print(f"      • {food.get('name', 'Unknown')} ({food.get('confidence', 0):.2f})")
    else:
        print(f"   ❌ Error: {response.status_code}")
        print(f"   → Response: {response.text[:100]}")
        
except Exception as e:
    print(f"   ❌ Detection Error: {e}")

# Test 5: Nutrition Endpoint
print("\n5️⃣  FastAPI Nutrition Endpoint Test")
print("-" * 40)
try:
    test_foods = ["poulet", "riz", "legumes"]
    test_data = {
        'foods': json.dumps(test_foods),
        'regime': 'Normal'
    }
    
    response = requests.post(
        'http://127.0.0.1:8001/analyze/step2-nutrition',
        data=test_data,
        timeout=30
    )
    
    if response.status_code == 200:
        result = response.json()
        status = result.get('status', 'unknown')
        print(f"   ✅ Nutrition endpoint working")
        print(f"   → Status: {status}")
        if status == 'success':
            print(f"   → Foods processed: {len(result.get('aliments', []))}")
            calories = result.get('total_calories', 0)
            print(f"   → Total calories: {calories:.0f}")
    else:
        print(f"   ❌ Error: {response.status_code}")
        
except Exception as e:
    print(f"   ❌ Nutrition Error: {e}")

print("\n" + "=" * 60)
print("✅ Pipeline test complete!")
print("=" * 60)
