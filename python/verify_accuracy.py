import os
import sys

# Add the current directory to path so we can import ml
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from ml.full_nutrition_analyzer import FullNutritionAnalyzer

def verify_accuracy():
    print("=== DÉMARRAGE DE LA VÉRIFICATION DE PRÉCISION ===")
    analyzer = FullNutritionAnalyzer()
    
    # Liste des images à tester pour vérifier la discrimination
    test_cases = [
        ("data/raw/viandes/poulet/chicken.jpg", "poulet"),
        ("data/raw/fruits/pomme/pomme1.jpg", "pomme"),
        ("data/raw/viandes/viande_hachee/viande.jpg", "viande_hachee"),
        ("data/raw/oeufs/oeufs/ouefs.jpg", "oeufs")
    ]
    
    for relative_path, expected_cat in test_cases:
        full_path = os.path.join(os.path.dirname(__file__), relative_path)
        if not os.path.exists(full_path):
            print(f"[-] Image manquante : {relative_path}")
            continue
            
        print(f"\n[+] Test : {relative_path} (Attendu: {expected_cat})")
        match = analyzer.similarity_matcher.find_match(full_path)
        
        if match:
            status = "✅ SUCCÈS" if match['category'] == expected_cat else "❌ ERREUR"
            print(f"{status} | Trouvé: {match['category']} | Confiance: {match['confidence']:.2f}")
        else:
            print(f"⚠️ NON DÉTECTÉ | L'IA n'est pas assez sûre.")

def verify_full_analysis():
    print("=== TEST DE L'ANALYSE COMPLÈTE ===")
    analyzer = FullNutritionAnalyzer()

    test_cases = [
        ("data/raw/viandes/poulet/chicken.jpg", "Hypocalorique"),
        ("data/raw/fruits/pomme/pomme1.jpg", "Hyperprotéiné")
    ]

    for image_path, regime in test_cases:
        if not os.path.exists(image_path):
            print(f"[-] Image manquante : {image_path}")
            continue

        print(f"\n[+] Test : {image_path} (Régime: {regime})")
        results = analyzer.analyze_meal(image_path, regime, daily_limit=2000, consumed_today=800)
        
        print(f"Statut: {results.get('status')}")
        if results.get('status') == 'success':
            aliments = results.get('aliments_detectes', [])
            print(f"Aliments détectés: {[a['nom'] for a in aliments]}")
            calories = results.get('analyse_nutritionnelle', {}).get('total_nutrition', {}).get('calories', 0)
            print(f"Calories totales: {calories} kcal")

if __name__ == "__main__":
    verify_accuracy()
    verify_full_analysis()