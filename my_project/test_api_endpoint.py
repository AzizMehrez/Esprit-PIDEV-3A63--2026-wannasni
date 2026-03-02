#!/usr/bin/env python3
"""
Test de l'API de détection
"""

import requests
import sys
from pathlib import Path

image_path = r"C:\Users\bacco\OneDrive\Bureau\MonProjetFinal\python\data\raw\les sucres\aug_color_0_chocolat.jpg"

print("📤 Test de l'API /analyze/step1-detect")
print("=" * 70)
print(f"Image: {Path(image_path).name}")
print(f"Chemin: {image_path}\n")

# Tester l'API
try:
    with open(image_path, 'rb') as f:
        files = {'file': f}
        response = requests.post(
            "http://localhost:8001/analyze/step1-detect",
            files=files,
            timeout=10
        )
    
    print(f"🔄 Code réponse: {response.status_code}\n")
    result = response.json()
    
    print("📊 Réponse API:")
    print("-" * 70)
    
    import json
    print(json.dumps(result, indent=2, ensure_ascii=False))
    
    if result.get('status') == 'success':
        foods = result.get('foods', [])
        if foods:
            print(f"\n✅ {len(foods)} aliment(s) détecté(s):")
            for food in foods:
                print(f"   • {food.get('nom', '?')}")
        else:
            print("\n⚠️  Aucun aliment détecté")
    else:
        print(f"\n❌ Erreur: {result.get('message', 'Inconnue')}")
        
except Exception as e:
    print(f"❌ Erreur requête: {e}")
    sys.exit(1)
