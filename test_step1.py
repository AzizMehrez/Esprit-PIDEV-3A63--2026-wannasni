import requests, os
BASE = 'http://127.0.0.1:8001'
img_path = 'public/uploads/meals/cho-6999e30f557aa.jpg'
print(f'Testing with: {img_path}')
if not os.path.exists(img_path):
    print(f'ERROR: File not found at {img_path}')
    exit(1)
with open(img_path, 'rb') as f:
    r = requests.post(f'{BASE}/analyze/step1-detect', files={'file': ('cho.jpg', f, 'image/jpeg')})
print(f'Status Code: {r.status_code}')
try:
    print(f'Response: {r.json()}')
except:
    print(f'Raw Response: {r.text}')
