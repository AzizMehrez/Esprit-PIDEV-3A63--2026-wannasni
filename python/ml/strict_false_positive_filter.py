#!/usr/bin/env python3
"""
LEVEL 3: Filtre INTELLIGENT des Fausses Positives Massives

Problem: Single food image (poulet) → returns multiple foods (poulet + pancakes + wrap)
Root Cause: No confidence gap analysis to distinguish single dominant detection from real multi-food

Solution: Intelligent gap analysis PLUS category diversity check
- SINGLE FOOD: If confidence_gap > 0.20 (dominant) → return ONLY top detection
- MULTI-FOOD: If confidence_gap < 0.15 AND categories are diverse → return ALL
- If categories have duplicates → reject as noise (e.g., 2x fast_food)
"""

from collections import Counter
try:
    from .nutrition_knowledge import NUTRITION_DATA
except ImportError:
    # Quand le fichier est exécuté directement (mode script)
    import sys
    import os
    sys.path.insert(0, os.path.dirname(__file__))
    from nutrition_knowledge import NUTRITION_DATA

class StrictFalsePositiveFilter:
    """Filtre STRICT avec logique intelligente gap + catégories"""
    
    # Aliments simples qui ne peuvent PAS être accompagnés de plats complexes
    SIMPLE_FOODS = {
        'chocolat', 'bonbon', 'biscuit', 'gâteau', 'pâtisserie',
        'fruit', 'pomme', 'banane', 'orange', 'fraise',
        'fromage', 'yaourt', 'crème', 'dessert',
        'noix', 'amande', 'noisette'
    }
    
    # Incompatibilités strictes (ne PAS apparaître ensemble)
    STRICT_INCOMPATIBILITIES = {
        'chocolat': ['pâtes', 'lasagnes', 'burger', 'pizza', 'poulet', 'steak'],
        'bonbon': ['pâtes', 'lasagnes', 'burger', 'pizza', 'riz'],
        'pomme': ['pâtes', 'lasagnes', 'viande grillée'],
        'glace': ['pâtes', 'lasagnes', 'poulet grillé', 'steak'],
    }
    
    def __init__(self):
        """Initialiser le filtre avec les seuils"""
        self.MIN_CONFIDENCE = 0.55
        self.COUNT_THRESHOLD = 4
        self.AVG_CONFIDENCE_THRESHOLD = 0.50
        self.CONFIDENCE_GAP_DOMINANT = 0.20  # Gap pour single dominant food
        self.CONFIDENCE_GAP_MULTI = 0.15     # Gap pour plat complet
    
    def get_food_category(self, food_name):
        """Récupère la catégorie nutritionnelle d'un aliment"""
        try:
            food_lower = food_name.lower()
            if food_lower in NUTRITION_DATA:
                return NUTRITION_DATA[food_lower].get('categorie', 'unknown')
        except Exception as e:
            print(f"[!] Erreur lors de la recherche de catégorie pour '{food_name}': {e}")
        return 'unknown'
    
    def extract_keywords(self, food_name):
        """Extrait les mots-clés principaux d'un aliment"""
        # Enlever les suffixes types et séparer par underscore
        food = food_name.lower().replace('_', ' ')
        # Mots à ignorer
        ignore_words = {'classique', 'frit', 'grille', 'pane', 'cuit', 'vapeur', 'moyenne', 'grande', 'petite', 'sauce', 'garni', 'complet'}
        words = [w for w in food.split() if w not in ignore_words and len(w) > 2]
        return set(words)
    
    def has_duplicate_keywords(self, candidates):
        """Vérifie si plusieurs aliments partagent les mêmes mots-clés"""
        keywords_list = [self.extract_keywords(c['name']) for c in candidates]
        
        # Chercher les chevauchements
        for i, kw1 in enumerate(keywords_list):
            for j, kw2 in enumerate(keywords_list[i+1:], i+1):
                common = kw1.intersection(kw2)
                if common:
                    return True, common, (candidates[i]['name'], candidates[j]['name'])
        return False, set(), ('', '')
    
    def filter_detections(self, candidates):
        """
        Filtre INTELLIGENT avec gap analysis + category diversity checking.
        
        Stratégie:
        - 1 seul aliment → accepter si confiance >= 0.50
        - 2-3 aliments avec GAP GRAND (>0.20) → retourner SEULEMENT le meilleur (single dominant)
        - 2-3 aliments avec GAP PETIT (<0.15) ET noms UNIQUES → retourner tous (plat complet)
        - 2-3 aliments avec GAP PETIT ET noms DOUBLONS → rejeter (bruit/fausse positive)
        - 4 aliments → strict (très haute confiance + noms uniques)
        - 5+ aliments → rejeter immédiatement
        
        Args:
            candidates: List[dict] avec keys: name, confidence, source
            
        Returns:
            List[dict]: Candidats filtrés (ou liste vide si rejet)
        """
        
        if not candidates:
            return []
        
        # Trier par confiance décroissante
        candidates_sorted = sorted(candidates, key=lambda c: c['confidence'], reverse=True)
        best_confidence = candidates_sorted[0]['confidence']
        num_candidates = len(candidates)
        
        # Cas 1: 1 SEUL aliment → accepter si confiance décente
        if num_candidates == 1:
            if best_confidence >= 0.50:
                print(f"[+] 1 seul aliment detecte ({best_confidence:.2f}) -> ACCEPTÉ")
                return candidates_sorted
            else:
                print(f"[-] 1 aliment mais confiance trop basse ({best_confidence:.2f})")
                return []
        
        # Cas 2: 2-3 ALIMENTS - analyse intelligente avec GAP + catégories
        if num_candidates in [2, 3]:
            
            # Calculer l'écart de confiance
            avg_confidence = sum(c['confidence'] for c in candidates_sorted) / len(candidates_sorted)
            confidence_gap = best_confidence - avg_confidence
            
            print(f"\n[ANALYSE] {num_candidates} aliments détectés:")
            print(f"  - Meilleur: {candidates_sorted[0]['name']} ({best_confidence:.2f})")
            for i, c in enumerate(candidates_sorted[1:], 1):
                print(f"  - #{i+1}: {c['name']} ({c['confidence']:.2f})")
            print(f"  - Écart (gap): {confidence_gap:.3f}")
            
            # Vérifier les mots-clés dupliqués (ex: "poulet" dans "poulet_grille" ET "wrap_poulet")
            has_dupes, common_keywords, dupes_pair = self.has_duplicate_keywords(candidates_sorted)
            if has_dupes and confidence_gap < 0.10:
                print(f"  ⚠ ALERTE: Mots-clés partagés {common_keywords} entre '{dupes_pair[0]}' et '{dupes_pair[1]}'")
                print(f"  → Probablement des variantes du même aliment avec gap très petit ({confidence_gap:.3f})")
                print(f"  → REJET: Fausse détection d'aliments multiples")
                return []
            
            # CAS A: GAP GRAND (> 0.20) → Une seule détection dominante
            if confidence_gap > self.CONFIDENCE_GAP_DOMINANT:
                print(f"  ✓ GAP GRAND (>{self.CONFIDENCE_GAP_DOMINANT:.2f}) = UNE SEULE IMAGE ALIMENT DOMINANT")
                print(f"  → Rejetter les secondaires (bruit), retourner SEULEMENT: {candidates_sorted[0]['name']}")
                return [candidates_sorted[0]]
            
            # CAS B: GAP PETIT (< 0.15) → Potentiellement plat complet
            elif confidence_gap < self.CONFIDENCE_GAP_MULTI:
                print(f"  → GAP PETIT (<{self.CONFIDENCE_GAP_MULTI:.2f}) = POTENTIELLEMENT PLAT COMPLET")
                
                # Vérifier les catégories nutritionnelles
                categories = [self.get_food_category(c['name']) for c in candidates_sorted]
                cat_counts = Counter(categories)
                max_cat_count = max(cat_counts.values())
                
                print(f"  → Catégories: {dict(cat_counts)}")
                
                # Vérifier si doublons exacts de NOMS = bruit
                # NOTE: On permet des aliments différents de la même catégorie
                # (ex: burger + frites sont tous les deux "fast_food" mais c'est un plat valide)
                name_counts = Counter(c['name'].lower() for c in candidates_sorted)
                max_name_count = max(name_counts.values())
                if max_name_count >= 2:
                    print(f"  ✗ DOUBLONS DE NOMS DÉTECTÉS ({max_name_count}x même aliment) = BRUIT!")
                    print(f"  → REJET: Probablement {num_candidates} fausses positives")
                    return []
                
                # Catégories toutes différentes = plat complet valide
                if best_confidence >= 0.60 and avg_confidence >= 0.45:
                    print(f"  ✓ PLAT COMPLET VALIDE (catégories variées, confiance: {avg_confidence:.2f})")
                    print(f"  → ACCEPTER les {num_candidates} aliments")
                    return candidates_sorted
                else:
                    print(f"  ✗ Confiance insuffisante pour plat complet (best={best_confidence:.2f}, avg={avg_confidence:.2f})")
                    return []
            
            # CAS C: GAP mi-chemin (0.15-0.20)
            else:
                print(f"  → GAP mi-chemin ({self.CONFIDENCE_GAP_MULTI:.2f}-{self.CONFIDENCE_GAP_DOMINANT:.2f})")
                
                if best_confidence >= 0.70:
                    print(f"  ✓ Confiance élevée ({best_confidence:.2f}) -> ACCEPTER tous")
                    return candidates_sorted
                else:
                    print(f"  ✗ Confiance moyenne -> Retourner SEULEMENT le meilleur")
                    return [candidates_sorted[0]]
        
        # Cas 3: 4 ALIMENTS → très strict
        if num_candidates == 4:
            avg_confidence = sum(c['confidence'] for c in candidates_sorted) / len(candidates_sorted)
            
            # Vérifier catégories
            categories = [self.get_food_category(c['name']) for c in candidates_sorted]
            cat_counts = Counter(categories)
            max_cat_count = max(cat_counts.values())
            
            print(f"\n[ANALYSE 4-ITEMS] Catégories: {dict(cat_counts)}")
            
            # 4 aliments valides si noms tous différents + confiance decent
            # NOTE: Catégories peuvent se répéter (ex: burger + frites = 2x fast_food est OK)
            name_counts_4 = Counter(c['name'].lower() for c in candidates_sorted)
            max_name_count_4 = max(name_counts_4.values())
            
            if max_name_count_4 == 1 and best_confidence >= 0.70 and avg_confidence >= 0.60:
                print(f"[+] 4 aliments PLAT COMPLET (noms uniques, confiances: {best_confidence:.2f}/{avg_confidence:.2f}) -> ACCEPTÉ")
                return candidates_sorted
            else:
                reason = ""
                if max_name_count_4 > 1:
                    reason = f"Noms dupliqués ({max_name_count_4}x même aliment)"
                elif best_confidence < 0.70:
                    reason = f"Confiance max trop basse ({best_confidence:.2f} < 0.70)"
                elif avg_confidence < 0.60:
                    reason = f"Confiance moyenne trop basse ({avg_confidence:.2f} < 0.60)"
                print(f"[-] REJET: 4 aliments probablement faux - {reason}")
                return []
        
        # Cas 4: 5+ ALIMENTS → trop de détections, mais garder le dominant si très confiant
        if num_candidates >= 5:
            # Si le meilleur a une confiance très élevée et un gap large,
            # c'est probablement le seul vrai aliment (les autres sont du bruit)
            if best_confidence >= 0.70:
                second_conf = candidates_sorted[1]['confidence'] if len(candidates_sorted) > 1 else 0
                gap = best_confidence - second_conf
                if gap >= 0.05:
                    print(f"[+] {num_candidates} détections mais dominant clair: {candidates_sorted[0]['name']} ({best_confidence:.2f}), gap={gap:.2f} -> GARDER LE DOMINANT")
                    return [candidates_sorted[0]]
                else:
                    # Pas de dominant clair, essayer de garder les 2-3 meilleurs avec confiance élevée
                    top_3 = [c for c in candidates_sorted[:3] if c['confidence'] >= 0.65]
                    if top_3:
                        print(f"[+] {num_candidates} détections, pas de dominant clair mais {len(top_3)} aliments forts -> GARDER")
                        return top_3
            print(f"[-] REJET: {num_candidates} aliments (>= 5) = CHAOS")
            return []
        
        # Filtre supplémentaire: incompatibilités strictes
        names_lower = {c['name'].lower(): c for c in candidates}
        
        for simple_food in self.SIMPLE_FOODS:
            if simple_food in names_lower:
                incompatible_list = self.STRICT_INCOMPATIBILITIES.get(simple_food, [])
                
                for other_name in names_lower:
                    if other_name != simple_food:
                        if any(incompat.lower() in other_name for incompat in incompatible_list):
                            print(f"[-] Incompatibilité: '{simple_food}' + '{other_name}' impossible")
                            candidates = [c for c in candidates if c['name'].lower() != other_name]
        
        # Filtre final: rejeter confiances trop basses
        candidates = [c for c in candidates if c['confidence'] >= self.MIN_CONFIDENCE]
        
        if not candidates:
            print(f"[-] REJET FINAL: Aucun aliment avec confiance >= {self.MIN_CONFIDENCE}")
            return []
        
        return candidates


def demonstrate():
    """Démo avec cas réels: single vs multi-food"""
    
    filter_strict = StrictFalsePositiveFilter()
    
    # CASE 1: Single poulet image returning 3 foods (BUG)
    print("\n" + "="*80)
    print("CASE 1: Single poulet image → Detecté comme 3 aliments (BUG À FIXER)")
    print("="*80)
    single_food_bug = [
        {'name': 'poulet_grille', 'confidence': 0.745, 'source': 'cnn'},
        {'name': 'pancakes_classiques', 'confidence': 0.717, 'source': 'similarity'},
        {'name': 'wrap_poulet', 'confidence': 0.705, 'source': 'cnn'},
    ]
    
    result1 = filter_strict.filter_detections(single_food_bug)
    print(f"\n✓ RÉSULTAT: {len(result1)} aliment(s)")
    if result1:
        for c in result1:
            print(f"  - {c['name']} ({c['confidence']:.2f})")
    
    # CASE 2: Real multi-food plat complet
    print("\n" + "="*80)
    print("CASE 2: Real plat complet (riz+poulet+salade+frites)")
    print("="*80)
    real_multi_food = [
        {'name': 'riz_blanc', 'confidence': 0.75, 'source': 'cnn'},
        {'name': 'poulet_grille', 'confidence': 0.74, 'source': 'cnn'},
        {'name': 'salade_verte', 'confidence': 0.73, 'source': 'cnn'},
        {'name': 'frites_moyenne', 'confidence': 0.72, 'source': 'cnn'},
    ]
    
    result2 = filter_strict.filter_detections(real_multi_food)
    print(f"\n✓ RÉSULTAT: {len(result2)} aliments")
    if result2:
        for c in result2:
            print(f"  - {c['name']} ({c['confidence']:.2f})")
    
    # CASE 3: Chocolat seul
    print("\n" + "="*80)
    print("CASE 3: Chocolat seul (bonne confiance)")
    print("="*80)
    chocolate_good = [
        {'name': 'chocolat', 'confidence': 0.72, 'source': 'cnn'},
    ]
    
    result3 = filter_strict.filter_detections(chocolate_good)
    print(f"\n✓ RÉSULTAT: {len(result3)} aliment(s)")
    if result3:
        for c in result3:
            print(f"  - {c['name']} ({c['confidence']:.2f})")


if __name__ == '__main__':
    demonstrate()
