#!/usr/bin/env python3
"""
Diagnostic: Analyse des Données Actuelles vs Requises

Montre:
1. Quelles catégories d'aliments existent
2. Combien d'images par catégorie
3. Quelles catégories MANQUENT (crucial pour chocolat)
4. Structure optimale requise
"""

import os
from pathlib import Path
from collections import defaultdict

def diagnose_data_structure():
    """Analyse la structure des données actuelles"""
    
    base_path = Path("python/data/raw")
    
    print("\n" + "="*80)
    print("📊 DIAGNOSTIC: État Actuel des Données ML")
    print("="*80)
    
    if not base_path.exists():
        print(f"\n❌ ERREUR: {base_path} n'existe pas!")
        print("Créer avec: mkdir -p python/data/raw")
        return
    
    # Compter les images
    categories = defaultdict(int)
    total_images = 0
    
    for category_dir in sorted(base_path.iterdir()):
        if category_dir.is_dir():
            # Compter les images
            images = list(category_dir.glob('*.jpg')) + list(category_dir.glob('*.png'))
            count = len(images)
            categories[category_dir.name] = count
            total_images += count
    
    # Afficher résultats
    print(f"\n📁 Catégories Existantes: {len(categories)}")
    print(f"📸 Total d'images: {total_images}")
    
    print("\n" + "-"*80)
    print("CATÉGORIES ACTUELLES:")
    print("-"*80)
    
    for cat, count in sorted(categories.items()):
        status = "✓" if count > 30 else "⚠️ " if count > 0 else "❌"
        print(f"{status} {cat:30} | {count:4} images")
    
    # Identifier les manques
    print("\n" + "-"*80)
    print("ANALYSE: ALIMENTS MANQUANTS CRITIQUES")
    print("-"*80)
    
    REQUIRED_SIMPLE_FOODS = [
        'chocolat', 'bonbon', 'biscuit', 'gateau', 'pâtisserie',
        'pomme', 'banane', 'orange', 'fraise', 'kiwi',
        'fromage', 'yaourt', 'crème', 'dessert'
    ]
    
    REQUIRED_COMPLETE_DISHES = [
        'spaghetti_bolognaise', 'lasagnes', 'burger', 'pizza', 'couscous',
        'riz_frit', 'poulet_grille', 'steak_frites', 'salade_composee'
    ]
    
    print("\n🔴 ALIMENTS SIMPLES MANQUANTS (CRITIQUE!):")
    missing_simple = []
    for food in REQUIRED_SIMPLE_FOODS:
        if food not in categories:
            missing_simple.append(food)
            print(f"  ❌ {food:30} - ABSENT")
        elif categories[food] < 30:
            print(f"  ⚠️  {food:30} - Seulement {categories[food]} images (besoin 50+)")
        else:
            print(f"  ✓ {food:30} - OK ({categories[food]} images)")
    
    print("\n🟡 PLATS COMPLETS À AMÉLIORER:")
    for dish in REQUIRED_COMPLETE_DISHES:
        if dish not in categories:
            print(f"  ❌ {dish:28} - ABSENT ou peu d'images")
        elif categories[dish] < 50:
            print(f"  ⚠️  {dish:28} - Besoin +{50-categories[dish]} images")
        else:
            print(f"  ✓ {dish:28} - OK ({categories[dish]} images)")
    
    # Recommandations
    print("\n" + "-"*80)
    print("RECOMMANDATIONS PRIORITAIRES:")
    print("-"*80)
    
    if 'chocolat' not in categories:
        print("""
🔴 CRITICAL: Pas de chocolat!
   Action: mkdir -p python/data/raw/chocolat
   Ajouter: 50-100 images de chocolat
   Impact: Résoudra le problème "chocolat → pâtes"
        """)
    
    if missing_simple:
        print(f"""
🟡 À ajouter ({len(missing_simple)} aliments):
   {', '.join(missing_simple)}
   
   Cela permettra au modèle de distinguer:
   • Aliments simples (à détecter seuls)
   • Plats complexes (à détecter complètement)
        """)
    
    # Plan d'action
    print("\n" + "="*80)
    print("📋 PLAN D'ACTION")
    print("="*80)
    
    print("""
ÉTAPE 1: Créer répertoires manquants
  mkdir -p python/data/raw/{chocolat,bonbon,biscuit,gateau,yaourt,fromage}

ÉTAPE 2: Ajouter images (MINIMUM 50 par catégorie)
  • Chocolat: Au moins 50 images
  • Bonbon: 40 images
  • Biscuit: 40 images
  • Gateau: 40 images
  • Yaourt: 30 images
  • Fromage: 30 images

ÉTAPE 3: Vérifier l'ajout
  ls -l python/data/raw/chocolat | wc -l
  (Devrait afficher 50+)

ÉTAPE 4: Ré-entraîner modèles
  python python/retrain_models.py

ÉTAPE 5: Tester
  • Uploader photo de chocolat
  • Vérifier: "Chocolat" détecté, pas "pâtes"
    """)
    
    # Détail: Structure optimale
    print("\n" + "="*80)
    print("📊 STRUCTURE OPTIMALE APRÈS IMPLÉMENTATION")
    print("="*80)
    
    print("""
python/data/raw/
│
├── ALIMENTS SIMPLES (50+ images chacun)
│   ├── chocolat/              ← 100 images
│   ├── bonbon/                ← 50 images
│   ├── biscuit/               ← 50 images
│   ├── gateau/                ← 50 images
│   ├── pomme/                 ← 50 images (améliorer)
│   ├── banane/                ← 50 images
│   ├── fromage/               ← 40 images
│   ├── yaourt/                ← 40 images
│   └── ...autres
│
├── PLATS COMPLETS (100+ images chacun)
│   ├── spaghetti_bolognaise/  ← 100 images
│   ├── lasagnes/              ← 100 images
│   ├── burger/                ← 100 images
│   ├── pizza/                 ← 100 images
│   ├── couscous/              ← 100 images
│   └── ...autres
│
└── TOTAUX:
    • Aliments simples: 500+ images
    • Plats complets: 600+ images
    • TOTAL: 1100+ images
    """)
    
    # Résumé
    print("\n" + "="*80)
    print("✅ RÉSUMÉ")
    print("="*80)
    
    print(f"""
Images actuelles: {total_images}
Catégories: {len(categories)}

MANQUES CRITIQUES:
{len(missing_simple)} aliments simples manquants (y compris chocolat!)

APRÈS IMPLÉMENTATION:
✓ Modèle comprendra chocolat vs plats
✓ Détaillera tous ingrédients des plats
✓ Pas plus de "chocolat → pâtes"
✓ Calories correctes

ACTION: Commencez par ajouter images chocolat!
    """)


if __name__ == '__main__':
    diagnose_data_structure()
