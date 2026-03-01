import os
import sys

# Ajouter le dossier parent au path pour pouvoir importer le package ml
sys.path.append(os.path.dirname(os.path.dirname(__file__)))

from python.ml.full_nutrition_analyzer import FullNutritionAnalyzer

def test_similarity():
    analyzer = FullNutritionAnalyzer()
    
    # Trouver une image de test dans la nouvelle structure
    # On teste le poulet
    test_image = os.path.join(os.path.dirname(__file__), 'data', 'raw', 'viandes', 'poulet', 'chicken.jpg')
    
    if not os.path.exists(test_image):
        print(f"Image de test non trouvée à : {test_image}")
        # fallback sur une autre image possible
        return
        
    print(f"Test de détection sur : {test_image}")
    
    results = analyzer.analyze_meal(test_image, "Hypocalorique")
    
    print("\n--- Résultats de l'analyse ---")
    print(f"Statut : {results['status']}")
    print(f"Aliments détectés : {[a['nom'] for a in results['aliments']]}")
    print(f"Total Calories : {results['total_calories']} kcal")
    print(f"Conformité : {results['compliance']['conforme']}")
    if not results['compliance']['conforme']:
        print(f"Raisons : {results['compliance']['raisons']}")
    print(f"Alertes : {[a['message'] for a in results['alerts']]}")
    print("------------------------------")

def test_full_analysis():
    analyzer = FullNutritionAnalyzer()

    test_image = os.path.join(os.path.dirname(__file__), 'data', 'raw', 'viandes', 'poulet', 'chicken.jpg')

    if not os.path.exists(test_image):
        print(f"Image de test non trouvée à : {test_image}")
        return

    print(f"Test d'analyse complète sur : {test_image}")

    results = analyzer.analyze_meal(test_image, "Hypocalorique", daily_limit=2000, consumed_today=700)

    print("\n--- Résultats de l'analyse complète ---")
    print(f"Statut : {results['status']}")
    print(f"Aliments détectés : {[a['nom'] for a in results['foods']]}")
    print(f"Total Calories : {results['nutrition']['total_nutrition']['calories']} kcal")
    print(f"Comparaison Calories : {results['calorie_comparison']['message']}")
    print(f"Recettes proposées : {[r['name'] for r in results['recipes']]}")
    print("------------------------------")

if __name__ == "__main__":
    test_similarity()
