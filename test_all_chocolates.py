#!/usr/bin/env python3
"""
Test toutes les images de chocolat via l'API
"""

import requests
from pathlib import Path
import json

data_dir = Path("python/data/raw/les sucres")
chocolate_images = sorted(list(data_dir.glob("*chocolat*.jpg")))

print(f"🧪 Test de {len(chocolate_images)} images de chocolat via l'API\n")
print("=" * 80)

success_count = 0
failed_count = 0

for img_path in chocolate_images:
    try:
        with open(img_path, 'rb') as f:
            files = {'file': f}
            response = requests.post(
                "http://localhost:8001/analyze/step1-detect",
                files=files,
                timeout=10
            )
        
        result = response.json()
        status = result.get('status', '?')
        
        if status == 'success' and result.get('foods'):
            foods = result.get('foods', [])
            food_names = ', '.join([f.get('nom', '?') for f in foods])
            print(f"✅ {img_path.name}")
            print(f"   → Détecté: {food_names}")
            success_count += 1
        else:
            print(f"❌ {img_path.name}")
            print(f"   → {result.get('message', 'Aucun aliment')}")
            failed_count += 1
            
    except Exception as e:
        print(f"❌ {img_path.name}")
        print(f"   → Erreur: {e}")
        failed_count += 1

print("\n" + "=" * 80)
print(f"\n📊 Résultats:")
print(f"   ✅ Succès: {success_count}/{len(chocolate_images)}")
print(f"   ❌ Échouées: {failed_count}/{len(chocolate_images)}")

if success_count == len(chocolate_images):
    print(f"\n🎉 EXCELLENT! Toutes les images sont détectées correctement!")
elif success_count > 0:
    print(f"\n⚠️  {success_count} images détectées sur {len(chocolate_images)}")
else:
    print(f"\n⚠️  Aucune image détectée - vérifier l'API")
