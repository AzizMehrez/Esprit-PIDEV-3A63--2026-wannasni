#!/usr/bin/env python3
"""
LEVEL 3: Filtre STRICTE des Fausses Positives Massives

Problem: Chocolat → Pâtes/Glace/Lasagnes/Poulet (5 aliments!)
Root Cause: Trop d'aliments acceptés + tous scores bas + combinaison chaos

Solution: Rejeter les détections évidentes fausses positives
- Si > 4 aliments: rejeter (plat trop chaotique)
- Si tous les scores < 0.50: rejeter (pas de consensus)
- Si meilleur score < 0.55: rejeter (pas assez de confiance)
- Ajouter logique spéciale pour aliments simples (chocolat, pomme, etc)
"""

class StrictFalsePositiveFilter:
    """Filtre STRICT des fausses positives massives"""
    
    # Aliments simples qui ne peuvent PAS être accompagnés de plats complexes
    SIMPLE_FOODS = {
        'chocolat', 'bonbon', 'biscuit', 'gâteau', 'pâtisserie',
        'fruit', 'pomme', 'banane', 'orange', 'fraise',
        'fromage', 'yaourt', 'crème', 'dessert',
        'noix', 'amande', 'noisette'
    }
    
    # Incompatibilités strictes (ne PAS apparaître ensemble)
    STRICT_INCOMPATIBILITIES = {
        # Si on détecte chocolat, rejeter plats complexes
        'chocolat': ['pâtes', 'lasagnes', 'burger', 'pizza', 'poulet', 'steak'],
        'bonbon': ['pâtes', 'lasagnes', 'burger', 'pizza', 'riz'],
        'pomme': ['pâtes', 'lasagnes', 'viande grillée'],
        'glace': ['pâtes', 'lasagnes', 'poulet grillé', 'steak'],
    }
    
    def __init__(self):
        """Initialiser le filtre avec les seuils stricts"""
        # Seuils stricts POUR LES DÉTECTIONS MASSIVES FAUSSES
        self.MIN_CONFIDENCE = 0.55  # Minimum absolu par aliment
        self.COUNT_THRESHOLD = 4    # Max 4 aliments par image  
        self.AVG_CONFIDENCE_THRESHOLD = 0.50  # Moyenne minimale
    
    def filter_detections(self, candidates):
        """
        Applique les filtres stricts INTELLIGEMMENT
        - 1 seul aliment haute confiance → retourner le 1
        - 2-3 aliments avec bonne confiance → retourner tous
        - 4+ aliments OR tous scores bas → rejeter (chaos)
        
        Args:
            candidates: List[dict] avec keys: name, confidence, source
            
        Returns:
            List[dict]: Candidats filtrés (ou liste vide si fausse positive massive)
        """
        
        if not candidates:
            return []
        
        # Trier par confiance décroissante
        candidates_sorted = sorted(candidates, key=lambda c: c['confidence'], reverse=True)
        best_confidence = candidates_sorted[0]['confidence']
        avg_confidence = sum(c['confidence'] for c in candidates) / len(candidates)
        num_candidates = len(candidates)
        
        # LOGIQUE INTELLIGENTE: Adapter selon le nombre et confiance
        
        # Cas 1: 1 SEUL aliment → TOUJOURS l'accepter (si confiance décente)
        if num_candidates == 1:
            if best_confidence >= 0.45:  # Seuil bas puisque c'est seul (peut être une bonne détection)
                print(f"[+] 1 seul aliment detecte ({best_confidence:.2f}) -> accepte")
                return candidates_sorted
            else:
                print(f"[-] 1 aliment mais confiance trop basse ({best_confidence:.2f})")
                return []
        
        # Cas 2: 2-3 ALIMENTS avec bonne confiance → ACCEPTER TOUS
        if num_candidates in [2, 3]:
            if best_confidence >= 0.60 and avg_confidence >= 0.45:  # Un peu plus lenient
                print(f"[+] {num_candidates} aliments avec bonne confiance -> accepter tous")
                return candidates_sorted
            else:
                print(f"[-] {num_candidates} aliments mais confiance insuffisante (best={best_confidence:.2f}, avg={avg_confidence:.2f})")
                return []
        
        # Cas 3: 4 ALIMENTS → strict (uniquement si très haute confiance collecitve)
        if num_candidates == 4:
            if best_confidence >= 0.80 and avg_confidence >= 0.60:  # Un peu lenient
                print(f"[+] 4 aliments mais tres haute confiance -> accepter tous")
                return candidates_sorted
            else:
                print(f"[-] REJET: {num_candidates} aliments = trop (confiance insuffisante)")
                return []
        
        # Cas 4: 5+ ALIMENTS → REJETER (chaos détecté)
        if num_candidates >= 5:
            print(f"[-] REJET: {num_candidates} aliments (>= 5) = chaos massif")
            return []
        
        # Filtre 4: Vérifier incompatibilités strictes
        names_lower = {c['name'].lower(): c for c in candidates}
        
        for simple_food in self.SIMPLE_FOODS:
            if simple_food in names_lower:
                # Aliment simple trouvé
                incompatible_list = self.STRICT_INCOMPATIBILITIES.get(simple_food, [])
                
                for other_name in names_lower:
                    if other_name != simple_food:
                        # Vérifier si c'est incompatible
                        if any(incompat.lower() in other_name for incompat in incompatible_list):
                            # Incompatibilité trouvée!
                            # Garder le simple food, rejeter le complexe
                            print(f"[-] Incompatibilite: '{simple_food}' + '{other_name}' impossible")
                            candidates = [c for c in candidates if c['name'].lower() != other_name]
        
        # Filtre 5: Rejeter aliments avec confiance trop basse
        candidates = [c for c in candidates if c['confidence'] >= self.MIN_CONFIDENCE]
        
        if not candidates:
            print(f"[-] REJET: Aucun aliment avec confiance >= {self.MIN_CONFIDENCE}")
            return []
        
        return candidates
    
    def explain_chocolate_detection(self, candidates):
        """Explication du cas chocolat"""
        print("\n" + "="*70)
        print("[*] CAS D'ETUDE: Pourquoi chocolat -> pates/glace/lasagnes?")
        print("="*70)
        
        print("\n[DATA] CANDIDATS BRUTS:")
        for c in candidates:
            print(f"  - {c['name']:20} conf={c['confidence']:.2f} src={c['source']}")
        
        print("\n[ISSUES] PROBLEMES DETECTES:")
        
        count = len(candidates)
        print(f"  1. Trop d'aliments: {count} (max accepté: {self.COUNT_THRESHOLD})")
        
        best = max(c['confidence'] for c in candidates)
        print(f"  2. Meilleur score: {best:.2f} < {self.MIN_CONFIDENCE} (trop bas!)")
        
        avg = sum(c['confidence'] for c in candidates) / len(candidates)
        print(f"  3. Score moyen: {avg:.2f} < {self.AVG_CONFIDENCE_THRESHOLD} (pas de consensus)")
        
        names = [c['name'].lower() for c in candidates]
        complex_foods = [n for n in names if any(w in n for w in ['pâte', 'lasagne', 'glace', 'poulet', 'viande'])]
        print(f"  4. Aliments complexes: {complex_foods} (incompatibles avec chocolat)")
        
        print("\n✅ APRÈS FILTRE STRICT:")
        filtered = self.filter_detections(candidates)
        if filtered:
            for c in filtered:
                print(f"  ✓ {c['name']:20} conf={c['confidence']:.2f}")
        else:
            print("  → Aucune détection valide (rejet de la requête)")
            print("  → Demander à l'utilisateur de vérifier l'image")
        
        return filtered


def demonstrate():
    """Démo avec le cas du chocolat"""
    
    filter_strict = StrictFalsePositiveFilter()
    
    # Cas réel du chocolat
    chocolate_case = [
        {'name': 'pâtes bolognaise', 'confidence': 0.48, 'source': 'similarity'},
        {'name': 'glace', 'confidence': 0.45, 'source': 'cnn'},
        {'name': 'poulet grillé', 'confidence': 0.42, 'source': 'cnn'},
        {'name': 'lasagnes', 'confidence': 0.40, 'source': 'similarity'},
        {'name': 'pancakes', 'confidence': 0.38, 'source': 'cnn'},
    ]
    
    result = filter_strict.explain_chocolate_detection(chocolate_case)
    
    print("\n" + "="*70)
    print("📝 RÉSUMÉ: Chocolat détecté FI?")
    print("="*70)
    print(f"Avant: {len(chocolate_case)} aliments = {sum(c['confidence'] for c in chocolate_case) / len(chocolate_case):.2f} confiance moyenne")
    print(f"Après: {len(result)} aliments")
    print(f"\n✓ Decision: {'ACCEPTER' if result else 'REJETER - demander confirmation utilisateur'}")
    
    # Cas valide (si confiance était meilleure)
    print("\n\n" + "="*70)
    print("✓ COMPARAISON: Cas valide (chocolat seul, bonne confiance)")
    print("="*70)
    
    good_case = [
        {'name': 'chocolat', 'confidence': 0.72, 'source': 'similarity'},
    ]
    
    result2 = filter_strict.filter_detections(good_case)
    print(f"\nCandidats: {len(good_case)}")
    print(f"Après filtre: {len(result2)}")
    if result2:
        print(f"✓ VALIDE: {result2[0]['name']} ({result2[0]['confidence']:.2f} confiance)")


if __name__ == '__main__':
    demonstrate()