#!/usr/bin/env python3
"""
REFONTE ML: Détection Intelligente Aliment Seul vs Plat Complet

NOUVELLE STRATÉGIE:
- Détecteur 1: Est-ce un ALIMENT SIMPLE ou un PLAT COMPLET?
- Si SIMPLE: Retourner CET aliment seul
- Si COMPLET: Retourner TOUS les aliments du plat

AVANTAGES:
✓ Chocolat → "Chocolat" (pas "pâtes + glace")
✓ Pâtes bolognaise → "Pâtes + Viande + Sauce" (pas 5 trucs random)
✓ Pomme → "Pomme" (pas mélange)
✓ Pizza complète → "Pâte + Sauce + Fromage + Topping"
"""

import numpy as np
from dataclasses import dataclass
from typing import List, Tuple

@dataclass
class DetectionStrategy:
    """Stratégie de détection selon le type d'aliment"""
    food_type: str  # 'simple' ou 'complete_dish'
    primary_food: str  # Aliment principal détecté
    secondary_foods: List[str]  # Aliments secondaires (pour plats complets)
    confidence: float  # Confiance globale
    reasoning: str  # Explication


class DishTypeClassifier:
    """
    Classifie si une image contient:
    - Simple food: Un seul aliment (chocolat, pomme, yaourt)
    - Complete dish: Un plat complet (pâtes, burger, pizza)
    """
    
    # Aliments SIMPLES (toujours seuls)
    SIMPLE_FOODS = {
        'chocolat', 'bonbon', 'biscuit', 'gâteau', 'crème brûlée',
        'les sucres', 'sucres', 'candies', 'sweets',  # ← NOUVEAU
        'pomme', 'banane', 'orange', 'fraise', 'raisin', 'poire',
        'fromage', 'yaourt', 'crème', 'dessert',
        'noix', 'amande', 'noisette', 'cacahuète',
        'oeuf', 'oeufs brouillés', 'omelette',
        'pain', 'sandwich',
        'jus', 'lait', 'yogurt drink'
    }
    
    # Plats COMPLETS (détecte tous les ingrédients)
    COMPLETE_DISHES = {
        'spaghetti bolognaise': ['pâtes', 'viande', 'sauce tomate'],
        'lasagnes': ['pâtes', 'viande', 'béchamel', 'fromage'],
        'pizza': ['pâte', 'sauce tomate', 'fromage', 'toppings'],
        'burger': ['pain', 'viande', 'fromage', 'legumes'],
        'couscous': ['couscous', 'viande', 'légumes'],
        'riz frit': ['riz', 'oeuf', 'légumes'],
        'poisson grillé': ['poisson', 'légumes', 'sauce'],
        'poulet rôti': ['poulet', 'légumes', 'sauce'],
        'steak frites': ['steak', 'frites', 'sauce'],
        'salade composée': ['légumes', 'protéine', 'vinaigrette'],
    }
    
    def classify(self, primary_detection: str, confidence: float) -> Tuple[str, str]:
        """
        Classifie le type de détection
        
        Args:
            primary_detection: Aliment principal détecté
            confidence: Confiance de détection (0.0-1.0)
            
        Returns:
            (food_type, reasoning)
            food_type: 'simple' ou 'complete_dish'
        """
        
        primary_lower = primary_detection.lower()
        
        # Vérifier si c'est un aliment simple
        for simple in self.SIMPLE_FOODS:
            if simple in primary_lower:
                return 'simple', f"Aliment simple détecté: {primary_detection}"
        
        # Vérifier si c'est un plat complet connu
        for dish, ingredients in self.COMPLETE_DISHES.items():
            if dish in primary_lower:
                return 'complete_dish', f"Plat complet détecté: {dish} → chercher ingrédients"
        
        # Par défaut: considérer comme simple si confiance élevée
        if confidence > 0.65:
            return 'simple', f"Détection fiable → aliment seul"
        else:
            return 'unknown', f"Ambigüe - confiance insuffisante ({confidence:.2f})"
    
    def get_expected_ingredients(self, dish_name: str) -> List[str]:
        """Retourner les ingrédients attendus d'un plat"""
        dish_lower = dish_name.lower()
        for dish, ingredients in self.COMPLETE_DISHES.items():
            if dish in dish_lower:
                return ingredients
        return []


class IntelligentFoodDetectionStrategy:
    """
    Nouvelle stratégie de détection:
    1. Détecter l'aliment principal
    2. Classifier: aliment seul ou plat complet?
    3. Si seul: retourner cet aliment
    4. Si complet: chercher TOUS les ingrédients du plat
    """
    
    def __init__(self):
        self.classifier = DishTypeClassifier()
    
    def detect_with_strategy(self, image_features: dict, raw_detections: list) -> DetectionStrategy:
        """
        Détecte avec la nouvelle stratégie
        
        Args:
            image_features: Features de l'image (couleurs, texture, etc)
            raw_detections: Détections brutes du CNN + Similarity
            
        Returns:
            DetectionStrategy avec logique appliquée
        """
        
        if not raw_detections:
            return DetectionStrategy(
                food_type='unknown',
                primary_food='Aucun aliment détecté',
                secondary_foods=[],
                confidence=0.0,
                reasoning="Aucune détection du tout"
            )
        
        # Prendre la meilleure détection
        primary = raw_detections[0]
        primary_name = primary.get('name', '?')
        primary_conf = primary.get('confidence', 0.0)
        
        # Classifier
        food_type, reasoning = self.classifier.classify(primary_name, primary_conf)
        
        if food_type == 'simple':
            # ALIMENT SEUL: retourner seulement cet aliment
            return DetectionStrategy(
                food_type='simple',
                primary_food=primary_name,
                secondary_foods=[],
                confidence=primary_conf,
                reasoning=f"Aliment simple détecté seul: {primary_name} ({primary_conf:.2f})"
            )
        
        elif food_type == 'complete_dish':
            # PLAT COMPLET: chercher TOUS les ingrédients
            expected_ingredients = self.classifier.get_expected_ingredients(primary_name)
            
            # Chercher chaque ingrédient dans les détections brutes
            found_ingredients = []
            for ingredient in expected_ingredients:
                # Chercher dans raw_detections
                for det in raw_detections:
                    if ingredient.lower() in det.get('name', '').lower():
                        found_ingredients.append(det.get('name'))
                        break
                else:
                    # Pas trouvé, ajouter comme "absent"
                    pass
            
            # Ordre: aliment principal + ingrédients trouvés
            all_foods = [primary_name] + found_ingredients
            
            # Calculer confiance moyenne
            avg_conf = np.mean([primary_conf] + [d.get('confidence', 0) for d in raw_detections[:3]])
            
            return DetectionStrategy(
                food_type='complete_dish',
                primary_food=primary_name,
                secondary_foods=found_ingredients,
                confidence=avg_conf,
                reasoning=f"Plat complet: {primary_name} détecté avec {len(found_ingredients)} ingrédient(s)"
            )
        
        else:
            # AMBIGÜE
            return DetectionStrategy(
                food_type='unknown',
                primary_food=primary_name,
                secondary_foods=[],
                confidence=0.0,
                reasoning=f"Détection ambigüe ({primary_conf:.2f}) - confiance insuffisante"
            )
    
    def format_output(self, strategy: DetectionStrategy) -> dict:
        """Formater le résultat pour l'API"""
        
        if strategy.food_type == 'unknown':
            return {
                "detected": False,
                "message": "Confiance insuffisante pour détecter cet aliment",
                "foods": [],
                "strategy": strategy.food_type,
                "reasoning": strategy.reasoning
            }
        
        # Construire la liste des aliments avec le format complet
        foods = []
        
        # Aliment principal
        foods.append({
            'nom': strategy.primary_food,
            'name': strategy.primary_food,  # Compatibilité
            'type': 'primary',
            'strategy': strategy.food_type,
            'confiance': strategy.confidence,
            'categorie': 'food',  # Placeholder - sera overridé par le système
            'source': 'intelligent_strategy'
        })
        
        # Aliments secondaires (pour plats complets)
        for secondary in strategy.secondary_foods:
            foods.append({
                'nom': secondary,
                'name': secondary,  # Compatibilité
                'type': 'secondary',
                'strategy': strategy.food_type,
                'confiance': strategy.confidence * 0.85,  # Slightly lower confidence for secondaries
                'categorie': 'food',  # Placeholder
                'source': 'intelligent_strategy'
            })
        
        return {
            "detected": True,
            "foods": foods,
            "strategy": strategy.food_type,
            "reasoning": strategy.reasoning,
            "confidence": strategy.confidence,
            "message": f"Détection {strategy.food_type}: {strategy.reasoning}"
        }


def demonstrate():
    """Démo de la nouvelle stratégie"""
    
    print("\n" + "="*80)
    print("[*] NOUVELLE STRATEGIE DE DETECTION")
    print("="*80)
    
    strategy_engine = IntelligentFoodDetectionStrategy()
    
    # Test 1: Chocolat seul
    print("\n[TEST 1] Chocolat Seul")
    print("-" * 80)
    
    raw_dets = [
        {'name': 'Chocolat', 'confidence': 0.72},
    ]
    
    result = strategy_engine.detect_with_strategy({}, raw_dets)
    output = strategy_engine.format_output(result)
    
    print(f"Détection brute: {raw_dets[0]['name']} (conf={raw_dets[0]['confidence']:.2f})")
    print(f"Classifier: {result.food_type}")
    print(f"Output:")
    for food in output.get('foods', []):
        print(f"  - {food['name']} ({food['type']})")
    print(f"Message: {output['message']}")
    
    # Test 2: Pâtes bolognaise (plat complet)
    print("\n[TEST 2] Pâtes Bolognaise (Plat Complet)")
    print("-" * 80)
    
    raw_dets = [
        {'name': 'Spaghetti Bolognaise', 'confidence': 0.68},
        {'name': 'Viande', 'confidence': 0.55},
        {'name': 'Pâtes', 'confidence': 0.63},
        {'name': 'Sauce tomate', 'confidence': 0.52},
    ]
    
    result = strategy_engine.detect_with_strategy({}, raw_dets)
    output = strategy_engine.format_output(result)
    
    print(f"Détection brute: {len(raw_dets)} items")
    print(f"Classifier: {result.food_type}")
    print(f"Ingrédients attendus: {strategy_engine.classifier.get_expected_ingredients('spaghetti bolognaise')}")
    print(f"Output:")
    for food in output.get('foods', []):
        label = "[PRIMARY]" if food['type'] == 'primary' else "[INGREDIENT]"
        print(f"  {label}: {food['name']}")
    print(f"Message: {output['message']}")
    
    # Test 3: Pomme seule
    print("\n[TEST 3] Pomme Seule")
    print("-" * 80)
    
    raw_dets = [
        {'name': 'Pomme', 'confidence': 0.75},
    ]
    
    result = strategy_engine.detect_with_strategy({}, raw_dets)
    output = strategy_engine.format_output(result)
    
    print(f"Détection brute: {raw_dets[0]['name']} (conf={raw_dets[0]['confidence']:.2f})")
    print(f"Classifier: {result.food_type}")
    print(f"Output:")
    for food in output.get('foods', []):
        print(f"  ✓ {food['name']}")
    print(f"Message: {output['message']}")
    
    # Test 4: Confiance trop basse (ambigüe)
    print("\n4️⃣ TEST: Confiance trop Basse (Ambigüe)")
    print("-" * 80)
    
    raw_dets = [
        {'name': 'Quelque chose', 'confidence': 0.35},
    ]
    
    result = strategy_engine.detect_with_strategy({}, raw_dets)
    output = strategy_engine.format_output(result)
    
    print(f"Détection brute: {raw_dets[0]['name']} (conf={raw_dets[0]['confidence']:.2f})")
    print(f"Classifier: {result.food_type}")
    print(f"Output:")
    print(f"  Status: {output['detected']}")
    print(f"  Message: {output['message']}")
    
    print("\n" + "="*80)
    print("✅ NOUVELLE STRATÉGIE DÉMONTRÉE")
    print("="*80)
    print("""
AVANTAGES:
  • Chocolat → "Chocolat" seul (pas "pâtes + glace")
  • Pâtes → "Pâtes + Viande + Sauce" (tous les ingrédients)
  • Clair: aliment seul vs plat complet
  • Pas de mélange chaotique
  • Prêt pour entraînement avec données chocolat
    """)


if __name__ == '__main__':
    demonstrate()
