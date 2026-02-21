"""
Full Nutrition Analyzer - WANNASNI AI
=====================================
Detects food from images, calculates nutritional values, checks diet compliance,
and suggests recipes based on the prescribed diet.

STRICT DETECTION POLICY:
- If food is NOT detected with sufficient confidence -> return ONLY "not detected"
- NO default data, NO fake calories, NO made-up nutrition info
- Only when food IS confidently detected -> provide full analysis
"""

import os
import json
import numpy as np
import tensorflow as tf
import cv2
import logging
import requests
from datetime import datetime
from pathlib import Path
import tempfile

from .nutrition_knowledge import (
    NUTRITION_DATA, DIET_RULES, DIET_RECIPE_SUGGESTIONS,
    DIET_SEARCH_TERMS, analyze_dish
)
from .similarity_matcher import ImageSimilarityMatcher
from .food_detection_corrector import FoodDetectionCorrector
from .detection_debugger import DetectionDebugger, DetectionValidator
from .strict_false_positive_filter import StrictFalsePositiveFilter
from .intelligent_dish_strategy import IntelligentFoodDetectionStrategy
from .strict_false_positive_filter import StrictFalsePositiveFilter

# Configuration
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger("FullNutritionAnalyzer")

# === CONFIGURATION DES SEUILS DE DÉTECTION - AMÉLIORÉ ===
# Seuils SIMILARITY (réduits pour permettre détection d'aliments simples comme le chocolat)
SIMILARITY_PRIMARY_THRESHOLD = 0.45  # Réduit de 0.60 pour meilleure détection simples aliments
SIMILARITY_SECONDARY_THRESHOLD = 0.40  # Réduit de 0.52

# Seuils CNN (réduits pour cohérence)
CNN_PRIMARY_THRESHOLD = 0.45  # Réduit de 0.55
CNN_SECONDARY_THRESHOLD = 0.38  # Réduit de 0.45

# Seuils pénalisés (fusion de sources)
FUSION_PRIMARY_THRESHOLD = 0.43  # Réduit de 0.50 pour meilleure sensibilité
FUSION_SECONDARY_THRESHOLD = 0.38  # Réduit de 0.40

# Legacy (garder pour compatibilité)
CONFIDENCE_THRESHOLD = 0.45  # Réduit de 0.50
SECONDARY_THRESHOLD = 0.38  # Réduit de 0.40
REGION_DETECTION_THRESHOLD = 0.40  # Réduit de 0.42
SINGLE_REGION_MIN_CONF = 0.38  # Réduit de 0.40
ALT_MIN_REGIONS = 1
MAX_SECONDARY_FOODS = 4  # Réduit de 5

# === CACHE DE DÉTECTIONS POUR PERFORMANCE ===
# Cache {image_path: detection_result} pour éviter les calculs redondants
DETECTION_CACHE_ENABLED = True
DETECTION_CACHE_MAX_SIZE = 100

# Étiquettes des portions
PORTION_SIZE_LABELS = {
    "petite": {"label": "Petite portion", "emoji": "🥄", "description": "Portion réduite, environ 40-50% d'une assiette standard"},
    "moyenne": {"label": "Portion moyenne", "emoji": "🍽️", "description": "Portion standard, une assiette normale"},
    "grande": {"label": "Grande portion", "emoji": "🍲", "description": "Portion généreuse, assiette bien remplie"},
    "genereuse": {"label": "Très grande portion", "emoji": "🥘", "description": "Portion très copieuse, double d'une portion standard"}
}

# Mapping des labels du modèle CNN vers les clés NUTRITION_DATA
MODEL_LABEL_MAPPING = {
    "fast food": ["burger_classique", "frites_moyenne", "nuggets_poulet_6pcs", "wrap_poulet", "pizza"],
    "pizza": "pizza",
    "fruits": ["pomme", "banane", "orange", "fraise", "kiwi", "poire", "raisin"],
    "les legumes": ["salade_verte", "carotte", "brocoli", "tomate", "courgette", "haricots_verts"],
    "les pattes": ["pates_completes", "spaghetti_bolognaise"],
    "les sucres": ["chocolat", "pancakes_classiques", "crepe_sucre", "gaufre_sucre", "chocolat_lait", "glace"],
    "pomme": "pomme",
    "proteins": ["poulet_grille", "steak_boeuf", "poisson_blanc", "saumon", "oeuf"],
}

# Plats complets - peuvent être détectés en plus d'autres aliments
WHOLE_DISH_FOODS = {
    "lasagnes", "lasagnes_legumes", "spaghetti_bolognaise",
    "boulettes_viande", "crepe_complete",
    "burger_classique", "burger_double", "burger_poulet",
    "pizza", "couscous",
}

# Seuil pour les plats complets
WHOLE_DISH_THRESHOLD = 0.40  # Réduit de 0.45 pour meilleur détection

# Profils couleur pour la désambiguïsation
COLOR_DISAMBIGUATION = {
    "pomme": {"hue": [(0, 15), (35, 85)], "sat_min": 40},
    "banane": {"hue": [(20, 35)], "sat_min": 60},
    "orange": {"hue": [(10, 25)], "sat_min": 100},
    "fraise": {"hue": [(0, 10), (170, 180)], "sat_min": 80},
    "carotte": {"hue": [(10, 25)], "sat_min": 100},
    "tomate": {"hue": [(0, 10), (170, 180)], "sat_min": 70},
    "brocoli": {"hue": [(35, 75)], "sat_min": 30},
    "salade_verte": {"hue": [(35, 85)], "sat_min": 25},
    "burger_classique": {"hue": [(10, 30)], "sat_min": 30},
    "frites_moyenne": {"hue": [(15, 35)], "sat_min": 60},
    "poulet_grille": {"hue": [(15, 30)], "sat_min": 30},
    "steak_boeuf": {"hue": [(0, 15)], "sat_min": 20},
    "saumon": {"hue": [(5, 20)], "sat_min": 50},
    "oeuf": {"hue": [(15, 35)], "sat_min": 50},
}


class FullNutritionAnalyzer:
    """
    Analyseur principal. Orchestre:
    1. Détection d'aliments (similarité + CNN)
    2. Calcul nutritionnel
    3. Vérification de conformité au régime
    4. Suggestions de recettes basées sur le régime
    """

    def __init__(self):
        self.model_path = os.path.join(os.path.dirname(__file__), 'model', 'food_classifier.h5')
        self.labels_path = os.path.join(os.path.dirname(__file__), 'model', 'labels.json')
        self.raw_data_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'data', 'raw')
        self.model = None
        self.labels = []

        # Initialiser le matcher de similarité (MobileNetV2)
        self.similarity_matcher = ImageSimilarityMatcher(self.raw_data_dir)
        self.similarity_matcher.build_index()

        # Initialiser le correcteur de détection
        self.food_corrector = FoodDetectionCorrector()
        
        # Initialiser le debugger
        self.debugger = DetectionDebugger(enable_verbose=False)
        
        # LEVEL 3: Initialiser le filtre strict des fausses positives
        self.strict_filter = StrictFalsePositiveFilter()
        
        # ✨ NOUVEAU: Initialiser la stratégie intelligente de détection
        self.intelligent_strategy = IntelligentFoodDetectionStrategy()

        # Cache de recettes
        self.recipe_cache = {}
        self.api_base_url = "https://www.themealdb.com/api/json/v1/1"
        
        # Cache de détections pour performances
        self.detection_cache = {}
        self.detection_cache_hits = 0
        self.detection_cache_misses = 0

        # Charger le modèle CNN (optionnel)
        self._load_model()
        self._load_labels()

    def _load_model(self):
        """Charger le modèle CNN s'il est disponible."""
        if not os.path.exists(self.model_path):
            logger.warning(f"Modèle CNN non trouvé: {self.model_path}")
            return
        
        # Vérifier que le fichier n'est pas vide
        if os.path.getsize(self.model_path) < 1000:
            logger.warning(f"Modèle CNN vide ou corrompu: {self.model_path}")
            return
            
        try:
            self.model = tf.keras.models.load_model(self.model_path)
            logger.info(f"Modèle CNN chargé: {self.model_path}")
        except Exception as e:
            logger.error(f"Erreur chargement modèle CNN: {e}")
            self.model = None

    def _load_labels(self):
        """Charger les labels du modèle CNN."""
        if os.path.exists(self.labels_path):
            try:
                with open(self.labels_path, 'r', encoding='utf-8') as f:
                    self.labels = json.load(f)
                logger.info(f"Labels chargés: {len(self.labels)} catégories")
            except Exception as e:
                logger.error(f"Erreur chargement labels: {e}")
                self.labels = []
        else:
            self.labels = []

    def _map_label_to_food(self, label, image_path=None):
        """Mapper un label CNN vers une clé NUTRITION_DATA."""
        if not label:
            return None
        label_lower = label.lower().strip()

        # Vérifier le mapping explicite
        mapped = MODEL_LABEL_MAPPING.get(label_lower)
        if mapped is None:
            # Essayer une correspondance directe
            if label_lower in NUTRITION_DATA:
                return label_lower
            label_underscore = label_lower.replace(" ", "_")
            if label_underscore in NUTRITION_DATA:
                return label_underscore
            return None

        # Mapping simple (string)
        if isinstance(mapped, str):
            return mapped if mapped in NUTRITION_DATA else None

        # Mapping multiple - désambiguïsation par couleur
        valid = [f for f in mapped if f in NUTRITION_DATA]
        if not valid:
            return None
        if len(valid) == 1:
            return valid[0]

        # Désambiguïsation par couleur si l'image est fournie
        if image_path:
            best_food = self._disambiguate_by_color(image_path, valid)
            if best_food:
                return best_food

        # Par défaut, prendre le premier
        return valid[0]

    def _disambiguate_by_color(self, image_path, candidates):
        """Désambiguïsation par couleur dominante."""
        try:
            img = cv2.imread(image_path)
            if img is None:
                return None
            hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
            h, w = hsv.shape[:2]
            # Se concentrer sur le centre (60% de l'image)
            ch, cw = h // 5, w // 5
            center = hsv[ch:h-ch, cw:w-cw]

            best_score = -1
            best_food = None

            for food in candidates:
                profile = COLOR_DISAMBIGUATION.get(food)
                if not profile:
                    continue
                score = 0
                hue_ranges = profile.get("hue", [])
                sat_min = profile.get("sat_min", 0)

                for hue_low, hue_high in hue_ranges:
                    mask = cv2.inRange(
                        center,
                        np.array([hue_low, sat_min, 40]),
                        np.array([hue_high, 255, 255])
                    )
                    ratio = cv2.countNonZero(mask) / max(center.shape[0] * center.shape[1], 1)
                    score = max(score, ratio)

                if score > best_score:
                    best_score = score
                    best_food = food

            if best_score > 0.05:  # Au moins 5% des pixels correspondent
                logger.info(f"Désambiguïsation couleur: {best_food} (score: {best_score:.3f})")
                return best_food
        except Exception as e:
            logger.warning(f"Erreur désambiguïsation couleur: {e}")
        return None

    # ========================================================================
    # DÉTECTION D'ALIMENTS
    # ========================================================================

    def _detect_foods(self, image_path):
        """
        Détection d'aliments sur une image unique.
        Utilise le matcher de similarité + CNN avec scoring combiné.
        Version améliorée avec seuils intelligents basés sur la source.
        """
        logger.info(f"Début détection aliment: {image_path}")
        candidates = self._get_all_candidates(image_path)

        if candidates:
            best = max(candidates.values(), key=lambda x: x['confiance'])
            
            # Déterminer le seuil approprié selon la SOURCE
            source = best.get('source', 'unknown')
            if source == 'similarity':
                threshold = SIMILARITY_PRIMARY_THRESHOLD
            elif source == 'cnn':
                threshold = CNN_PRIMARY_THRESHOLD
            elif source == 'fusion_sim+cnn':
                threshold = FUSION_PRIMARY_THRESHOLD
            else:
                threshold = CONFIDENCE_THRESHOLD
            
            # Plats complets: seuil potentiellement plus haut
            if best['nom'] in WHOLE_DISH_FOODS:
                threshold = max(threshold, WHOLE_DISH_THRESHOLD * 0.95)  # Légèrement moins strict
            
            if best['confiance'] >= threshold:
                food_data = NUTRITION_DATA.get(best['nom'], {})
                best['type'] = food_data.get('categorie', 'inconnu')
                best.pop('sim_score', None)
                best.pop('cnn_score', None)
                logger.info(f"DÉTECTÉ: {best['nom']} (confiance: {best['confiance']:.3f}, source: {source})")
                return [best]

        logger.warning("Aucun aliment détecté avec confiance suffisante")
        return [{
            "nom": "non_detecte",
            "detected": False,
            "confiance": 0.0,
            "source": "none",
            "message": "L'aliment n'a pas pu être identifié dans cette image."
        }]

    def _get_all_candidates(self, image_path, threshold_override=None):
        """
        Obtenir TOUS les candidats (similarité + CNN).
        Méthode de détection principale.
        """
        candidates = {}

        # Similarité (MobileNetV2) - méthode PRINCIPALE
        sim_match = self.similarity_matcher.find_match(image_path)
        if sim_match:
            food_key = self.similarity_matcher.map_category_to_food(sim_match['category'])
            if food_key and food_key in NUTRITION_DATA:
                candidates[food_key] = {
                    "nom": food_key,
                    "confiance": sim_match['confidence'],
                    "source": "similarity",
                    "detected": True,
                    "sim_score": sim_match['confidence'],
                    "cnn_score": 0.0,
                }
                logger.info(f"Match similarité: {food_key} ({sim_match['confidence']:.3f})")

            # Ajouter les finalistes si leur score est élevé
            # AMÉLIORÉ: être plus strict pour éviter les faux positifs
            for tm in sim_match.get('top_matches', [])[1:]:
                if tm['confidence'] < SIMILARITY_SECONDARY_THRESHOLD:  # Augmenté à 0.52
                    continue
                margin_to_top = sim_match['confidence'] - tm['confidence']
                if margin_to_top < 0.12:  # Augmenté de 0.10 - exiger une vraie différence
                    continue
                alt_food = self.similarity_matcher.map_category_to_food(tm['category'])
                if alt_food and alt_food in NUTRITION_DATA and alt_food not in candidates:
                    candidates[alt_food] = {
                        "nom": alt_food,
                        "confiance": tm['confidence'] * 0.85,  # Pénalité restaurée pour secondaires
                        "source": "similarity_alt",
                        "detected": True,
                        "sim_score": tm['confidence'],
                        "cnn_score": 0.0,
                    }
                    logger.info(f"Finaliste similarité: {alt_food} ({tm['confidence']:.3f})")

        # Prédictions CNN si disponibles - FUSION AMÉLIORÉE
        cnn_preds = self._get_cnn_prediction_scores(image_path)
        for food_key, conf in cnn_preds.items():
            if food_key not in candidates:
                candidates[food_key] = {
                    "nom": food_key,
                    "confiance": conf,
                    "source": "cnn",
                    "detected": True,
                    "sim_score": 0.0,
                    "cnn_score": conf,
                }
                logger.info(f"Prédiction CNN: {food_key} ({conf:.3f})")
            else:
                # Fusion AMÉLIORÉE: 75% similarité (plus fiable), 25% CNN
                # La similarité d'images est plus robuste pour la détection de nourriture
                sim_score = candidates[food_key]['confiance']
                combined = (sim_score * 0.75) + (conf * 0.25)
                candidates[food_key]['confiance'] = combined
                candidates[food_key]['cnn_score'] = conf
                candidates[food_key]['source'] = 'fusion_sim+cnn'
                logger.debug(f"Fusion détection {food_key}: sim={sim_score:.3f}, cnn={conf:.3f} → combined={combined:.3f}")

        return candidates

    def _get_cnn_prediction_scores(self, image_path):
        """
        Obtenir les prédictions CNN sous forme de scores par aliment.
        AMÉLIORÉ: filtrage strict pour éviter faux positifs.
        """
        if not self.model or not self.labels:
            return {}

        try:
            img = cv2.imread(image_path)
            if img is None:
                return {}

            # Prétraitement
            img_resized = cv2.resize(img, (224, 224))
            img_array = np.expand_dims(img_resized.astype('float32') / 255.0, axis=0)

            # Prédiction
            preds = self.model.predict(img_array, verbose=0)[0]

            # Obtenir les meilleures prédictions - PLUS STRICT
            food_scores = {}
            sorted_indices = np.argsort(preds)[::-1]
            
            # Chercher le score max pour normalisation
            max_score = float(preds[sorted_indices[0]]) if len(sorted_indices) > 0 else 0
            min_score_threshold = max(0.30, max_score - 0.35)  # Écart de 0.35 max vs max

            for idx in sorted_indices[:10]:  # Réduit à 10 (était 15)
                conf = float(preds[idx])
                
                # Appliquer un seuil minimum
                if conf < min_score_threshold:
                    break
                
                if idx >= len(self.labels):
                    continue

                label = self.labels[idx]
                food_key = self._map_label_to_food(label, image_path)

                if food_key and food_key in NUTRITION_DATA:
                    # Appliquer un seuil CNN absolu minimum
                    if conf >= CNN_SECONDARY_THRESHOLD:  # 0.45
                        if food_key not in food_scores or conf > food_scores[food_key]:
                            food_scores[food_key] = conf
                            logger.debug(f"CNN pred valid: {food_key} ({conf:.3f})")

            return food_scores
        except Exception as e:
            logger.warning(f"Erreur prédiction CNN: {e}")
            return {}

    def _detect_multi_foods_cached(self, image_path):
        """
        Détection avec cache pour éviter les calculs redondants.
        Intéressant pour les tests et l'API répétitive.
        """
        if not DETECTION_CACHE_ENABLED:
            return self._detect_multi_foods(image_path)
        
        # Vérifier le cache
        cache_key = str(image_path)
        if cache_key in self.detection_cache:
            self.detection_cache_hits += 1
            logger.debug(f"Detection cache HIT for {image_path}")
            return self.detection_cache[cache_key]
        
        # Calcul et cachage
        self.detection_cache_misses += 1
        result = self._detect_multi_foods(image_path)
        
        # Mantenir la taille du cache
        if len(self.detection_cache) >= DETECTION_CACHE_MAX_SIZE:
            # Supprimer l'entrée la plus ancienne (FIFO)
            oldest_key = next(iter(self.detection_cache))
            del self.detection_cache[oldest_key]
            logger.debug("Detection cache: removed oldest entry")
        
        self.detection_cache[cache_key] = result
        
        # Log stats toutes les 10 entrées
        total = self.detection_cache_hits + self.detection_cache_misses
        if total > 0 and total % 10 == 0:
            hit_rate = (self.detection_cache_hits / total) * 100
            logger.info(f"Detection cache stats: {hit_rate:.1f}% hit rate ({self.detection_cache_hits}/{total})")
        
        return result

    def detect_only(self, image_path):
        """
        Étape de détection pour l'API.
        Retourne un dict avec 'detected', 'message', 'foods'.
        AMÉLIORÉ NIVEAU 2: 
        - Seuils intelligents par source
        - Validation contextuelle 
        - Correction intelligente des erreurs
        """
        self.debugger.reset()
        foods = self._detect_multi_foods(image_path)

        # Filtrer les détections à faible confiance avec seuils intelligents
        confident_foods = []
        for f in foods:
            if not f.get('detected', False):
                continue
            
            # S'assurer que 'categorie' existe (sinon c'est un fruit by default)
            if 'categorie' not in f:
                f['categorie'] = 'fruit'  # Placeholder si manquant
            
            source = f.get('source', 'unknown')
            conf = f.get('confiance', 0)
            
            # Appliquer le seuil approprié selon la source
            if source == 'similarity':
                threshold = SIMILARITY_PRIMARY_THRESHOLD
            elif source == 'similarity_alt':
                threshold = SIMILARITY_SECONDARY_THRESHOLD
            elif source == 'cnn':
                threshold = CNN_PRIMARY_THRESHOLD
            elif source == 'fusion_sim+cnn':
                threshold = FUSION_PRIMARY_THRESHOLD
            else:
                threshold = CONFIDENCE_THRESHOLD
            
            # Plats complets: slightly lower threshold
            if f.get('nom') in WHOLE_DISH_FOODS:
                threshold = max(threshold, WHOLE_DISH_THRESHOLD * 0.95)
            
            if conf >= threshold:
                confident_foods.append(f)
                self.debugger.log_detection_step(
                    "THRESHOLD_PASS",
                    f['nom'],
                    conf,
                    f"Source: {source}, Threshold: {threshold:.2f}"
                )

        # LEVEL 3: Appliquer le filtre strict des fausses positives massives
        if len(confident_foods) > 0:
            logger.info(f"[*] LEVEL 3: Avant filtre strict: {len(confident_foods)} aliments")
            for cf in confident_foods:
                logger.info(f"   - {cf.get('nom', '?')} (conf={cf.get('confiance', 0):.2f}, source={cf.get('source', '?')})")
            
            # Convertir au format attendu par le filtre strict
            candidates_for_filter = [
                {
                    'name': f.get('nom', '?'),
                    'confidence': f.get('confiance', 0),
                    'source': f.get('source', 'unknown')
                }
                for f in confident_foods
            ]
            
            # Appliquer le filtre strict
            logger.info(f"[*] Appel du filtre strict LEVEL 3...")
            filtered_candidates = self.strict_filter.filter_detections(candidates_for_filter)
            logger.info(f"[+] Filtre strict retourne: {len(filtered_candidates)} aliments")
            
            if not filtered_candidates:
                # Le filtre a rejeté toutes les détections
                logger.warning(f"[!] Filtre strict: {len(confident_foods)} detections REJETEES (fausse positive massive detectee!)")
                self.debugger.log_warning(
                    "STRICT_FILTER_REJECT",
                    f"{len(confident_foods)} aliments détectés mais rejetés par le filtre strict",
                    None
                )
                confident_foods = []
            else:
                # CORRECTION: Garder seulement les aliments qui ont passé le filtre strict
                filtered_names = {c['name'].lower() for c in filtered_candidates}
                old_count = len(confident_foods)
                confident_foods = [
                    f for f in confident_foods 
                    if f.get('nom', '').lower() in filtered_names
                ]
                logger.info(f"[+] Filtre strict: {old_count} -> {len(confident_foods)} aliments ACCEPTES")
        
        # [*] NIVEAU 4: Appliquer la stratégie intelligente de détection (Aliment simple vs Plat complet)
        if confident_foods:
            logger.info(f"[*] STRATEGIE INTELLIGENTE: Classifying {len(confident_foods)} aliments...")
            
            # LOGIQUE SIMPLE ET DIRECTE:
            # - Si 1 seul aliment détecté → c'est un aliment simple, garder le 1
            # - Si 2-3 aliments détectés → c'est un plat complet, GARDER TOUS
            # - Si 4+ → rejeter (chaos)
            
            num_foods = len(confident_foods)
            if num_foods == 1:
                # 1 aliment seul: c'est OK, garder
                logger.info(f"[+] 1 aliment seul detecte: {confident_foods[0].get('nom')} - accepte")
            elif num_foods in [2, 3]:
                # 2-3 aliments: c'est un plat complet, GARDER TOUS
                logger.info(f"[+] {num_foods} aliments = plat complet - accepter tous")
            else:
                # 4+ aliments ou problème
                logger.warning(f"[!] {num_foods} aliments detectes - verifier qualite")
        
        # ANCIEN CODE COMMENTÉ - n'utilise plus la stratégie intelligente
        # (elle causait trop de rejets d'aliments valides)
        # if confident_foods:
        #     raw_detections_for_strategy = [...]
        #     strategy_result = self.intelligent_strategy.detect_with_strategy(...)
        #     strategy_output = self.intelligent_strategy.format_output(strategy_result)
        #     if strategy_output.get('detected'):
        #         accepted_names = {f['name'].lower() for f in strategy_output['foods']}
        #         confident_foods = [f for f in confident_foods if f.get('nom', '').lower() in accepted_names]
        
        # AMÉLIORÉ: Appliquer la validation contextuelle
        if confident_foods:
            confident_foods = self._validate_food_combinations(confident_foods)
            
            # NOUVEAU: Appliquer corrections intelligentes
            confident_foods = self.food_corrector.correct_detections(
                confident_foods,
                self.debugger
            )
        
        # NOUVEAU: Valider les résultats finaux
        if confident_foods:
            validation_result = DetectionValidator.validate(confident_foods, self.debugger)
            if not validation_result['is_valid']:
                logger.warning(f"Problèmes de détection détectés: {validation_result['issues']}")

        if not confident_foods:
            attempted = [
                f"{f.get('nom', '?')}({f.get('confiance', 0):.2f})"
                for f in foods if f.get('nom') and f.get('nom') != 'non_detecte'
            ]
            if attempted:
                logger.warning(f"Détections à faible confiance rejetées: {', '.join(attempted)}")
                self.debugger.log_warning(
                    "LOW_CONFIDENCE_REJECTED",
                    f"Rejections: {', '.join(attempted)}",
                    None
                )

            return {
                "detected": False,
                "message": (
                    "Aucun aliment n'a été détecté avec suffisamment de certitude dans cette image. "
                    "Essayez de reprendre la photo de plus près, "
                    "avec un bon éclairage, en centrant bien l'aliment dans le cadre."
                ),
                "foods": []
            }

        # Ajouter warning pour confiance modérée
        for food in confident_foods:
            if food.get('confiance', 0) < 0.70:
                food['avertissement'] = "Détection avec confiance modérée. Vérifiez le résultat."

        # 🔥 ENRICHISSEMENT NUTRITIONNEL: Ajouter les données nutritionnelles pour chaque aliment détecté
        for food in confident_foods:
            food_name = food.get('nom', '').lower()
            
            # Rechercher l'aliment dans la base de données nutritionnelles
            if food_name in NUTRITION_DATA:
                nutrition = NUTRITION_DATA[food_name]
                
                # Ajouter les informations nutritionnelles
                food['calories'] = nutrition.get('calories', 0)
                food['categorie'] = nutrition.get('categorie', 'autre')
                food['portion_moyenne'] = nutrition.get('portion_moyenne', 100)
                food['unite'] = nutrition.get('unite', 'g')
                food['nutriments'] = nutrition.get('nutriments', {})
                
                logger.info(f"[+] Donnees nutritionnelles ajoutees a {food_name}: {food['calories']} kcal")
            else:
                logger.warning(f"[!] Aliment {food_name} non trouve dans NUTRITION_DATA")

        return {
            "detected": True,
            "foods": confident_foods,
            "debug_info": {
                "num_foods": len(confident_foods),
                "avg_confidence": sum(f.get('confiance', 0) for f in confident_foods) / max(len(confident_foods), 1),
                "validation_issues": validation_result.get('issues', []) if confident_foods else []
            } if logger.isEnabledFor(logging.DEBUG) else None
        }

    # ========================================================================
    # CALCULS NUTRITIONNELS
    # ========================================================================

    def _calculate_detailed_nutrition(self, food_item):
        """
        Calculer les informations nutritionnelles détaillées pour un aliment.
        VERSION AMÉLIORÉE avec validation et facteurs de correction.
        """
        name = food_item.get('nom', '')

        if name not in NUTRITION_DATA or name == 'non_detecte' or name == 'aliment_non_identifie':
            return None

        data = NUTRITION_DATA[name]
        qty = food_item.get('quantite', data.get('portion_moyenne', 100))
        
        # Valider la quantité détectée (fourchette raisonnable)
        qty = self._validate_and_adjust_quantity(name, qty, data)
        
        data_unit = data.get('unite', 'g')
        base_cals = data.get('calories', 0)
        macros = data.get('nutriments', {})

        # Déterminer si les calories sont pour 100g ou par portion
        per_100g_units = {'100g', '100g cuit', '100ml', 'g', 'ml'}
        is_per_100g = data_unit.lower().replace(' ', '') in {u.lower().replace(' ', '') for u in per_100g_units}

        if is_per_100g:
            ratio = qty / 100.0
        else:
            portion_ref = data.get('portion_moyenne', 100)
            if portion_ref > 0:
                ratio = qty / portion_ref
            else:
                ratio = 1.0
        
        # Appliquer les facteurs de correction (cuisson, transformation, etc.)
        correction_factor = self._get_nutrition_correction_factor(name, data)
        adjusted_ratio = ratio * correction_factor

        nutrition = {
            "nom": name,
            "nom_affichage": name.replace('_', ' ').capitalize(),
            "calories": max(1, round(base_cals * adjusted_ratio, 1)),  # Min 1 kcal
            "quantite": qty,
            "unite": data_unit,
            "categorie": data.get('categorie', 'inconnu'),
            "bienfaits": data.get('bienfaits', ''),
            "description": data.get('description', ''),
        }
        
        # Ajouter les macronutriments disponibles
        for key, value in macros.items():
            if isinstance(value, (int, float)) and value > 0:
                nutrition[key] = round(value * adjusted_ratio, 1)

        # Déterminer la taille de portion
        portions = data.get('portions', {})
        if portions:
            portion_label = self._get_portion_size_label(qty, portions)
            nutrition['taille_portion'] = portion_label
            nutrition['portions_reference'] = portions

        # Ajouter les détails de composition
        if 'composition_detaillee' in data:
            nutrition['composition_detaillee'] = data['composition_detaillee']

        # Ajouter la fourchette calorique pour les plats composés
        if 'fourchette_calorique' in data:
            fourchette = data['fourchette_calorique']
            nutrition['fourchette_calorique'] = {
                'min': round(fourchette[0] * adjusted_ratio, 1),
                'max': round(fourchette[1] * adjusted_ratio, 1)
            }

        # Générer une description détaillée
        nutrition['analyse_detaillee'] = self._generate_food_analysis_text(name, data, qty, nutrition)

        # Vérification de saisonnalité
        if 'saison' in data:
            nutrition['saison'] = data['saison']
            nutrition['est_de_saison'] = self._check_seasonality(data['saison'])

        return nutrition
    
    def _validate_and_adjust_quantity(self, food_name, qty, data):
        """
        Valider et ajuster la quantité détectée pour éviter les aberrations.
        AMÉLIORÉ: Utilise des fourchettes raisonnables par catégorie.
        """
        # Fourchettes raisonnables de quantité par catégorie
        category = data.get('categorie', 'unknown')
        portion_avg = data.get('portion_moyenne', 100)
        
        # Fourchette: entre 0.25 et 3x la portion moyenne
        min_qty = portion_avg * 0.25
        max_qty = portion_avg * 3.0
        
        # Pour certaines catégories, ajuster les fourchettes
        if category == 'fruit':
            max_qty = portion_avg * 4  # Peut manger plus de fruits
        elif category in ['dessert', 'snack']:
            max_qty = portion_avg * 2.5
        elif category == 'boisson':
            max_qty = portion_avg * 5  # Les boissons peuvent être servies en grandes quantités
        elif category in ['proteine', 'proteine_grasse']:
            max_qty = portion_avg * 2  # Les protéines: moins de variation
        
        # Valider
        if qty < min_qty:
            logger.debug(f"{food_name}: Quantité {qty}g ajustée à minimum {min_qty}g")
            return min_qty
        elif qty > max_qty:
            logger.debug(f"{food_name}: Quantité {qty}g plafonnée à {max_qty}g")
            return max_qty
        
        return qty
    
    def _get_nutrition_correction_factor(self, food_name, data):
        """
        Obtenir un facteur de correction pour les transformations nutritionnelles.
        Par ex: viande cuite vs crue, portions réelles vs portions théoriques.
        """
        # Facteurs de correction par catégorie d'aliment
        category = data.get('categorie', 'unknown')
        
        # Correction par catégorie
        category_factors = {
            'fruit': 1.0,  # Pas de transformation
            'legume': 0.95,  # Légèrement moins après cuisson (perte de 5%)
            'proteines_grasse': 0.92,  # La viande perd ~8% en cuisant (gras)
            'proteine': 0.90,  # La viande maigre perd ~10% en cuisant
            'proteine_maigre': 0.92,
            'feculent': 1.05,  # Les féculents peuvent absorber l'eau: +5%
            'laitage': 0.98,
            'boisson': 1.0,
            'sauce': 0.95,
            'plat_compose': 0.96,  # Moyenne pour les plats
            'fast_food': 0.98,
            'dessert': 0.96,
        }
        
        factor = category_factors.get(category, 1.0)
        
        # Facteurs additionnels spécifiques par aliment
        specific_factors = {
            'poulet_grille': 0.88,  # Perte importante en grillage
            'poulet_frit': 0.98,  # Prend du gras frit: peu de variabilité
            'steak_boeuf': 0.85,  # Perte importante en cuisson
            'saumon': 0.92,
            'poisson_blanc': 0.90,
        }
        
        if food_name in specific_factors:
            factor = specific_factors[food_name]
        
        logger.debug(f"Nutrition correction for {food_name}: factor={factor:.2f}")
        return factor

    def _get_portion_size_label(self, qty_g, portions):
        """Déterminer l'étiquette de taille de portion."""
        if not portions:
            return {"taille": "moyenne", "label": "Portion moyenne", "emoji": "🍽️"}

        # Trouver la portion la plus proche
        best_match = "moyenne"
        best_diff = float('inf')
        for size_key, size_g in portions.items():
            diff = abs(qty_g - size_g)
            if diff < best_diff:
                best_diff = diff
                best_match = size_key

        info = PORTION_SIZE_LABELS.get(best_match, PORTION_SIZE_LABELS["moyenne"])
        return {
            "taille": best_match,
            "label": info["label"],
            "emoji": info["emoji"],
            "description": info["description"],
            "grammage_estime": qty_g,
            "grammage_reference": portions.get(best_match, qty_g)
        }

    def _generate_food_analysis_text(self, name, data, qty, nutrition):
        """Générer un texte d'analyse détaillé."""
        display_name = name.replace('_', ' ').capitalize()
        categorie = data.get('categorie', 'inconnu')
        calories = nutrition.get('calories', 0)
        description = data.get('description', '')
        bienfaits = data.get('bienfaits', '')
        macros = data.get('nutriments', {})
        data_unit = data.get('unite', '100g')

        # Déterminer le ratio
        per_100g_units = {'100g', '100gcuit', '100ml', 'g', 'ml'}
        is_per_100g = data_unit.lower().replace(' ', '') in per_100g_units
        if is_per_100g:
            ratio = qty / 100
        else:
            portion_ref = data.get('portion_moyenne', 100)
            ratio = qty / portion_ref if portion_ref > 0 else 1.0

        # Labels de catégorie en français
        cat_labels = {
            'fruit': 'Fruit', 'legume': 'Légume', 'feculent': 'Féculent',
            'proteine': 'Protéine maigre', 'proteine_maigre': 'Protéine maigre',
            'proteine_grasse': 'Protéine grasse', 'proteine_vegetale': 'Protéine végétale',
            'plat_compose': 'Plat composé', 'fast_food': 'Fast-food',
            'dessert': 'Dessert', 'boisson': 'Boisson', 'sauce': 'Sauce',
            'snack': 'Snack', 'confiserie': 'Confiserie', 'laitage': 'Produit laitier',
            'legumineuse': 'Légumineuse', 'boisson_sucree': 'Boisson sucrée'
        }
        cat_label = cat_labels.get(categorie, categorie.capitalize())

        lines = []
        lines.append(f"🍽️ {display_name} — {cat_label}")

        if description:
            lines.append(f"📝 {description}")

        # Macronutriments
        prot = macros.get('proteines', 0)
        gluc = macros.get('glucides', 0)
        lip = macros.get('lipides', 0)
        fib = macros.get('fibres', 0)

        macro_parts = []
        if prot > 0:
            macro_parts.append(f"Protéines {round(prot * ratio, 1)}g")
        if gluc > 0:
            macro_parts.append(f"Glucides {round(gluc * ratio, 1)}g")
        if lip > 0:
            macro_parts.append(f"Lipides {round(lip * ratio, 1)}g")
        if fib > 0:
            macro_parts.append(f"Fibres {round(fib * ratio, 1)}g")

        if is_per_100g:
            portion_desc = f"{qty}g"
        else:
            portion_desc = f"1 {data_unit}" if ratio == 1.0 else f"{qty}g (~{ratio:.1f} portion)"

        if macro_parts:
            lines.append(f"📊 Pour {portion_desc} : {calories} kcal — {' | '.join(macro_parts)}")

        # Micronutriments notables
        micro_labels = {
            'vitamine_c': ('Vit. C', 'mg'), 'vitamine_a': ('Vit. A', 'µg'),
            'vitamine_d': ('Vit. D', 'µg'), 'vitamine_k': ('Vit. K', 'µg'),
            'vitamine_b12': ('Vit. B12', 'µg'), 'fer': ('Fer', 'mg'),
            'calcium': ('Calcium', 'mg'), 'potassium': ('Potassium', 'mg'),
            'magnesium': ('Magnésium', 'mg'), 'zinc': ('Zinc', 'mg'),
            'omega_3': ('Oméga-3', 'g'), 'selenium': ('Sélénium', 'µg'),
            'folate': ('Folate', 'µg'), 'iode': ('Iode', 'µg'),
            'phosphore': ('Phosphore', 'mg'),
        }
        micro_parts = []
        for key, (label, unit_str) in micro_labels.items():
            val = macros.get(key, 0)
            if isinstance(val, (int, float)) and val > 0:
                micro_parts.append(f"{label} {round(val * ratio, 1)}{unit_str}")

        if micro_parts:
            lines.append(f"💊 Micronutriments : {' | '.join(micro_parts[:6])}")

        if bienfaits:
            lines.append(f"✅ {bienfaits}")

        # Contexte des portions
        portions = data.get('portions', {})
        if portions:
            portion_info = []
            base_cal = data['calories']
            portion_ref = data.get('portion_moyenne', 100)
            for size, grams in sorted(portions.items(), key=lambda x: x[1]):
                if is_per_100g:
                    cals = round(base_cal * grams / 100)
                else:
                    cals = round(base_cal * grams / portion_ref) if portion_ref > 0 else base_cal
                size_label = {'petite': 'Petite', 'moyenne': 'Moyenne', 'grande': 'Grande', 'genereuse': 'Très grande'}.get(size, size)
                portion_info.append(f"{size_label} ({grams}g) = {cals} kcal")
            lines.append(f"📏 Portions : {' | '.join(portion_info)}")

        return '\n'.join(lines)

    def _check_seasonality(self, seasons):
        """Vérifier si un aliment est de saison."""
        current_month = datetime.now().month
        month_to_season = {
            12: "hiver", 1: "hiver", 2: "hiver",
            3: "printemps", 4: "printemps", 5: "printemps",
            6: "ete", 7: "ete", 8: "ete",
            9: "automne", 10: "automne", 11: "automne"
        }
        current_season = month_to_season.get(current_month, "")
        return current_season in seasons if seasons else False

    def _generate_nutritional_score(self, total_nutrition):
        """Générer un score nutritionnel de 0 à 100."""
        score = 100
        reasons = []
        recommendations = []

        calories = total_nutrition.get('calories', 0)
        proteines = total_nutrition.get('proteines', 0)
        glucides = total_nutrition.get('glucides', 0)
        lipides = total_nutrition.get('lipides', 0)
        fibres = total_nutrition.get('fibres', 0)
        sel = total_nutrition.get('sel', 0)
        sucres = total_nutrition.get('sucres', 0)

        # Analyse calorique
        if calories > 800:
            score -= 15
            reasons.append("Repas très calorique (>800 kcal)")
        elif calories < 300 and calories > 0:
            recommendations.append("Repas léger, pensez à ajouter une source de protéines")

        # Analyse protéines
        if proteines < 15 and calories > 300:
            score -= 10
            reasons.append("Faible en protéines")
        elif proteines > 40:
            score -= 5
            reasons.append("Très riche en protéines")

        # Analyse lipides
        if lipides > 50:
            score -= 25
            reasons.append("Extrêmement riche en lipides")
        elif lipides > 30:
            score -= 15
            reasons.append("Trop de lipides (>30g)")

        # Analyse fibres
        if fibres > 10:
            score += 5
            recommendations.append("Excellent apport en fibres !")
        elif fibres < 3 and calories > 400:
            score -= 10
            reasons.append("Faible en fibres")

        # Analyse sucres
        if sucres > 25:
            score -= 15
            reasons.append("Riche en sucres")

        # Analyse sel
        if sel > 4:
            score -= 25
            reasons.append("Extrêmement salé")
        elif sel > 2:
            score -= 15
            reasons.append("Trop salé (>2g)")

        # Recommandations générales
        if not recommendations:
            if proteines < 20 and calories > 400:
                recommendations.append("Pour un repas plus équilibré, ajoutez une source de protéines")
            if fibres < 5:
                recommendations.append("Pensez à ajouter des légumes pour plus de fibres")

        return {
            "score": max(0, min(100, score)),
            "reasons": reasons,
            "recommendations": recommendations,
            "analyse": {
                "calories": calories,
                "proteines": proteines,
                "glucides": glucides,
                "lipides": lipides,
                "fibres": fibres,
                "sel": sel,
                "sucres": sucres
            }
        }

    def calculate_nutrition(self, foods, regime_prescrit):
        """Calculer la nutrition totale pour une liste d'aliments détectés."""
        total_nutrition = {
            "calories": 0, "proteines": 0, "glucides": 0, "lipides": 0,
            "sel": 0, "fibres": 0, "sucres": 0
        }
        details = []

        for f in foods:
            nutri = self._calculate_detailed_nutrition(f)
            if nutri is None:
                continue
            details.append(nutri)
            for key in total_nutrition:
                if key in nutri:
                    total_nutrition[key] += nutri[key]

        compliance = self._check_diet_compliance(details, regime_prescrit)
        nutritional_score = self._generate_nutritional_score(total_nutrition)

        return {
            "aliments": details,
            "total_nutrition": total_nutrition,
            "nutritional_score": nutritional_score,
            "compliance": compliance,
            "nombre_aliments": len(details)
        }

    def _validate_food_combinations(self, foods):
        """
        Valider les combinaisons d'aliments pour réduire les faux positifs.
        Filters illogical combinations (e.g., 10 different foods in one meal).
        """
        if not foods:
            return foods
        
        # Limite: max 5 aliments dans une seule assiette (cas réaliste)
        if len(foods) > 5:
            logger.warning(f"Trop d'aliments détectés ({len(foods)}), filtrage strict")
            # Garder seulement les plus confiants
            foods_sorted = sorted(foods, key=lambda x: x.get('confiance', 0), reverse=True)
            foods = foods_sorted[:5]
        
        # Groupes d'aliments incompatibles (faux positifs courants)
        incompatible_groups = [
            # {class principale, [aliments qui ne peuvent pas coexister]}
            ("legume_seul", ["pizza", "burger", "frites"]),  # Pas de légume seul avec fast-food
        ]
        
        # Déterminer si on a des aliments incompatibles
        # (implémentation simple pour éviter la sur-ingénierie)
        
        # Validation: si on a un plat complet + beaucoup d'autres items
        whole_dishes = [f for f in foods if f.get('nom') in WHOLE_DISH_FOODS]
        if len(whole_dishes) >= 2:
            logger.warning(f"Multiple whole dishes detected: {[f.get('nom') for f in whole_dishes]}, keeping best one")
            # Garder le meilleur plat complet seulement
            best_dish = max(whole_dishes, key=lambda x: x.get('confiance', 0))
            # Supprimer les autres plats complets mais garder les accompagnements
            other_wholes = [f for f in whole_dishes if f.get('nom') != best_dish.get('nom')]
            foods = [f for f in foods if f.get('nom') not in [w.get('nom') for w in other_wholes]]
        
        # Validation: fruits + plat chaud ensemble (peu plausible)
        # Mais on gardera si confiance suffisante
        fruit_categories = ['fruit']
        hot_dishes = ['pizza', 'burger', 'pates', 'riz', 'lasagnes', 'couscous']
        
        fruits = [f for f in foods if NUTRITION_DATA.get(f.get('nom'), {}).get('categorie') in fruit_categories]
        hot = [f for f in foods if any(h in f.get('nom', '').lower() for h in hot_dishes)]
        
        if fruits and hot and len(foods) <= 2:
            logger.info(f"Détection: Fruits + plat chaud (peu plausible mais possible: {[f.get('nom') for f in foods]})")
        
        # Dédupliquer si deux aliments très similaires détectés
        final_foods = []
        seen_categories = set()
        for food in sorted(foods, key=lambda x: x.get('confiance', 0), reverse=True):
            category = NUTRITION_DATA.get(food.get('nom'), {}).get('categorie', 'unknown')
            
            # Pour les catégories uniques, garder le meilleur match
            if category in seen_categories:
                logger.debug(f"Removing duplicate category {category}: {food.get('nom')}")
                continue
            
            seen_categories.add(category)
            final_foods.append(food)
        
        logger.info(f"Foods after validation: {len(final_foods)} items")
        return final_foods

    # ========================================================================
    # CONFORMITÉ AU RÉGIME
    # ========================================================================

    def _check_diet_compliance(self, details, regime):
        """Vérifier la conformité des aliments au régime prescrit."""
        regime = self._normalize_regime_key(regime)
        rules = DIET_RULES.get(regime, {})
        if not rules:
            return {
                "conforme": True,
                "raisons": [],
                "alertes": [],
                "recommandations": [],
                "message": "Aucune règle spécifique pour ce régime",
                "regime": regime
            }

        raisons = []
        alertes = []

        for item in details:
            categorie = item.get('categorie', 'inconnu')
            nom = item.get('nom', '').replace('_', ' ')

            if categorie in rules.get('interdits', []):
                raisons.append(f"Interdit dans votre régime {regime}: {nom}")
                alertes.append(
                    f"L'aliment '{nom}' est déconseillé dans le régime {regime}"
                )

            if categorie in rules.get('a_limiter', []):
                raisons.append(f"A limiter ({regime}): {nom}")

        # Vérifier les catégories recommandées manquantes
        recommandations = []
        categories_presentes = set(item.get('categorie', '') for item in details)
        categories_recommandees = set(rules.get('recommandes', []))
        manquants = categories_recommandees - categories_presentes
        if manquants:
            recommandations.append(
                f"Pour un meilleur équilibre, ajoutez: {', '.join(manquants)}"
            )

        return {
            "conforme": len(alertes) == 0,
            "raisons": raisons,
            "alertes": alertes,
            "recommandations": recommandations,
            "regime": rules.get('nom', regime)
        }

    # ========================================================================
    # SUGGESTIONS DE RECETTES
    # ========================================================================

    def get_recipes(self, total_nutrition, daily_limit, consumed_today, regime="Standard"):
        """Obtenir des suggestions de recettes basées sur les calories restantes."""
        remaining = daily_limit - (consumed_today + total_nutrition.get('calories', 0))

        if remaining <= 0:
            return {
                "message": "Limite calorique atteinte. Pas de recettes supplémentaires recommandées.",
                "remaining_calories": 0,
                "recipes": []
            }

        return self._get_diet_based_recipes(remaining, regime)

    def _normalize_regime_key(self, regime):
        """Normaliser le nom du régime pour correspondre aux clés DIET_RULES."""
        if regime in DIET_RULES:
            return regime
        for key in DIET_RULES:
            if key.lower() == regime.lower():
                return key
        return regime

    def _get_diet_based_recipes(self, remaining_calories, regime, detected_foods=None):
        """Obtenir des recettes basées sur le régime et les calories restantes."""
        regime = self._normalize_regime_key(regime)
        rules = DIET_RULES.get(regime, {})
        recommended_categories = rules.get('recommandes', [])
        forbidden_categories = rules.get('interdits', [])

        # Obtenir les recettes locales spécifiques au régime
        local_recipes = self._get_local_diet_recipes(remaining_calories, regime)

        # Trier par calories
        local_recipes.sort(key=lambda x: x.get('calories', 500))

        return {
            "remaining_calories": round(remaining_calories, 1),
            "regime": regime,
            "regime_nom": rules.get('nom', regime),
            "categories_recommandees": recommended_categories,
            "categories_interdites": forbidden_categories,
            "recipes": local_recipes[:8],
            "conseil": self._generate_diet_advice(regime, remaining_calories)
        }

    def _get_local_diet_recipes(self, max_calories, regime):
        """Obtenir les recettes stockées localement pour un régime spécifique."""
        recipes = []

        diet_recipes = DIET_RECIPE_SUGGESTIONS.get(regime)
        if diet_recipes is None:
            diet_recipes = DIET_RECIPE_SUGGESTIONS.get("Standard", [])

        for recipe in diet_recipes:
            if recipe['calories'] <= max_calories:
                recipes.append({
                    "nom": recipe['nom'],
                    "name": recipe['nom'],
                    "ingredients": recipe.get('ingredients', []),
                    "estimated_calories": recipe['calories'],
                    "calories": recipe['calories'],
                    "difficulte": recipe.get('difficulte', 'Facile'),
                    "temps": recipe.get('temps', ''),
                    "source": "local_diet"
                })

        return recipes

    def _generate_diet_advice(self, regime, remaining_calories):
        """Générer des conseils diététiques basés sur le régime."""
        rules = DIET_RULES.get(regime, {})
        recommended = rules.get('recommandes', [])

        if remaining_calories > 500:
            return (
                f"Vous avez encore {int(remaining_calories)} kcal disponibles. "
                f"Privilégiez: {', '.join(recommended) if recommended else 'une alimentation équilibrée'}."
            )
        elif remaining_calories > 200:
            return (
                f"Il vous reste {int(remaining_calories)} kcal. "
                f"Optez pour un repas léger avec "
                f"{', '.join(recommended[:2]) if recommended else 'des légumes'}."
            )
        else:
            return (
                f"Il ne reste que {int(remaining_calories)} kcal. "
                f"Prenez une collation légère si nécessaire."
            )

    # ========================================================================
    # RAPPORTS
    # ========================================================================

    def generate_final_report(self, total_nutrition, compliance, daily_limit, consumed_today, regime=""):
        """Générer un rapport final d'analyse avec alertes."""
        total_day = consumed_today + total_nutrition.get('calories', 0)
        remaining = daily_limit - total_day

        alerts = []

        # Alerte calorique
        if total_day > daily_limit:
            alerts.append({
                "type": "danger",
                "title": "Limite calorique dépassée",
                "messages": [
                    f"Total du jour: {int(total_day)} / {daily_limit} kcal",
                    f"Dépassement: {int(total_day - daily_limit)} kcal"
                ],
                "recommendation": "Réduisez les portions du prochain repas"
            })
        elif remaining < 200:
            alerts.append({
                "type": "warning",
                "title": "Limite calorique presque atteinte",
                "messages": [
                    f"Total: {int(total_day)} / {daily_limit} kcal",
                    f"Reste: {int(remaining)} kcal"
                ]
            })
        else:
            alerts.append({
                "type": "success",
                "title": "Dans votre limite calorique",
                "messages": [
                    f"Total: {int(total_day)} / {daily_limit} kcal",
                    f"Reste: {int(remaining)} kcal"
                ]
            })

        # Alerte conformité régime
        if not compliance.get('conforme', True):
            alerts.append({
                "type": "warning",
                "title": f"Alerte régime {regime}",
                "messages": compliance.get('raisons', []),
                "recommendations": compliance.get('recommandations', [])
            })

        # Alerte score nutritionnel
        score_info = self._generate_nutritional_score(total_nutrition)
        if score_info['score'] < 50:
            alerts.append({
                "type": "info",
                "title": "Conseils nutritionnels",
                "messages": score_info['reasons'],
                "recommendations": score_info['recommendations']
            })
        elif score_info['score'] > 80:
            alerts.append({
                "type": "success",
                "title": "Excellent équilibre nutritionnel !",
                "messages": ["Votre repas est bien équilibré"],
                "score": score_info['score']
            })

        # Format compatible pour JS
        compat_alerts = []
        for a in alerts:
            msg = a.get('title', '')
            if a.get('messages'):
                msg += ": " + "; ".join(a['messages'])
            compat_alerts.append({"type": a.get('type', 'info'), "message": msg})

        return {
            "alerts": compat_alerts,
            "total_day": round(total_day, 1),
            "remaining_calories": round(remaining, 1),
            "daily_limit": daily_limit,
            "consumed_before": consumed_today,
            "meal_calories": total_nutrition.get('calories', 0),
            "nutritional_score": score_info['score'],
            "score_details": score_info,
            "message": "Rapport généré par WANNASNI AI - Analyse nutritionnelle",
            "timestamp": datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        }

    def compare_calories_with_diet(self, total_calories, prescribed_calories):
        """Comparer les calories totales avec la limite quotidienne prescrite."""
        difference = prescribed_calories - total_calories
        percentage = (total_calories / prescribed_calories) * 100 if prescribed_calories > 0 else 0

        if difference > 0:
            return {
                "status": "under_limit",
                "message": f"En dessous de la limite de {abs(difference):.0f} kcal",
                "difference": abs(difference),
                "percentage": round(percentage, 1)
            }
        elif difference < 0:
            return {
                "status": "over_limit",
                "message": f"Dépassement de {abs(difference):.0f} kcal",
                "difference": abs(difference),
                "percentage": round(percentage, 1),
                "suggestion": "Activité physique recommandée pour compenser"
            }
        else:
            return {
                "status": "on_limit",
                "message": "Exactement à la limite calorique",
                "difference": 0,
                "percentage": 100
            }

    # ========================================================================
    # ANALYSE PRINCIPALE
    # ========================================================================

    def analyze_meal(self, image_path, regime_prescrit="Standard", daily_limit=2000, consumed_today=0):
        """
        Pipeline complet d'analyse de repas.

        POLITIQUE STRICTE:
        - Si aucun aliment détecté -> retourne uniquement statut "not_detected"
        - Pas de données par défaut, pas de calories fictives
        - Analyse complète uniquement quand un aliment EST détecté
        """
        try:
            logger.info(f"Début analyse repas: {image_path}")

            # Étape 1: Détection des aliments
            detection_result = self.detect_only(image_path)

            if not detection_result.get('detected', False):
                return {
                    "status": "not_detected",
                    "aliments_detectes": [],
                    "message": detection_result.get(
                        'message',
                        "Aucun aliment détecté dans cette image."
                    ),
                    "conseil": (
                        "Essayez de reprendre la photo de plus près, "
                        "avec un bon éclairage, en centrant bien l'aliment."
                    ),
                    "timestamp": datetime.now().isoformat()
                }

            foods = detection_result['foods']

            # Étape 2: Calcul nutritionnel
            nutrition_results = self.calculate_nutrition(foods, regime_prescrit)

            # Étape 3: Comparaison avec la limite du régime
            calorie_comparison = self.compare_calories_with_diet(
                nutrition_results['total_nutrition']['calories'],
                daily_limit - consumed_today
            )

            # Étape 4: Suggestions de recettes
            remaining_calories = daily_limit - (
                consumed_today + nutrition_results['total_nutrition']['calories']
            )
            recipe_suggestions = {}
            if remaining_calories > 100:
                recipe_suggestions = self._get_diet_based_recipes(
                    remaining_calories, regime_prescrit,
                    detected_foods=[f['nom'] for f in foods]
                )

            # Étape 5: Génération du rapport
            report = self.generate_final_report(
                nutrition_results['total_nutrition'],
                nutrition_results['compliance'],
                daily_limit,
                consumed_today,
                regime_prescrit
            )

            return {
                "status": "success",
                "timestamp": datetime.now().isoformat(),
                "image_analysee": os.path.basename(image_path),
                "regime_prescrit": regime_prescrit,
                "aliments_detectes": nutrition_results['aliments'],
                "analyse_nutritionnelle": nutrition_results,
                "comparaison_calories": calorie_comparison,
                "recettes_suggerees": recipe_suggestions.get('recipes', [])[:5],
                "conseil_regime": recipe_suggestions.get('conseil', ''),
                "rapport": report,
                "message": "Analyse terminée avec succès"
            }

        except Exception as e:
            logger.error(f"Erreur analyse repas: {e}")
            import traceback
            traceback.print_exc()
            return {
                "status": "error",
                "message": f"Erreur lors de l'analyse: {str(e)}",
                "timestamp": datetime.now().isoformat()
            }

    # ========================================================================
    # DÉTECTION MULTI-ALIMENTS
    # ========================================================================

    def _detect_multi_foods(self, image_path):
        """
        Détecter plusieurs aliments dans une seule image.
        
        Utilise des méthodes unifiées du similarity_matcher:
        1. detect_multiple_foods() - détecte les catégories
        2. map_categories_to_foods() - mappe vers les clés NUTRITION_DATA
        """
        logger.info(f"=== DÉTECTION MULTI-ALIMENTS: {image_path} ===")
        
        # Étape 1: Détection des catégories par région
        multi_results = self.similarity_matcher.detect_multiple_foods(image_path)
        
        if not multi_results:
            logger.info("Aucune catégorie détectée")
            return [{
                "nom": "non_detecte",
                "detected": False,
                "confiance": 0.0,
                "source": "none",
                "message": "Aucun aliment detecte dans cette image."
            }]
        
        # Étape 2: Mapper les catégories vers les clés NUTRITION_DATA
        mapped_foods = self.similarity_matcher.map_categories_to_foods(multi_results)
        
        if not mapped_foods:
            logger.info("Aucune catégorie mappée vers NUTRITION_DATA")
            return [{
                "nom": "non_detecte",
                "detected": False,
                "confiance": 0.0,
                "source": "none",
                "message": "Aucun aliment valide detecte dans cette image."
            }]
        
        # Étape 3: Transformer au format interne
        result = []
        for idx, item in enumerate(mapped_foods):
            food_key = item.get('food_key', '')
            confidence = item.get('confidence', 0.0)
            source = item.get('source', 'unknown')
            
            if food_key not in NUTRITION_DATA:
                logger.warning(f"[{idx}] Clé NUTRITION invalide: {food_key}")
                continue
            
            food_data = NUTRITION_DATA[food_key]
            result.append({
                "nom": food_key,
                "confiance": confidence,
                "source": source,
                "detected": True,
                "type": food_data.get('categorie', 'inconnu'),
                "region": item.get('source', 'full').replace('_', ' ').title()
            })
            
            logger.info(f"[{idx+1}] {food_key} ({confidence:.3f}) [{source}]")
        
        if not result:
            logger.warning("Pas de résultats après filtrage NUTRITION_DATA")
            return [{
                "nom": "non_detecte",
                "detected": False,
                "confiance": 0.0,
                "source": "none",
                "message": "Aucun aliment confiant detecte dans cette image."
            }]
        
        # Trier par confiance décroissante
        result.sort(key=lambda x: x['confiance'], reverse=True)
        
        logger.info(f"=== RÉSULTAT FINAL MULTI-ALIMENTS: {len(result)} aliments ===")
        for idx, food in enumerate(result):
            logger.info(f"  {idx+1}. {food['nom']} ({food['confiance']:.3f}) [{food['source']}]")
        
        return result

    def _detect_food_colors(self, hsv_image):
        """Détection couleur AVANCÉE avec signatures visuelles spécialisées par aliment."""
        color_detections = {}
        h, w = hsv_image.shape[:2]
        total_pixels = h * w
        
        # Convertir en BGR puis GRAY pour analyses supplémentaires
        bgr_image = cv2.cvtColor(hsv_image, cv2.COLOR_HSV2BGR)
        gray = cv2.cvtColor(bgr_image, cv2.COLOR_BGR2GRAY)

        # ===== POULET GRILLÉ =====
        gold_mask = cv2.inRange(hsv_image, np.array([10, 60, 80]), np.array([25, 200, 220]))
        gold_ratio = cv2.countNonZero(gold_mask) / total_pixels
        
        if gold_ratio > 0.05:
            sobel_x = cv2.Sobel(gray, cv2.CV_64F, 1, 0, ksize=3)
            sobel_y = cv2.Sobel(gray, cv2.CV_64F, 0, 1, ksize=3)
            edges = np.sqrt(sobel_x**2 + sobel_y**2)
            texture_score = np.count_nonzero(edges > 50) / total_pixels
            
            poulet_conf = (gold_ratio * 0.6 + texture_score * 0.4) if texture_score > 0.03 else 0
            if poulet_conf > 0.15:
                color_detections['poulet_grille'] = min(0.85, 0.40 + poulet_conf)

        # ===== FRITES =====
        orange_mask = cv2.inRange(hsv_image, np.array([8, 70, 70]), np.array([22, 255, 240]))
        orange_ratio = cv2.countNonZero(orange_mask) / total_pixels
        
        if orange_ratio > 0.08:
            # Uniformité de couleur
            hsv_s = hsv_image[:,:,1]
            sat_variance = cv2.Laplacian(hsv_s, cv2.CV_64F).var()
            uniformity_score = 1.0 / (1.0 + sat_variance / 100.0)
            
            # Formes longues
            contours, _ = cv2.findContours(orange_mask, cv2.RETR_TREE, cv2.CHAIN_APPROX_SIMPLE)
            aspect_ratios = []
            for cnt in contours[:10]:
                if cv2.contourArea(cnt) > 50:
                    x, y, w_, h_ = cv2.boundingRect(cnt)
                    aspect_ratios.append(max(w_, h_) / (min(w_, h_) + 1))
            
            long_objects_score = len([r for r in aspect_ratios if r > 2.5]) / max(1, len(aspect_ratios))
            
            frites_conf = (orange_ratio * 0.4 + uniformity_score * 0.3 + long_objects_score * 0.3)
            if frites_conf > 0.20:
                color_detections['frites_moyenne'] = min(0.85, 0.35 + frites_conf)

        # ===== SALADE VERTE =====
        green_bright = cv2.inRange(hsv_image, np.array([35, 30, 60]), np.array([85, 255, 255]))
        green_ratio = cv2.countNonZero(green_bright) / total_pixels
        
        if green_ratio > 0.10:
            h_channel = hsv_image[:,:,0].astype(float)
            h_variance = cv2.Laplacian(h_channel, cv2.CV_64F).var()
            variation_score = min(0.9, h_variance / 200.0)
            
            salade_conf = (green_ratio * 0.6 + variation_score * 0.4)
            if salade_conf > 0.20 and variation_score > 0.10:
                color_detections['salade_verte'] = min(0.85, 0.30 + salade_conf)

        # ===== RIZ BLANC =====
        white_mask = cv2.inRange(hsv_image, np.array([0, 0, 180]), np.array([40, 50, 255]))
        white_ratio = cv2.countNonZero(white_mask) / total_pixels
        
        if white_ratio > 0.08:
            morph_kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (3, 3))
            white_closed = cv2.morphologyEx(white_mask, cv2.MORPH_CLOSE, morph_kernel)
            num_components, _ = cv2.connectedComponents(white_closed)
            granule_score = min(1.0, num_components / 100.0) if num_components > 20 else 0
            
            riz_conf = (white_ratio * 0.5 + granule_score * 0.5)
            if riz_conf > 0.15 and granule_score > 0.10:
                color_detections['riz_blanc'] = min(0.85, 0.30 + riz_conf)

        # ===== CHAMPIGNON CUIT =====
        brown_mask = cv2.inRange(hsv_image, np.array([5, 40, 50]), np.array([25, 200, 160]))
        brown_ratio = cv2.countNonZero(brown_mask) / total_pixels
        
        if brown_ratio > 0.05:
            edges = cv2.Canny(gray, 50, 150)
            smoothness_score = 1.0 - (np.count_nonzero(edges) / total_pixels)
            
            contours, _ = cv2.findContours(brown_mask, cv2.RETR_TREE, cv2.CHAIN_APPROX_SIMPLE)
            roundness = []
            for cnt in contours:
                area = cv2.contourArea(cnt)
                perimeter = cv2.arcLength(cnt, True)
                if perimeter > 0:
                    circularity = 4 * np.pi * area / (perimeter ** 2)
                    if area > 50:
                        roundness.append(circularity)
            
            round_score = np.mean(roundness) if roundness else 0
            
            champ_conf = (brown_ratio * 0.35 + smoothness_score * 0.35 + round_score * 0.30)
            if champ_conf > 0.18 and smoothness_score > 0.20:
                color_detections['champignon_cuit'] = min(0.85, 0.25 + champ_conf)

        # ===== TOMATE =====
        red1 = cv2.inRange(hsv_image, np.array([0, 100, 100]), np.array([10, 255, 255]))
        red2 = cv2.inRange(hsv_image, np.array([170, 100, 100]), np.array([180, 255, 255]))
        red_mask = cv2.bitwise_or(red1, red2)
        red_ratio = cv2.countNonZero(red_mask) / total_pixels
        
        if red_ratio > 0.06:
            v_channel = hsv_image[:,:,2]
            brightness = np.mean(v_channel[red_mask > 0]) / 255.0 if np.count_nonzero(red_mask) > 0 else 0
            
            tomate_conf = (red_ratio * 0.5 + brightness * 0.5)
            if tomate_conf > 0.18 and brightness > 0.70:
                color_detections['tomate'] = min(0.85, 0.25 + tomate_conf)

        logger.debug(f"Détections couleur: {color_detections}")
        return color_detections

    # ========================================================================
    # ESTIMATION DES PORTIONS
    # ========================================================================

    def _estimate_portions(self, image_path, detected_foods):
        """
        Estimer les tailles de portion basées sur l'analyse visuelle.
        """
        img = cv2.imread(image_path)
        if img is None:
            return {f.get('nom', 'inconnu'): {'portion_g': 100, 'methode': 'defaut'}
                    for f in detected_foods}

        h, w = img.shape[:2]
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        blurred = cv2.GaussianBlur(gray, (11, 11), 0)

        # Détection d'assiette circulaire
        circles = cv2.HoughCircles(
            blurred, cv2.HOUGH_GRADIENT, dp=1.2, minDist=100,
            param1=100, param2=50, minRadius=int(min(h, w) * 0.2),
            maxRadius=int(min(h, w) * 0.45)
        )

        plate_detected = False
        plate_diameter_cm = 26
        pixels_per_cm = 1
        plate_area_px = h * w

        if circles is not None:
            plate_detected = True
            best_circle = circles[0][0]
            plate_radius_px = best_circle[2]
            plate_area_px = np.pi * plate_radius_px ** 2
            pixels_per_cm = (plate_radius_px * 2) / plate_diameter_cm
            logger.info(f"Assiette détectée: rayon={plate_radius_px}px, {pixels_per_cm:.1f}px/cm")
        else:
            pixels_per_cm = max(w, h) / 30
            logger.info("Pas d'assiette détectée, estimation basée sur l'image")

        portions = {}
        num_foods = max(1, len([f for f in detected_foods if f.get('detected', False)]))

        for food in detected_foods:
            if not food.get('detected', False):
                continue

            food_name = food.get('nom', 'inconnu')
            food_data = NUTRITION_DATA.get(food_name, {})
            default_portion = food_data.get('portion_moyenne', 100)
            categorie = food_data.get('categorie', 'inconnu')

            # Estimation basée sur la proportion de surface
            food_area_ratio = 1.0 / num_foods
            estimated_area_cm2 = (plate_area_px * food_area_ratio) / (pixels_per_cm ** 2)

            # Conversion surface -> grammes (densité approximative)
            density_map = {
                'proteine': 1.1, 'proteine_maigre': 1.0, 'proteine_grasse': 1.2,
                'feculent': 0.8, 'legume': 0.5, 'fruit': 0.6,
                'fast_food': 1.3, 'sauce': 1.0, 'confiserie': 0.9,
            }
            density = density_map.get(categorie, 0.8)
            height_cm = 1.5 if categorie in ('proteine', 'feculent') else 1.0
            volume_cm3 = estimated_area_cm2 * height_cm
            estimated_g = round(volume_cm3 * density)

            # Limiter à une plage raisonnable
            min_portion = max(30, int(default_portion * 0.3))
            max_portion = int(default_portion * 3)
            estimated_g = max(min_portion, min(max_portion, estimated_g))

            confidence_label = 'haute' if plate_detected else 'moyenne'
            portions[food_name] = {
                'portion_g': estimated_g,
                'portion_defaut_g': default_portion,
                'methode': 'assiette_detectee' if plate_detected else 'estimation_image',
                'confiance': confidence_label,
                'categorie': categorie
            }

        return portions

    # ========================================================================
    # ANALYSE AVANCÉE
    # ========================================================================

    def analyze_meal_advanced(self, image_path, regime_prescrit="Standard",
                               daily_limit=2000, consumed_today=0,
                               poids=None, taille=None, age=None):
        """
        Analyse avancée combinant toutes les fonctionnalités:
        1. Détection multi-aliments
        2. Estimation des portions
        3. Calcul nutritionnel
        4. Conformité au régime
        5. Suggestions de recettes
        """
        try:
            logger.info(f"Analyse avancée: {image_path}")

            # Étape 1: Détection multi-aliments
            foods = self._detect_multi_foods(image_path)

            if not any(f.get('detected', False) for f in foods):
                return {
                    "status": "not_detected",
                    "aliments_detectes": [],
                    "message": "Aucun aliment détecté dans cette image.",
                    "conseil": "Reprenez la photo de plus près avec un bon éclairage.",
                    "timestamp": datetime.now().isoformat()
                }

            detected = [f for f in foods if f.get('detected', False)]

            # Étape 2: Estimation des portions
            portions = self._estimate_portions(image_path, detected)
            for food in detected:
                portion_info = portions.get(food['nom'], {})
                food['quantite'] = portion_info.get('portion_g', 100)
                food['portion_estimee'] = portion_info

            # Étape 3: Nutrition
            nutrition_results = self.calculate_nutrition(detected, regime_prescrit)

            # Étape 4: Comparaison calories
            calorie_comparison = self.compare_calories_with_diet(
                nutrition_results['total_nutrition']['calories'],
                daily_limit - consumed_today
            )

            # Étape 5: Recettes
            remaining = daily_limit - (consumed_today + nutrition_results['total_nutrition']['calories'])
            recipe_suggestions = {}
            if remaining > 100:
                recipe_suggestions = self._get_diet_based_recipes(remaining, regime_prescrit)

            # Étape 6: Rapport
            report = self.generate_final_report(
                nutrition_results['total_nutrition'],
                nutrition_results['compliance'],
                daily_limit, consumed_today, regime_prescrit
            )

            return {
                "status": "success",
                "timestamp": datetime.now().isoformat(),
                "image_analysee": os.path.basename(image_path),
                "regime_prescrit": regime_prescrit,
                "multi_detection": True,
                "nombre_aliments": len(detected),
                "aliments_detectes": nutrition_results['aliments'],
                "portions_estimees": portions,
                "analyse_nutritionnelle": nutrition_results,
                "comparaison_calories": calorie_comparison,
                "recettes_suggerees": recipe_suggestions.get('recipes', [])[:5],
                "conseil_regime": recipe_suggestions.get('conseil', ''),
                "rapport": report,
                "message": f"Analyse avancée terminée : {len(detected)} aliment(s) détecté(s)"
            }

        except Exception as e:
            logger.error(f"Erreur analyse avancée: {e}")
            import traceback
            traceback.print_exc()
            return {
                "status": "error",
                "message": f"Erreur analyse avancée: {str(e)}",
                "timestamp": datetime.now().isoformat()
            }