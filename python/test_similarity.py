import os
import sys
import cv2
import numpy as np
from pathlib import Path

# Ajouter le dossier parent au path pour pouvoir importer le package ml
sys.path.append(os.path.dirname(os.path.dirname(__file__)))

from python.ml.full_nutrition_analyzer import FullNutritionAnalyzer

def test_similarity():
    """Test basique de détection par similarité."""
    analyzer = FullNutritionAnalyzer()
    
    # Chercher une image de test dans la structure
    test_images = []
    raw_dir = os.path.join(os.path.dirname(__file__), 'data', 'raw')
    
    if os.path.exists(raw_dir):
        for ext in ['*.jpg', '*.jpeg', '*.png']:
            test_images.extend(list(Path(raw_dir).rglob(ext)))
    
    if not test_images:
        print("❌ Aucune image de test trouvée dans data/raw/")
        print("   Veuillez ajouter des images pour tester la détection.")
        return
    
    # Prendre la première image trouvée
    test_image = str(test_images[0])
    print(f"🔍 Test de détection sur : {test_image}")
    
    results = analyzer.analyze_meal(test_image, "Standard")
    
    print("\n📊 --- Résultats de l'analyse ---")
    print(f"Statut : {results.get('status', 'inconnu')}")
    
    if results.get('status') == 'not_detected':
        print(f"Message : {results.get('message', 'Aucune détection')}")
        print(f"Conseil : {results.get('conseil', '')}")
    else:
        aliments = results.get('aliments_detectes', [])
        print(f"Aliments détectés : {[a['nom'] for a in aliments]}")
        print(f"Total Calories : {results.get('analyse_nutritionnelle', {}).get('total_nutrition', {}).get('calories', 0)} kcal")
        
        compliance = results.get('analyse_nutritionnelle', {}).get('compliance', {})
        print(f"Conformité régime : {'✅' if compliance.get('conforme') else '❌'}")
        
        if not compliance.get('conforme'):
            print(f"Raisons : {compliance.get('raisons', [])}")
        
        alertes = results.get('rapport', {}).get('alerts', [])
        if alertes:
            print("Alertes :")
            for a in alertes:
                print(f"  - [{a.get('type', 'info')}] {a.get('message', '')}")
    
    print("------------------------------\n")

def test_multi_food_detection():
    """Test de détection multiple d'aliments."""
    analyzer = FullNutritionAnalyzer()
    
    # Créer une image de test synthétique avec plusieurs aliments
    test_img_path = os.path.join(os.path.dirname(__file__), 'test_multi.jpg')
    
    # Si l'image n'existe pas, on utilise la première image trouvée
    if not os.path.exists(test_img_path):
        raw_dir = os.path.join(os.path.dirname(__file__), 'data', 'raw')
        if os.path.exists(raw_dir):
            test_images = list(Path(raw_dir).rglob('*.jpg'))
            if test_images:
                test_img_path = str(test_images[0])
    
    print(f"🔍 Test de détection multiple sur : {test_img_path}")
    
    # Utiliser la méthode avancée
    results = analyzer.analyze_meal_advanced(test_img_path, "Standard")
    
    print("\n📊 --- Résultats de l'analyse avancée ---")
    print(f"Statut : {results.get('status', 'inconnu')}")
    
    if results.get('status') == 'success':
        print(f"Nombre d'aliments détectés : {results.get('nombre_aliments', 0)}")
        
        aliments = results.get('aliments_detectes', [])
        for i, a in enumerate(aliments, 1):
            print(f"\n  Aliment {i}: {a.get('nom', 'inconnu')}")
            print(f"    - Calories: {a.get('calories', 0)} kcal")
            print(f"    - Quantité: {a.get('quantite', 100)}g")
            print(f"    - Catégorie: {a.get('categorie', 'inconnu')}")
        
        if results.get('mode_cuisson'):
            print(f"\nMode de cuisson détecté: {results['mode_cuisson'].get('label', 'inconnu')}")
            print(f"  Conseil: {results['mode_cuisson'].get('conseil', '')}")
        
        if results.get('recettes_suggerees'):
            print(f"\nRecettes suggérées ({len(results['recettes_suggerees'])}):")
            for r in results['recettes_suggerees'][:3]:
                print(f"  - {r.get('nom', r.get('name', 'inconnu'))} ({r.get('calories', 0)} kcal)")
    
    print("------------------------------\n")

def test_nutritionist_summary():
    """Test de génération de résumé nutritionniste."""
    analyzer = FullNutritionAnalyzer()
    
    # Créer un historique de repas simulé
    meal_history = [
        {
            "date": "2024-01-15 12:30:00",
            "aliments": ["poulet_grille", "riz_complet", "brocoli"],
            "calories": 550,
            "estConforme": True
        },
        {
            "date": "2024-01-16 19:00:00",
            "aliments": ["saumon", "haricots_verts", "pomme_de_terre_vapeur"],
            "calories": 620,
            "estConforme": True
        },
        {
            "date": "2024-01-17 13:00:00",
            "aliments": ["burger_classique", "frites_moyenne"],
            "calories": 950,
            "estConforme": False
        }
    ]
    
    print("📊 Test de génération de résumé nutritionniste")
    
    summary = analyzer.generate_nutritionist_summary(
        meal_history=meal_history,
        regime_type="Standard",
        daily_limit=2000,
        poids=70,
        taille=175,
        age=65
    )
    
    if summary.get('status') == 'success':
        resume = summary.get('resume', {})
        print(f"\nRésumé sur {resume.get('nombre_repas', 0)} repas:")
        print(f"  - Taux de conformité: {resume.get('taux_conformite', 0)}%")
        print(f"  - Calories moyennes: {resume.get('calories_moyenne', 0)} kcal")
        
        aliments_freq = summary.get('aliments_frequents', [])
        if aliments_freq:
            print(f"\nAliments les plus fréquents:")
            for a in aliments_freq[:3]:
                print(f"  - {a.get('nom', '')}: {a.get('count', 0)} fois")
        
        score_risque = summary.get('score_risque', {})
        if score_risque:
            print(f"\nScore de risque: {score_risque.get('score', 0)} - {score_risque.get('niveau', 'inconnu')}")
        
        suggestions = summary.get('suggestions_ajustement', [])
        if suggestions:
            print(f"\nSuggestions d'ajustement:")
            for s in suggestions:
                print(f"  - {s}")
    
    print("------------------------------\n")

def test_meal_reminders():
    """Test de génération de rappels de repas."""
    analyzer = FullNutritionAnalyzer()
    
    print("⏰ Test de génération de rappels de repas")
    
    reminders = analyzer.generate_meal_reminders(
        regime_type="Diabétique",
        repas_par_jour=4,
        repas_consommes=2,
        calories_consommees=900,
        calories_limite=2000,
        aliments_recommandes=["legume", "proteine_maigre"],
        aliments_interdits=["sucre", "boisson_sucree"]
    )
    
    if reminders.get('status') == 'success':
        print(f"\nRepas restants: {reminders.get('repas_restants', 0)}")
        print(f"Calories restantes: {reminders.get('calories_restantes', 0)} kcal")
        
        notifications = reminders.get('notifications', [])
        if notifications:
            print(f"\nNotifications ({len(notifications)}):")
            for n in notifications:
                print(f"  - [{n.get('type', 'info')}] {n.get('titre', '')}")
                print(f"    {n.get('message', '')}")
        
        suggestions = reminders.get('suggestions_repas', [])
        if suggestions:
            print(f"\nSuggestions pour les repas restants:")
            for s in suggestions:
                print(f"  - {s.get('type_repas', '')}: {s.get('calories_suggerees', 0)} kcal ({s.get('statut', '')})")
    
    print("------------------------------\n")

def test_texture_analysis():
    """Test d'analyse de texture pour personnes avec difficultés de déglutition."""
    analyzer = FullNutritionAnalyzer()
    
    print("🍽️ Test d'analyse de texture")
    
    # Simuler des aliments détectés
    detected_foods = [
        {"nom": "steak_boeuf", "detected": True},
        {"nom": "frites_moyenne", "detected": True},
        {"nom": "salade_verte", "detected": True}
    ]
    
    # Image de test (on utilise la première disponible)
    test_img_path = ""
    raw_dir = os.path.join(os.path.dirname(__file__), 'data', 'raw')
    if os.path.exists(raw_dir):
        test_images = list(Path(raw_dir).rglob('*.jpg'))
        if test_images:
            test_img_path = str(test_images[0])
    
    if not test_img_path:
        print("❌ Aucune image de test trouvée")
        return
    
    texture_analysis = analyzer.analyze_texture(
        image_path=test_img_path,
        detected_foods=detected_foods,
        difficulte_deglutition=True
    )
    
    if texture_analysis.get('applicable'):
        print(f"\nAnalyse de texture pour personnes avec difficultés de déglutition:")
        
        resultats = texture_analysis.get('resultats', [])
        for r in resultats:
            print(f"\n  Aliment: {r.get('aliment', '')}")
            print(f"    - Texture: {r.get('texture', '')}")
            print(f"    - Risque étouffement: {r.get('risque_etouffement', '')}")
            print(f"    - Suggestion: {r.get('suggestion', '')}")
        
        alertes = texture_analysis.get('alertes', [])
        if alertes:
            print(f"\nAlertes ({len(alertes)}):")
            for a in alertes:
                print(f"  - [{a.get('type', 'info')}] {a.get('titre', '')}")
                print(f"    {a.get('message', '')}")
        
        print(f"\nConseil général: {texture_analysis.get('conseil_general', '')}")
    
    print("------------------------------\n")

if __name__ == "__main__":
    print("=" * 60)
    print("WANNASNI AI - Tests du module d'analyse nutritionnelle")
    print("=" * 60 + "\n")
    
    # Exécuter tous les tests
    test_similarity()
    test_multi_food_detection()
    test_nutritionist_summary()
    test_meal_reminders()
    test_texture_analysis()
    
    print("\n✅ Tests terminés")