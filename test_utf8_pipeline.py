#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""Test nutrition detection pipeline with UTF-8 encoding"""

import sys
import os

# Ensure UTF-8
if sys.platform == 'win32':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')

import requests
from PIL import Image
import io as io_module
import numpy as np

print("✓ Testing detection pipeline with UTF-8 encoding support...")

# Create a test image (simple food-like image)
def create_test_image():
    """Create a simple test image"""
    img_array = np.random.randint(0, 256, (224, 224, 3), dtype=np.uint8)
    img = Image.fromarray(img_array.astype('uint8'), 'RGB')
    
    # Save to bytes
    img_bytes = io_module.BytesIO()
    img.save(img_bytes, format='JPEG')
    img_bytes.seek(0)
    return img_bytes

print("1️⃣  Creating test image...")
test_image = create_test_image()

print("2️⃣  Testing FastAPI /analyze/step1-detect endpoint...")
try:
    response = requests.post(
        'http://127.0.0.1:8001/analyze/step1-detect',
        files={'file': ('test.jpg', test_image, 'image/jpeg')},
        timeout=30
    )
    print(f"   → Status: {response.status_code}")
    print(f"   → Response: {response.json()}")
    
    if response.status_code == 200:
        print("   ✓ Detection successful!")
    else:
        print(f"   ✗ Error: {response.text}")
        
except Exception as e:
    print(f"   ✗ Error: {e}")

print("\n3️⃣  Testing UTF-8 character handling...")
try:
    # This tests if the logging with Unicode arrows works
    test_message = "💡 Test avec flèche → et caractères spéciaux: résultat → analyse"
    print(f"   └─ {test_message}")
    print("   ✓ UTF-8 characters handled correctly!")
except Exception as e:
    print(f"   ✗ UTF-8 Error: {e}")

print("\n✅ Pipeline test complete!")
