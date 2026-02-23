#!/usr/bin/env python3
"""
FULL NUTRITION ANALYZER - CORRIGÉ
Version qui distingue intelligemment les images simples des plats composés
"""

import cv2
import numpy as np
import logging
from datetime import datetime
import os

from .nutrition_knowledge import NUTRITION_DATA, DIET_RULES, DIET_RECIPE_SUGGESTIONS
from .similarity_matcher import ImageSimilarityMatcher
from .strict_false_positive_filter import StrictFalsePositiveFilter

logger = logging.getLogger("FullNutritionAnalyzer")

# Seuils ajustés
SIMPLE_FOOD_THRESHOLD = 0.60  # Pour un seul aliment
MULTI_FOOD_THRESHOLD = 0.45    # Pour plusieurs aliments
COMPOSITION_DOMINANCE_RATIO = 0.25  # Si un aliment domine trop, c'est simple

class FullNutritionAnalyzer:
    """
    Analyseur intelligent qui distingue les images simples des plats composés
    """
    
    def __init__(self):
        self.raw_data_dir = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'data', 'raw')
        self.similarity_matcher = ImageSimilarityMatcher(self.raw_data_dir)
        self.similarity_matcher.build_index()
        self.strict_filter = StrictFalsePositiveFilter()
    
    def _classify_image_type(self, image_path):
        """
        Détermine si l'image contient un seul aliment ou plusieurs.
        Retourne: 'simple', 'multi', ou 'unknown'
        """
        img = cv2.imread(image_path)
        if img is None:
            return 'unknown'
        
        h, w = img.shape[:2]
        
        # 1. Analyse de couleur - Si une couleur domine > 60%, probablement simple
        hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
        
        # Quantifier les couleurs dominantes
        hist_h = cv2.calcHist([hsv], [0], None, [30], [0, 180])
        hist_h = hist_h.flatten() / (h * w)
        
        # Trouver les couleurs significatives (> 5% de l'image)
        significant_colors = np.sum(hist_h > 0.05)
        dominant_color_ratio = np.max(hist_h)
        
        logger.debug(f"Analyse couleurs: {significant_colors} couleurs significatives, dominance={dominant_color_ratio:.2f}")
        
        if dominant_color_ratio > 0.40 and significant_colors <= 3:
            logger.info("📸 Image classée: SIMPLE (couleur dominante)")
            return 'simple'
        
        # 2. Analyse de contours - Beaucoup de contours = probablement multiple
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        edges = cv2.Canny(gray, 50, 150)
        edge_density = np.count_nonzero(edges) / (h * w)
        
        logger.debug(f"Densité contours: {edge_density:.3f}")
        
        if edge_density > 0.15:
            logger.info("📸 Image classée: MULTI (beaucoup de contours)")
            return 'multi'
        
        # 3. Analyse par région - Comparer les régions
        regions = self._get_sample_regions(img)
        region_scores = []
        
        for region in regions[:4]:  # 4 quadrants
            # Extraire features rapidement
            region_features = self._extract_fast_features(region)
            region_scores.append(region_features)
        
        # Si les régions sont très différentes, c'est multi
        if len(region_scores) > 1:
            diff = np.std(region_scores) / (np.mean(region_scores) + 0.001)
            if diff > 0.3:
                logger.info(f"📸 Image classée: MULTI (régions hétérogènes, diff={diff:.2f})")
                return 'multi'
        
        logger.info("📸 Image classée: SIMPLE par défaut")
        return 'simple'
    
    def _get_sample_regions(self, img):
        """Découpe l'image en régions pour analyse"""
        h, w = img.shape[:2]
        regions = []
        
        # 4 quadrants
        regions.append(img[0:h//2, 0:w//2])
        regions.append(img[0:h//2, w//2:w])
        regions.append(img[h//2:h, 0:w//2])
        regions.append(img[h//2:h, w//2:w])
        
        return regions
    
    def _extract_fast_features(self, img):
        """Extraction rapide de features pour classification"""
        try:
            # Redimensionner pour vitesse
            small = cv2.resize(img, (64, 64))
            
            # Moyenne des couleurs
            mean_color = np.mean(small, axis=(0, 1))
            
            # Variance (texture)
            gray = cv2.cvtColor(small, cv2.COLOR_BGR2GRAY)
            variance = np.var(gray)
            
            return np.mean(mean_color) + variance / 1000
        except:
            return 0
    
    def detect_only(self, image_path):
        """
        Détection intelligente - choisit automatiquement la bonne méthode
        """
        logger.info(f"🔍 Analyse de l'image: {image_path}")
        
        # ÉTAPE 1: Classifier le type d'image
        image_type = self._classify_image_type(image_path)
        logger.info(f"🏷️ Type détecté: {image_type}")
        
        # ÉTAPE 2: Choisir la méthode appropriée
        if image_type == 'simple':
            # Image simple - utiliser find_match (retourne 1 aliment)
            foods = self._detect_single_food(image_path)
        else:
            # Image multiple - utiliser detect_multiple_foods
            foods = self._detect_multi_foods_corrected(image_path)
        
        # ÉTAPE 3: Post-traitement et validation
        if not foods:
            return {
                "detected": False,
                "message": "Aucun aliment détecté dans cette image.",
                "foods": []
            }
        
        # Ajouter les données nutritionnelles
        for food in foods:
            food_name = food.get('nom', '')
            if food_name in NUTRITION_DATA:
                nutrition = NUTRITION_DATA[food_name]
                food['calories'] = nutrition.get('calories', 0)
                food['categorie'] = nutrition.get('categorie', 'inconnu')
                food['description'] = nutrition.get('description', '')
                food['bienfaits'] = nutrition.get('bienfaits', '')
                food['nutriments'] = nutrition.get('nutriments', {})
        
        return {
            "detected": True,
            "foods": foods,
            "image_type": image_type,
            "message": f"{len(foods)} aliment(s) détecté(s)"
        }
    
    def _detect_single_food(self, image_path):
        """
        Détection pour image simple - retourne 1 seul aliment
        """
        logger.info("📌 Mode SIMPLE: recherche d'un seul aliment")
        
        # Utiliser find_match du similarity_matcher
        match = self.similarity_matcher.find_match(image_path)
        
        if not match or match['confidence'] < 0.50:
            logger.warning("❌ Aucun match fiable pour image simple")
            return []
        
        # Mapper la catégorie vers un aliment
        food_key = self.similarity_matcher.map_category_to_food(match['category'])
        if not food_key or food_key not in NUTRITION_DATA:
            logger.warning(f"❌ Mapping invalide: {match['category']} → {food_key}")
            return []
        
        logger.info(f"✅ Aliment simple détecté: {food_key} (confiance: {match['confidence']:.2f})")
        
        return [{
            "nom": food_key,
            "confiance": match['confidence'],
            "source": "similarity",
            "detected": True,
            "type": "simple"
        }]
    
    def _detect_multi_foods_corrected(self, image_path):
        """
        Détection pour image multiple - retourne TOUS les aliments du plat
        Version améliorée: seuil bas + détection couleur + pas de filtre strict
        """
        logger.info("🍽️ Mode MULTI AMÉLIORÉ: recherche de plusieurs aliments")
        
        # Étape 1: Obtenir TOUS les candidats de similarity_matcher
        multi_results = self.similarity_matcher.detect_multiple_foods(image_path)
        
        if not multi_results:
            logger.warning("❌ Aucun résultat pour image multiple")
            return []
        
        logger.info(f"  Candidats bruts du matcher: {len(multi_results)}")
        for r in multi_results:
            logger.info(f"    - {r.get('category', '?')} (conf: {r.get('confidence', 0):.3f}, source: {r.get('source', '?')})")
        
        # Étape 2: Mapper vers des clés nutritionnelles avec seuil BAS (0.30)
        mapped_foods = []
        seen = set()
        
        for item in multi_results:
            cat = item.get('category', '')
            conf = item.get('confidence', 0)
            source = item.get('source', 'region')
            
            # Seuil bas pour attraper plus d'aliments dans les plats composés
            if conf < 0.30:
                logger.debug(f"    Rejeté {cat} (conf {conf:.3f} < 0.30)")
                continue
            
            food_key = self.similarity_matcher.map_category_to_food(cat)
            if not food_key or food_key not in NUTRITION_DATA:
                logger.debug(f"    Rejeté {cat} → mapping invalide ({food_key})")
                continue
            
            # Éviter les doublons
            if food_key in seen:
                logger.debug(f"    Rejeté {cat} → doublon de {food_key}")
                continue
            
            seen.add(food_key)
            mapped_foods.append({
                "nom": food_key,
                "confiance": conf,
                "source": source,
                "detected": True,
                "type": "multi"
            })
            logger.info(f"  ✓ {food_key} (conf: {conf:.3f}, source: {source})")
        
        # Étape 3: Si trop peu de résultats, ajouter détection par couleur
        if len(mapped_foods) < 4:
            logger.info(f"  Seulement {len(mapped_foods)} aliments → détection couleur en renfort")
            color_foods = self._detect_by_color(image_path)
            for cf in color_foods:
                if cf['nom'] not in seen:
                    seen.add(cf['nom'])
                    mapped_foods.append(cf)
                    logger.info(f"  ✓ (couleur) {cf['nom']} (conf: {cf['confiance']:.3f})")
        
        # PAS de filtre strict - il est trop agressif pour les plats composés
        
        # Étape 4: Résolution de conflits - éliminer les "plats complets" faux-positifs
        # Un burger, pizza, lasagnes etc. sont des PLATS COMPLETS qui ne sont pas
        # des accompagnements sur un plat composé (riz + frites + salade + viande)
        PLATS_COMPLETS = {
            "burger_classique", "burger_double", "burger_poulet",
            "pizza", "lasagnes", "lasagnes_legumes",
            "spaghetti_bolognaise", "wrap_poulet",
            "pates_completes", "couscous_royal"
        }
        ACCOMPAGNEMENTS = {
            "riz_blanc", "riz_complet", "frites_moyenne", "frites_grande",
            "salade_verte", "brocoli", "haricots_verts", "courgette",
            "pomme_de_terre_vapeur", "carotte", "épinard"
        }
        
        accomp_count = sum(1 for f in mapped_foods if f['nom'] in ACCOMPAGNEMENTS)
        
        if accomp_count >= 2 and len(mapped_foods) >= 4:
            # C'est un plat composé → les plats complets sont des faux-positifs
            before_count = len(mapped_foods)
            mapped_foods = [f for f in mapped_foods if f['nom'] not in PLATS_COMPLETS]
            removed = before_count - len(mapped_foods)
            if removed > 0:
                logger.info(f"  🔧 Filtre plats complets: {removed} faux-positif(s) retiré(s) (contexte: plat composé avec {accomp_count} accompagnements)")
        
        # Trier par confiance
        mapped_foods.sort(key=lambda x: x['confiance'], reverse=True)
        
        # Limiter à 6 max
        if len(mapped_foods) > 6:
            mapped_foods = mapped_foods[:6]
        
        logger.info(f"✅ Total final: {len(mapped_foods)} aliments détectés")
        for f in mapped_foods:
            logger.info(f"    → {f['nom']} ({f['confiance']:.3f})")
        
        return mapped_foods
    
    def _detect_by_color(self, image_path):
        """
        Détection de secours basée sur les couleurs dominantes.
        Utilisée quand la similarité ne trouve pas assez d'aliments.
        """
        img = cv2.imread(image_path)
        if img is None:
            return []
        
        hsv = cv2.cvtColor(img, cv2.COLOR_BGR2HSV)
        h, w = img.shape[:2]
        total_pixels = h * w
        detections = []
        
        # Marron foncé → steak/viande (HSV: teinte 5-20, saturation moyenne, valeur basse-moyenne)
        brown_dark = cv2.inRange(hsv, (5, 30, 20), (20, 200, 150))
        brown_ratio = cv2.countNonZero(brown_dark) / total_pixels
        if brown_ratio > 0.06:
            detections.append({
                "nom": "steak_boeuf",
                "confiance": min(0.55, 0.30 + brown_ratio),
                "source": "color_detection",
                "detected": True,
                "type": "color"
            })
            logger.info(f"  🎨 Couleur marron détectée ({brown_ratio:.2%}) → steak_boeuf")
        
        # Vert → salade/légumes
        green_mask = cv2.inRange(hsv, (35, 30, 30), (85, 255, 255))
        green_ratio = cv2.countNonZero(green_mask) / total_pixels
        if green_ratio > 0.10:
            detections.append({
                "nom": "salade_verte",
                "confiance": min(0.60, 0.35 + green_ratio),
                "source": "color_detection",
                "detected": True,
                "type": "color"
            })
        
        # Orange/jaune doré → frites, poulet pané, escalope
        golden_mask = cv2.inRange(hsv, (15, 80, 100), (30, 255, 255))
        golden_ratio = cv2.countNonZero(golden_mask) / total_pixels
        if golden_ratio > 0.08:
            detections.append({
                "nom": "escalope_poulet_pane",
                "confiance": min(0.50, 0.30 + golden_ratio),
                "source": "color_detection",
                "detected": True,
                "type": "color"
            })
            logger.info(f"  🎨 Couleur dorée détectée ({golden_ratio:.2%}) → escalope_poulet_pane")
        
        # Rouge → tomate, sauce
        red_mask1 = cv2.inRange(hsv, (0, 50, 50), (10, 255, 255))
        red_mask2 = cv2.inRange(hsv, (170, 50, 50), (180, 255, 255))
        red_mask = cv2.bitwise_or(red_mask1, red_mask2)
        red_ratio = cv2.countNonZero(red_mask) / total_pixels
        if red_ratio > 0.08:
            detections.append({
                "nom": "tomate",
                "confiance": min(0.50, 0.30 + red_ratio),
                "source": "color_detection",
                "detected": True,
                "type": "color"
            })
        
        return detections
    
    def calculate_nutrition(self, foods_list, regime="Standard"):
        """
        Calcule les valeurs nutritionnelles pour une liste d'aliments détectés.
        Appelé par app.py step2-nutrition.
        """
        aliments = []
        total_nutrition = {
            "calories": 0, "proteines": 0, "glucides": 0,
            "lipides": 0, "fibres": 0, "sel": 0, "sucres": 0
        }

        for food in foods_list:
            food_name = food if isinstance(food, str) else food.get('nom', food.get('name', ''))
            if food_name in NUTRITION_DATA:
                data = NUTRITION_DATA[food_name]
                nutriments = data.get('nutriments', {})
                cal = data.get('calories', 0)
                aliment_info = {
                    "nom": food_name,
                    "calories": cal,
                    "categorie": data.get('categorie', 'inconnu'),
                    "description": data.get('description', ''),
                    "bienfaits": data.get('bienfaits', ''),
                    "portion": data.get('portion_moyenne', 100),
                    "nutriments": nutriments
                }
                aliments.append(aliment_info)
                total_nutrition["calories"] += cal
                for key in ["proteines", "glucides", "lipides", "fibres", "sel", "sucres"]:
                    total_nutrition[key] += nutriments.get(key, 0)
            else:
                logger.warning(f"Aliment inconnu: {food_name}")

        # Vérifier la conformité au régime
        compliance = self._check_diet_compliance(aliments, regime)

        return {
            "aliments": aliments,
            "total_nutrition": total_nutrition,
            "compliance": compliance
        }

    def _check_diet_compliance(self, aliments, regime):
        """Vérifie la conformité des aliments au régime prescrit."""
        rules = DIET_RULES.get(regime, DIET_RULES.get("Standard", {}))
        interdits = rules.get("interdits", [])
        a_limiter = rules.get("a_limiter", [])
        raisons = []

        for aliment in aliments:
            cat = aliment.get("categorie", "")
            nom = aliment.get("nom", "")
            if cat in interdits:
                raisons.append(f"{nom} est interdit dans le régime {regime}")
            elif cat in a_limiter:
                raisons.append(f"{nom} est à limiter dans le régime {regime}")

        return {
            "conforme": len(raisons) == 0,
            "regime": regime,
            "raisons": raisons
        }

    def get_recipes(self, total_nutrition, daily_limit=2000, consumed_today=0, regime="Standard"):
        """
        Retourne des suggestions de recettes adaptées au régime.
        """
        remaining = daily_limit - consumed_today - total_nutrition.get("calories", 0)
        suggestions = DIET_RECIPE_SUGGESTIONS.get(regime, DIET_RECIPE_SUGGESTIONS.get("Standard", []))

        # Filtrer par calories restantes
        filtered = [r for r in suggestions if r.get("calories", 0) <= max(remaining, 200)]
        if not filtered:
            filtered = suggestions[:2]  # Toujours retourner au moins 2 suggestions

        return {
            "regime": regime,
            "calories_restantes": max(0, remaining),
            "suggestions": filtered
        }

    def generate_final_report(self, total_nutrition, compliance, daily_limit=2000, consumed_today=0):
        """
        Génère le rapport final avec alertes.
        """
        total_cal = total_nutrition.get("calories", 0)
        remaining = daily_limit - consumed_today - total_cal
        alertes = []

        if remaining < 0:
            alertes.append({
                "type": "danger",
                "message": f"Limite calorique dépassée de {abs(remaining):.0f} kcal"
            })
        elif remaining < 200:
            alertes.append({
                "type": "warning",
                "message": f"Attention: seulement {remaining:.0f} kcal restantes"
            })

        if not compliance.get("conforme", True):
            for raison in compliance.get("raisons", []):
                alertes.append({"type": "warning", "message": raison})

        return {
            "total_calories": total_cal,
            "calories_restantes": max(0, remaining),
            "alertes": alertes,
            "compliance": compliance,
            "timestamp": datetime.now().isoformat()
        }

    def analyze_meal(self, image_path, regime_prescrit="Standard", daily_limit=2000, consumed_today=0):
        """
        Pipeline complet d'analyse
        """
        try:
            detection = self.detect_only(image_path)

            if not detection.get('detected', False):
                return {
                    "status": "not_detected",
                    "message": detection.get('message', "Aucun aliment détecté"),
                    "timestamp": datetime.now().isoformat()
                }

            foods = detection['foods']
            foods_names = [f.get('nom', '') for f in foods]
            nutrition = self.calculate_nutrition(foods_names, regime_prescrit)
            total_cal = nutrition["total_nutrition"]["calories"]
            remaining = daily_limit - consumed_today - total_cal

            return {
                "status": "success",
                "image_type": detection.get('image_type', 'unknown'),
                "aliments_detectes": nutrition["aliments"],
                "total_nutrition": nutrition["total_nutrition"],
                "total_calories": total_cal,
                "calories_restantes": max(0, remaining),
                "compliance": nutrition["compliance"],
                "nombre_aliments": len(foods),
                "timestamp": datetime.now().isoformat(),
                "message": f"Analyse terminée: {len(foods)} aliment(s) détecté(s)"
            }

        except Exception as e:
            logger.error(f"Erreur: {e}")
            return {
                "status": "error",
                "message": str(e),
                "timestamp": datetime.now().isoformat()
            }

    def analyze_meal_advanced(self, image_path, regime_prescrit="Standard",
                              daily_limit=2000, consumed_today=0,
                              poids=None, taille=None, age=None):
        """
        Analyse avancée avec détection multi-aliments, scoring de risque.
        """
        try:
            result = self.analyze_meal(image_path, regime_prescrit, daily_limit, consumed_today)

            if result.get("status") != "success":
                return result

            # Ajouter IMC si données disponibles
            if poids and taille and taille > 0:
                taille_m = taille / 100 if taille > 10 else taille
                imc = poids / (taille_m ** 2)
                result["imc"] = round(imc, 1)
                if imc < 18.5:
                    result["imc_categorie"] = "Insuffisance pondérale"
                elif imc < 25:
                    result["imc_categorie"] = "Normal"
                elif imc < 30:
                    result["imc_categorie"] = "Surpoids"
                else:
                    result["imc_categorie"] = "Obésité"

            result["analyse_avancee"] = True
            return result

        except Exception as e:
            logger.error(f"Erreur analyse avancée: {e}")
            return {"status": "error", "message": str(e)}

    def generate_meal_reminders(self, regime_type="Standard", repas_par_jour=3,
                                 repas_consommes=0, calories_consommees=0,
                                 calories_limite=2000, aliments_recommandes=None,
                                 aliments_interdits=None):
        """
        Génère des rappels intelligents de repas.
        """
        repas_restants = max(0, repas_par_jour - repas_consommes)
        cal_restantes = max(0, calories_limite - calories_consommees)
        cal_par_repas = cal_restantes / repas_restants if repas_restants > 0 else 0

        rules = DIET_RULES.get(regime_type, DIET_RULES.get("Standard", {}))
        recommandes = rules.get("recommandes", [])

        rappels = []
        if repas_restants > 0:
            rappels.append({
                "message": f"Il vous reste {repas_restants} repas aujourd'hui",
                "calories_suggerees": round(cal_par_repas),
                "categories_recommandees": recommandes
            })

        if cal_restantes < 200 and repas_restants > 0:
            rappels.append({
                "type": "warning",
                "message": "Budget calorique très limité, privilégiez les légumes"
            })

        return {
            "status": "success",
            "repas_restants": repas_restants,
            "calories_restantes": cal_restantes,
            "calories_par_repas": round(cal_par_repas),
            "rappels": rappels,
            "regime": regime_type
        }

    def analyze_food_trends(self, meal_history):
        """
        Analyse les tendances alimentaires sur plusieurs jours.
        """
        if not meal_history:
            return {"status": "success", "message": "Pas d'historique", "tendances": []}

        total_days = len(meal_history)
        all_foods = []
        total_cal = 0

        for day in meal_history:
            foods = day.get("aliments", day.get("foods", []))
            for f in foods:
                name = f if isinstance(f, str) else f.get("nom", f.get("name", ""))
                all_foods.append(name)
            total_cal += day.get("calories", 0)

        # Fréquence des aliments
        from collections import Counter
        freq = Counter(all_foods)
        top_foods = freq.most_common(5)

        return {
            "status": "success",
            "jours_analyses": total_days,
            "calories_moyenne": round(total_cal / max(total_days, 1)),
            "aliments_frequents": [{"nom": f, "count": c} for f, c in top_foods],
            "total_aliments_uniques": len(set(all_foods))
        }

    def analyze_meal_rhythm(self, meal_history, repas_par_jour=3):
        """
        Analyse le rythme des repas.
        """
        if not meal_history:
            return {"status": "success", "message": "Pas d'historique", "analyse": {}}

        repas_counts = []
        for day in meal_history:
            count = day.get("nombre_repas", day.get("meals_count", 0))
            repas_counts.append(count)

        avg_meals = sum(repas_counts) / max(len(repas_counts), 1)
        regulier = all(abs(c - repas_par_jour) <= 1 for c in repas_counts) if repas_counts else False

        return {
            "status": "success",
            "moyenne_repas_jour": round(avg_meals, 1),
            "objectif": repas_par_jour,
            "regulier": regulier,
            "jours_analyses": len(meal_history),
            "message": "Rythme régulier" if regulier else "Rythme irrégulier"
        }

    def calculate_risk_score(self, poids=None, taille=None, age=None,
                              meal_history=None, regime_type="Standard", daily_limit=2000):
        """
        Calcule un score de risque nutritionnel personnalisé.
        """
        score = 50  # Score neutre par défaut
        facteurs = []

        # IMC
        if poids and taille and taille > 0:
            taille_m = taille / 100 if taille > 10 else taille
            imc = poids / (taille_m ** 2)
            if imc < 18.5:
                score += 15
                facteurs.append("IMC bas (insuffisance pondérale)")
            elif 18.5 <= imc < 25:
                score -= 10
                facteurs.append("IMC normal")
            elif 25 <= imc < 30:
                score += 10
                facteurs.append("Surpoids")
            else:
                score += 25
                facteurs.append("Obésité")

        # Historique
        if meal_history:
            avg_cal = sum(d.get("calories", 0) for d in meal_history) / max(len(meal_history), 1)
            if avg_cal > daily_limit * 1.2:
                score += 15
                facteurs.append("Apport calorique excessif")
            elif avg_cal < daily_limit * 0.6:
                score += 10
                facteurs.append("Apport calorique insuffisant")

        score = max(0, min(100, score))
        niveau = "faible" if score < 30 else "modéré" if score < 60 else "élevé"

        return {
            "status": "success",
            "score": score,
            "niveau": niveau,
            "facteurs": facteurs,
            "regime": regime_type
        }

    def generate_nutritionist_summary(self, meal_history, regime_type="Standard",
                                       daily_limit=2000, poids=None, taille=None,
                                       age=None, aliments_recommandes=None,
                                       aliments_interdits=None):
        """
        Génère un résumé nutritionniste complet.
        """
        trends = self.analyze_food_trends(meal_history)
        rhythm = self.analyze_meal_rhythm(meal_history)
        risk = self.calculate_risk_score(
            poids=poids, taille=taille, age=age,
            meal_history=meal_history, regime_type=regime_type,
            daily_limit=daily_limit
        )

        conseils = []
        if risk.get("niveau") == "élevé":
            conseils.append("Consultez un professionnel de santé pour adapter votre alimentation")
        if trends.get("total_aliments_uniques", 0) < 5:
            conseils.append("Diversifiez davantage votre alimentation")
        if not rhythm.get("regulier", False):
            conseils.append("Essayez de maintenir un rythme alimentaire plus régulier")

        rules = DIET_RULES.get(regime_type, {})
        conseils_regime = []
        for cat in rules.get("recommandes", []):
            conseils_regime.append(f"Privilégiez les aliments de type: {cat}")

        return {
            "status": "success",
            "tendances": trends,
            "rythme": rhythm,
            "risque": risk,
            "conseils": conseils,
            "conseils_regime": conseils_regime,
            "regime": regime_type,
            "timestamp": datetime.now().isoformat()
        }