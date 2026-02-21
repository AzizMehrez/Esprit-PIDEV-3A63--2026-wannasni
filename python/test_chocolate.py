#!/usr/bin/env python3
"""
Test de détection du chocolat après réentraînement
"""

import sys
import json
import base64
from pathlib import Path
from time import sleep

SCRIPT_DIR = Path(__file__).parent
DATA_DIR = SCRIPT_DIR / "data" / "raw"
CHOCOLATE_DIR = DATA_DIR / "les sucres"

def test_chocolate_detection():
    """Tester la détection du chocolat via l'API"""
    
    print("\n" + "="*70)
    print("🚀 TEST DE DÉTECTION DU CHOCOLAT")
    print("="*70 + "\n")
    
    # Trouver une image comportant "chocolat"
    chocolate_images = [f for f in CHOCOLATE_DIR.glob("*chocolat*.jpg")]
    
    if not chocolate_images:
        print("❌ Aucune image de chocolat trouvée!")
        return False
    
    test_image = str(chocolate_images[0])
    print(f"📸 Image test: {Path(test_image).name}\n")
    
    # Vérifier que le serveur est prêt
    import requests
    max_tries = 10
    for attempt in range(max_tries):
        try:
            response = requests.get("http://localhost:8001/api/health", timeout=2)
            if response.status_code == 200:
                print("✅ Serveur ML prêt!\n")
                break
        except Exception as e:
            if attempt < max_tries - 1:
                print(f"⏳ Tentative {attempt+1}/{max_tries}: Serveur en démarrage...")
                sleep(2)
            else:
                print(f"❌ Serveur non accessible après {max_tries} tentatives")
                return False
    
    # Charger l'image en base64
    with open(test_image, "rb") as f:
        img_data = base64.b64encode(f.read()).decode()
    
    # Envoyer vers l'API
    print("📤 Envoi de l'image au serveur ML...")
    
    try:
        # Préparer le fichier
        files = {"file": (Path(test_image).name, open(test_image, "rb"), "image/jpeg")}
        response = requests.post(
            "http://localhost:8001/analyze/step1-detect",
            files=files,
            timeout=10
        )
        
        if response.status_code != 200:
            print(f"❌ Erreur API: {response.status_code}")
            print(f"   {response.text}")
            return False
        
        result = response.json()
        
        # Afficher les résultats
        print("\n" + "="*70)
        print("📊 RÉSULTATS")
        print("="*70 + "\n")
        
        if result.get("status") == "success":
            foods = result.get("foods", [])
            print(f"✅ DÉTECTÉ: {len(foods)} aliment(s)\n")
            
            for food in foods:
                name = food.get("nom", "?")
                kcal = food.get("calories", 0) or food.get("kcal", 0)
                conf = food.get("confiance", 0)
                print(f"   • {name}")
                print(f"     └─ {kcal} kcal | Confiance: {conf:.0%}\n")
            
            # Vérifier si chocolat est détecté
            food_names = [f.get("nom", "").lower() for f in foods]
            
            if any("chocolat" in name for name in food_names):
                print("✅✅✅ SUCCESS! CHOCOLAT DÉTECTÉ! ✅✅✅\n")
                return True
            else:
                print("⚠️  Le modèle a détecté quelque chose mais PAS du chocolat")
                print(f"   Détecté: {', '.join([f.get('nom', '?') for f in foods])}\n")
                return False
        elif result.get("status") == "not_detected":
            print(f"⚠️  Aucun aliment détecté")
            print(f"   Message: {result.get('message')}\n")
            return False
            
    except Exception as e:
        print(f"❌ Erreur requête: {e}\n")
        return False

if __name__ == "__main__":
    success = test_chocolate_detection()
    sys.exit(0 if success else 1)
