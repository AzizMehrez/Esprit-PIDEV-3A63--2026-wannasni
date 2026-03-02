#!/usr/bin/env python3
"""
Test simple de détection du chocolat
"""

import sys
from pathlib import Path

# Chemin du projet
project_path = Path(__file__).parent
sys.path.insert(0, str(project_path))

from python.ml.full_nutrition_analyzer import FullNutritionAnalyzer

def test_local():
    """Test local du modèle CNN et similarité"""
    
    print("\n" + "="*70)
    print("🚀 TEST LOCAL DE DÉTECTION")
    print("="*70 + "\n")
    
    # Trouver une image de chocolat
    data_dir = project_path / "python" / "data" / "raw" / "les sucres"
    
    chocolate_images = list(data_dir.glob("*chocolat.jpg"))
    
    if not chocolate_images:
        print("❌ Aucune image de chocolat trouvée!")
        return False
    
    test_image = str(chocolate_images[0])
    print(f"📸 Image test: {Path(test_image).name}\n")
    
    # Charger l'analyseur
    print("🔧 Chargement du modèle...")
    analyzer = FullNutritionAnalyzer()
    print("✅ Modèle chargé\n")
    
    # Détecter
    print("🔍 Détection en cours...")
    try:
        result = analyzer.detect_only(test_image)
        
        print("\n" + "="*70)
        print("📊 RÉSULTATS")
        print("="*70 + "\n")
        
        if result.get("detected"):
            foods = result.get("foods", [])
            print(f"✅ DÉTECTÉ: {len(foods)} aliment(s)\n")
            
            for food in foods:
                name = food.get("nom", "?")
                kcal = food.get("calories", 0) or food.get("kcal", 0)
                conf = food.get("confiance", 0)
                print(f"   • {name:25} {kcal:4.0f} kcal | Confiance: {conf:5.0%}")
            
            # Vérifier si chocolat est détecté
            food_names = [f.get("nom", "").lower() for f in foods]
            
            if any("chocolat" in name for name in food_names):
                print("\n✅✅✅ SUCCESS! CHOCOLAT DÉTECTÉ! ✅✅✅\n")
                return True
            else:
                print(f"\n⚠️  Détecté: {', '.join([f.get('nom', '?') for f in foods])}\n")
                return False
        else:
            print(f"❌ {result.get('message', 'Détection échouée')}\n")
            return False
            
    except Exception as e:
        print(f"❌ Erreur: {e}\n")
        import traceback
        traceback.print_exc()
        return False

if __name__ == "__main__":
    success = test_local()
    sys.exit(0 if success else 1)
