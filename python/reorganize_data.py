import os
import shutil
from pathlib import Path

# Déterminer le chemin du dossier raw de manière robuse
base_dir = os.path.dirname(os.path.abspath(__file__))
raw_dir = Path(os.path.join(base_dir, "data", "raw"))

if not raw_dir.exists():
    # Essai depuis la racine du projet
    raw_dir = Path("python/data/raw")

# Nouvelle structure cible
new_structure = {
    "fruits": {
        "pomme": ["pomme1.jpg", "pomme2.jpg", "p3.jpg"],
        "autres_fruits": ["d'autres fruits.jpg", "fruits.jpg"]
    },
    "legumes": {
        "legumes_variés": ["legumes.jpg"],
        "frites_maison": ["frit avec des sauces.jpg"],
        "pommes_de_terre": ["potatos comme frittes mais rond.jpg"]
    },
    "plats_pates": {
        "spaghetti": ["spagetti juste avec sauce.jpg"],
        "spaghetti_crevettes": ["spagetti crevettes aussi.jpg", "spaggeti avec crevettes.jpg"],
        "lasagne": ["lasagne.jpg"],
        "macaroni": ["makarona.jpg"],
        "pates_generiques": ["des pattes.jpg"]
    },
    "viandes": {
        "poulet": ["chicken.jpg"],
        "escalope_panee": ["crunshy scalope.jpg", "scalope.jpg", "riz et scalope pannee.jpg"],
        "viande_hachee": ["des petits morceaux du viandes.jpg", "viande.jpg", "viande2.jpg"],
        "viande_sauce": ["sapgetti avec vaindes ronds.jpg", "du riz blanc et une sauce rouge.jpg"]
    },
    "fast_food": {
        "burger": ["burger et friites et jus du fast food.jpg"],
        "shawarma": ["sandwich shawarma.jpg"],
        "pancake": ["pancake.jpg"],
        "general": ["ce que a eviter et a manger.jpg"]
    },
    "oeufs": {
        "oeufs": ["ouef2.jpg", "ouefs.jpg"]
    },
    "desserts": {
        "glace": ["glace.jpg"],
        "milkshake": ["oreo milkshake.png"]
    },
    "proteines_generiques": {
        "divers": ["types de proteines.jpg"]
    }
}

# Créer la nouvelle structure et déplacer les fichiers
for category, subcats in new_structure.items():
    for subcat, files in subcats.items():
        # Créer le dossier cible
        target_dir = raw_dir / category / subcat
        target_dir.mkdir(parents=True, exist_ok=True)
        
        # Chercher et déplacer chaque fichier
        for filename in files:
            # Chercher dans tous les dossiers existants
            for current_dir in raw_dir.glob("*"):
                if current_dir.is_dir():
                    file_path = current_dir / filename
                    if file_path.exists():
                        shutil.move(str(file_path), str(target_dir / filename))
                        print(f"Déplacé: {filename} -> {category}/{subcat}")

print("✅ Réorganisation terminée!")