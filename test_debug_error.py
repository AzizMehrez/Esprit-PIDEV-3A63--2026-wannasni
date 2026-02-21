#!/usr/bin/env python3
"""
Test script to debug the 500 error from step1-detect endpoint
"""
import requests
import json
from pathlib import Path

# Test image path
test_image = Path("python/data/raw/legumes/image_1.jpg")

if not test_image.exists():
    # Find any test image
    import glob
    images = glob.glob("python/data/raw/*/*.jpg")
    if images:
        test_image = images[0]
        print(f"Using test image: {test_image}")
    else:
        print("❌ No test images found!")
        exit(1)

try:
    # Test Step 1 Detection
    print("[*] Testing Step 1 Detection (step1-detect)...")
    with open(test_image, 'rb') as f:
        files = {'file': f}
        response = requests.post(
            'http://localhost:8001/analyze/step1-detect',
            files=files,
            timeout=30
        )
    
    print(f"Status Code: {response.status_code}")
    print(f"Response Headers: {response.headers}")
    
    try:
        data = response.json()
        print(f"Response JSON:\n{json.dumps(data, indent=2, ensure_ascii=False)}")
    except:
        print(f"Response Text:\n{response.text}")
    
    if response.status_code != 200:
        print(f"\n❌ ERROR {response.status_code}: {response.reason}")
        print("\n🔴 Server returned an error - check FastAPI logs for traceback")
    else:
        print(f"[OK] SUCCESS: Detection returned properly")
        
except Exception as e:
    print(f"[ERROR] Connection error: {e}")
    print("Make sure FastAPI is running on http://localhost:8001")
