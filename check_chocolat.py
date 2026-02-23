#!/usr/bin/env python
import sys
if sys.platform == 'win32':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

from python.ml.nutrition_knowledge import NUTRITION_DATA

if 'chocolat' in NUTRITION_DATA:
    print('✓ chocolat exists in NUTRITION_DATA')
    data = NUTRITION_DATA['chocolat']
    print(f'  Calories: {data.get("calories")}')
    print(f'  Category: {data.get("categorie")}')
else:
    print('✗ ch chocolate NOT in NUTRITION_DATA')
    chocol_keys = [k for k in NUTRITION_DATA.keys() if 'chocol' in k.lower()]
    print(f'Available chocolat* keys: {chocol_keys}')
