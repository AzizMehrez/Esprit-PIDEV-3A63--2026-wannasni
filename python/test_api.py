"""Quick API test - run with: python test_api.py"""
import requests, os, json

BASE = 'http://127.0.0.1:8001'

def test(label, img_path):
    print(f'\n--- {label} ---')
    if not os.path.exists(img_path):
        print(f'  NOT FOUND: {img_path}')
        return
    with open(img_path, 'rb') as f:
        r = requests.post(f'{BASE}/analyze/step1-detect', files={'file': ('test.jpg', f, 'image/jpeg')})
    data = r.json()
    if data.get('status') == 'success':
        foods = data.get('foods', [])
        if foods:
            for food in foods:
                nom = food.get('nom')
                conf = food.get('confiance', 0)
                src = food.get('source', '')
                print(f'  OK: {nom} (conf={conf:.2f}, src={src})')
        else:
            print('  => empty foods list')
    else:
        print(f'  FAIL: {data.get("status")} - {data.get("message","")}')

# Test from the raw training images directory
test('lasagne', 'data/raw/plats_pates/lasagne/dl_d077f87b.jpg')
test('lasagne 2', 'data/raw/plats_pates/lasagne/dl_329d8e56.jpg')
test('escalope panee', 'data/raw/viandes/escalope_panee/scalope.jpg')
test('escalope panee 2', os.path.join('data','raw','viandes','escalope_panee','crunshy scalope.jpg'))
test('poulet grille', 'data/raw/viandes/poulet/chicken.jpg')
test('steak hache', 'data/raw/viandes/viande_hachee/viande.jpg')
test('spaghetti', os.path.join('data','raw','plats_pates','spaghetti','spagetti juste avec sauce.jpg'))
test('riz', os.path.join('data','raw','riz','riz_blanc_avec_salade.jpg'))
test('burger', 'data/raw/fast_food/burger/burger.jpg')

print('\n=== DONE ===')
