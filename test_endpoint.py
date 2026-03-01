#!/usr/bin/env python3
import sys
import os
sys.path.insert(0, 'python/ml')
os.chdir('c:\\Users\\bacco\\OneDrive\\Bureau\\MonProjetFinal')

from PIL import Image
import numpy as np
import requests

# Create a simple test image
img_array = np.random.randint(0, 255, (224, 224, 3), dtype=np.uint8)
img = Image.fromarray(img_array)
img.save('test_upload.jpg')

# Upload to Symfony endpoint with correct URL
try:
    with open('test_upload.jpg', 'rb') as f:
        files = {'meal_photo': ('test_upload.jpg', f, 'image/jpeg')}
        resp = requests.post('http://localhost:8000/fr/nutrition/tracking/step1-detect', files=files, timeout=60)
    print(f"Status: {resp.status_code}")
    print(f"Response: {resp.text[:800]}")
    if resp.status_code >= 400:
        print(f"\nError response (full): {resp.text}")
except Exception as e:
    print(f"Error: {e}")

