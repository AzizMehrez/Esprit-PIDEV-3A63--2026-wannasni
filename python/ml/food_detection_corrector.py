"""
Intelligent Food Correction System - Améliore les détections défectueuses
Applique des règles de contexte pour corriger les erreurs courantes
"""

import logging
from python.ml.nutrition_knowledge import NUTRITION_DATA

logger = logging.getLogger(__name__)


class FoodDetectionCorrector:
    """
    Corrige les erreurs courantes de détection en appliquant des règles intelligentes.
    """
    
    # Aliments qui ne doivent jamais être détectés ensemble
    INCOMPATIBLE_PAIRS = [
        # (aliment1, aliment2, raison)
        ("pizza", "burger", "Deux plats complets différents"),
        ("pizza", "pates", "Deux féculents principaux"),
        ("burger", "lasagnes", "Deux plats complets différents"),
        ("couscous", "riz", "Deux féculents principaux"),
        ("pates_completes", "riz", "Deux féculents principaux"),
    ]
    
    # Aliments secondaires acceptables pour chaque principal
    ACCEPTABLE_SECONDARIES = {
        "pizza": ["salade_verte", "legume", "fruit"],
        "burger": ["frites", "boisson"],
        "pates": ["legume", "proteine"],
        "riz": ["legume", "proteine"],
        "poulet_grille": ["legume", "feculent", "sauce"],
        "steak_boeuf": ["legume", "feculent", "sauce"],
        "couscous": ["legume", "proteine_vegetale"],
    }
    
    # Combinaisons plausibles
    PLAUSIBLE_COMBINATIONS = [
        # (category1, category2, plausibility_score) où score 0-100
        ("proteine", "legume", 95),      # Très plausible
        ("proteine", "feculent", 95),    # Très plausible
        ("legume", "feculent", 90),      # Généralement plausible
        ("fruit", "legume", 85),         # Salade de fruits ou détox
        ("fruit", "sauce", 30),          # Peu plausible
        ("dessert", "proteine", 20),     # Peu plausible
        ("boisson", "fruit", 70),        # Smoothie possible
    ]
    
    def __init__(self):
        self.corrections_applied = []
    
    def correct_detections(self, foods, debugger=None):
        """
        Corriger les détections défectueuses.
        
        Returns:
            Liste corrigée des aliments
        """
        if not foods:
            return foods
        
        original_foods = [f.copy() for f in foods]
        corrected_foods = foods.copy()
        
        # Appliquer les corrections
        corrected_foods = self._remove_incompatible_pairs(corrected_foods, debugger)
        corrected_foods = self._boost_confidence_if_plausible(corrected_foods, debugger)
        corrected_foods = self._penalize_confidence_if_implausible(corrected_foods, debugger)
        corrected_foods = self._filter_low_confidence_outliers(corrected_foods, debugger)
        
        # Logger les changements
        if len(corrected_foods) != len(original_foods):
            logger.info(f"Corrections: {len(original_foods)} → {len(corrected_foods)} aliments")
            if debugger:
                debugger.log_warning(
                    "CORRECTIONS_APPLIED",
                    f"Nombre d'aliments modifié de {len(original_foods)} à {len(corrected_foods)}",
                    {"original": [f['nom'] for f in original_foods],
                     "corrected": [f['nom'] for f in corrected_foods]}
                )
        
        return corrected_foods
    
    def _remove_incompatible_pairs(self, foods, debugger=None):
        """
        Supprimer les paires d'aliments incompatibles.
        Garde le plus confiant, supprime l'autre.
        """
        removed = []
        
        for main1, main2, reason in self.INCOMPATIBLE_PAIRS:
            food1 = next((f for f in foods if f['nom'] == main1), None)
            food2 = next((f for f in foods if f['nom'] == main2), None)
            
            if food1 and food2:
                # Supprimer le moins confiant
                to_remove = food2 if food1.get('confiance', 0) > food2.get('confiance', 0) else food1
                removed.append(to_remove['nom'])
                foods = [f for f in foods if f['nom'] != to_remove['nom']]
                
                if debugger:
                    debugger.log_warning(
                        "INCOMPATIBLE_PAIR",
                        f"{main1} + {main2}: {reason}. Suppression de {to_remove['nom']}",
                        {"kept": (food1 if to_remove == food2 else food2)['nom'],
                         "kept_confidence": (food1 if to_remove == food2 else food2).get('confiance')}
                    )
                
                logger.info(f"Suppression aliment incompatible: {to_remove['nom']} ({reason})")
        
        return foods
    
    def _boost_confidence_if_plausible(self, foods, debugger=None):
        """
        Augmenter la confiance si la combinaison est très plausible.
        """
        if len(foods) < 2:
            return foods
        
        for food in foods:
            category = NUTRITION_DATA.get(food['nom'], {}).get('categorie', 'unknown')
            
            # Vérifier plausibilité avec les autres aliments
            plausibility_bonus = 0.0
            
            for other_food in foods:
                if other_food['nom'] == food['nom']:
                    continue
                
                other_category = NUTRITION_DATA.get(other_food['nom'], {}).get('categorie', 'unknown')
                
                # Chercher la paire dans PLAUSIBLE_COMBINATIONS
                for cat1, cat2, plausibility_score in self.PLAUSIBLE_COMBINATIONS:
                    if ((cat1 == category and cat2 == other_category) or
                        (cat1 == other_category and cat2 == category)):
                        plausibility_bonus = max(plausibility_bonus, plausibility_score / 100.0 * 0.05)
            
            if plausibility_bonus > 0:
                old_conf = food.get('confiance', 0)
                new_conf = min(0.99, old_conf + plausibility_bonus)
                
                if new_conf > old_conf:
                    food['confiance'] = new_conf
                    food['plausibility_boost'] = plausibility_bonus
                    
                    if debugger:
                        debugger.log_detection_step(
                            "CONFIDENCE_BOOST",
                            food['nom'],
                            new_conf,
                            f"Combinaison plausible (+{plausibility_bonus:.3f})"
                        )
        
        return foods
    
    def _penalize_confidence_if_implausible(self, foods, debugger=None):
        """
        Réduire la confiance si la combinaison est peu plausible.
        """
        if len(foods) < 2:
            return foods
        
        for food in foods:
            category = NUTRITION_DATA.get(food['nom'], {}).get('categorie', 'unknown')
            
            # Vérifier implausibilité
            min_plausibility = 100.0
            implausible_pairs = []
            
            for other_food in foods:
                if other_food['nom'] == food['nom']:
                    continue
                
                other_category = NUTRITION_DATA.get(other_food['nom'], {}).get('categorie', 'unknown')
                
                for cat1, cat2, plausibility_score in self.PLAUSIBLE_COMBINATIONS:
                    if ((cat1 == category and cat2 == other_category) or
                        (cat1 == other_category and cat2 == category)):
                        min_plausibility = min(min_plausibility, plausibility_score)
                        break
                
                # Si pas trouvé dans PLAUSIBLE_COMBINATIONS, c'est peu plausible
                else:
                    min_plausibility = min(min_plausibility, 20)
                    implausible_pairs.append(other_food['nom'])
            
            # Appliquer pénalité
            if min_plausibility < 50 and implausible_pairs:
                penalty = (100 - min_plausibility) / 1000.0  # 0.5 pour score 0, etc
                old_conf = food.get('confiance', 0)
                new_conf = max(0.0, old_conf - penalty)
                
                if new_conf < old_conf:
                    food['confiance'] = new_conf
                    food['plausibility_penalty'] = penalty
                    
                    logger.info(
                        f"Pénalité confiance {food['nom']}: "
                        f"{old_conf:.3f} → {new_conf:.3f} "
                        f"(implausible avec {implausible_pairs})"
                    )
                    
                    if debugger:
                        debugger.log_warning(
                            "IMPLAUSIBLE_COMBINATION",
                            f"{food['nom']} peu compatible avec {implausible_pairs}",
                            {"penalty": penalty, "new_confidence": new_conf}
                        )
        
        return foods
    
    def _filter_low_confidence_outliers(self, foods, debugger=None):
        """
        Filtrer les aliments à très basse confiance qui sont des outliers.
        """
        if len(foods) <= 1:
            return foods
        
        # Calculer confiance moyenne
        confidences = [f.get('confiance', 0) for f in foods]
        avg_conf = sum(confidences) / len(confidences)
        max_conf = max(confidences)
        
        # Threshold: food with conf < (avg_conf - 0.2) AND < 0.45 est suspect
        filtered = []
        removed = []
        
        for food in foods:
            conf = food.get('confiance', 0)
            
            # Seuil adaptatif: trop loin du meilleur
            if conf < avg_conf - 0.2 and conf < 0.45:
                removed.append(food['nom'])
                
                if debugger:
                    debugger.log_warning(
                        "OUTLIER_DETECTED",
                        f"{food['nom']} has low confidence ({conf:.3f}) - removed",
                        {"average_confidence": avg_conf, "max_confidence": max_conf}
                    )
                
                logger.info(
                    f"Suppression outlier: {food['nom']} "
                    f"(conf: {conf:.3f} << avg: {avg_conf:.3f})"
                )
            else:
                filtered.append(food)
        
        return filtered


class ConfidenceAdjustmentFactory:
    """
    Factory pour créer des ajustements de confiance basés sur des critères.
    """
    
    @staticmethod
    def adjust_for_source(food, source):
        """Ajuster confiance basée sur la source"""
        source_weights = {
            "similarity": 1.10,  # +10%
            "similarity_alt": 0.95,  # -5%
            "cnn": 0.90,  # -10%
            "fusion_sim+cnn": 1.0,
            "unknown": 0.85,  # -15%
        }
        
        weight = source_weights.get(source, 1.0)
        food['confiance'] = min(0.99, food.get('confiance', 0) * weight)
        food['source_adjustment'] = weight
        
        return food
    
    @staticmethod
    def adjust_for_image_quality_indicators(food, image_quality_score):
        """
        Ajuster confiance basée sur la qualité de l'image.
        image_quality_score: 0-100 (100 = excellente)
        """
        if image_quality_score < 50:
            penalty = (100 - image_quality_score) / 500.0
            food['confiance'] = max(0.0, food.get('confiance', 0) - penalty)
            food['image_quality_penalty'] = penalty
        
        return food
