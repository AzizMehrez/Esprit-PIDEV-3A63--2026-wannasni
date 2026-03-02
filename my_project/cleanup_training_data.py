#!/usr/bin/env python
"""
Training Data Cleanup Script
============================
Fixes the major data quality issues:
1. Moves misplaced images to correct categories
2. Removes/moves mixed-food images that confuse the classifier
3. Populates empty fries/ folder
4. Removes infographics/non-food images
5. Fixes duplicate folder issues (fast food/ vs fast_food/)
"""
import os
import shutil
from pathlib import Path

DATA_DIR = Path(r"C:\Users\bacco\OneDrive\Bureau\MonProjetFinal\python\data\raw")

def ensure_dir(path):
    """Create directory if it doesn't exist."""
    path.mkdir(parents=True, exist_ok=True)
    return path

def move_file(src, dst_folder, reason=""):
    """Move a file and all its augmentations to dst_folder."""
    dst_folder = ensure_dir(dst_folder)
    stem = src.stem
    parent = src.parent
    moved = 0
    
    # Move the original
    if src.exists():
        dst = dst_folder / src.name
        if not dst.exists():
            shutil.move(str(src), str(dst))
            moved += 1
            print(f"  MOVED: {src.name} -> {dst_folder.name}/ ({reason})")
        else:
            print(f"  SKIP (exists): {src.name}")
    
    # Move all augmentations of this file
    for aug_file in parent.glob(f"aug_*_{stem}.*"):
        dst = dst_folder / aug_file.name
        if not dst.exists():
            shutil.move(str(aug_file), str(dst))
            moved += 1
    
    if moved > 1:
        print(f"    + {moved - 1} augmentations moved")
    return moved

def delete_file(src, reason=""):
    """Delete a file and all its augmentations."""
    stem = src.stem
    parent = src.parent
    deleted = 0
    
    if src.exists():
        os.remove(str(src))
        deleted += 1
        print(f"  DELETED: {src.name} ({reason})")
    
    for aug_file in parent.glob(f"aug_*_{stem}.*"):
        os.remove(str(aug_file))
        deleted += 1
    
    if deleted > 1:
        print(f"    + {deleted - 1} augmentations deleted")
    return deleted

# =============================================================================
print("=" * 70)
print("STEP 1: Fix frites - Move chips/frites images from burger/ to fries/")
print("=" * 70)

fries_dir = DATA_DIR / "fast_food" / "fries"
ensure_dir(fries_dir)

burger_dir = DATA_DIR / "fast_food" / "burger"

# chips.jpg and chips2.jpg are actually frites/chips, not burgers
for name in ["chips.jpg", "chips2.jpg"]:
    f = burger_dir / name
    if f.exists():
        move_file(f, fries_dir, "chips/frites, not burger")

# Move images from "fast food/" (with space) that are frites
fast_food_space = DATA_DIR / "fast food"
for name in ["frites aussi.jpg", "frites seulement.jpg"]:
    f = fast_food_space / name
    if f.exists():
        move_file(f, fries_dir, "frites image in wrong folder")

# Move "plat contient beaucoup de frites et des sauces.jpg" to frites too
f = fast_food_space / "plat contient beaucoup de frites et des sauces.jpg"
if f.exists():
    move_file(f, fries_dir, "frites image")

# Copy frites_maison images to fries/ too (for better coverage)
frites_maison_dir = DATA_DIR / "legumes" / "frites_maison"
if frites_maison_dir.exists():
    for img in frites_maison_dir.glob("*.jpg"):
        if "aug_" not in img.stem:  # Only originals
            dst = fries_dir / img.name
            if not dst.exists():
                shutil.copy2(str(img), str(dst))
                print(f"  COPIED: {img.name} -> fries/ (from frites_maison)")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 2: Remove/move mixed-food images from burger/")
print("=" * 70)

# "burger et friites et jus du fast food.jpg" confuses the model
f = burger_dir / "burger et friites et jus du fast food.jpg"
if f.exists():
    delete_file(f, "mixed: burger+frites+jus confuses classifier")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 3: Fix 'fast food/' (with space) - move remaining to proper folders")
print("=" * 70)

if fast_food_space.exists():
    # chiken chawarma -> shawarma/
    f = fast_food_space / "chiken chawarma.jpg"
    if f.exists():
        move_file(f, DATA_DIR / "fast_food" / "shawarma", "chawarma -> shawarma")
    
    # pizza -> pizza/
    f = fast_food_space / "pizza.jpg"
    if f.exists():
        move_file(f, DATA_DIR / "fast_food" / "pizza", "pizza -> pizza folder")
    
    # Clean up if empty
    remaining = list(fast_food_space.iterdir())
    if not remaining:
        fast_food_space.rmdir()
        print(f"  Removed empty folder: 'fast food/'")
    else:
        print(f"  'fast food/' still has {len(remaining)} files: {[f.name for f in remaining]}")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 4: Remove non-food images and infographics")
print("=" * 70)

# Infographic in general/
f = DATA_DIR / "fast_food" / "general" / "ce que a eviter et a manger.jpg"
if f.exists():
    delete_file(f, "infographic, not food")

# "types de proteines.jpg" - infographic
f = DATA_DIR / "proteines_generiques" / "divers" / "types de proteines.jpg"
if f.exists():
    delete_file(f, "infographic, not food")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 5: Fix misplaced images in 'les pattes/' (rice/couscous in pasta)")
print("=" * 70)

les_pattes = DATA_DIR / "les pattes"
if les_pattes.exists():
    # Move riz images to riz/
    riz_dir = DATA_DIR / "riz"
    for name in ["riz blanc.jpg", "riz blanc aussi.jpg"]:
        f = les_pattes / name
        if f.exists():
            move_file(f, riz_dir, "rice image, not pasta")
    
    # Move couscous images - keep in les pattes for now but note it
    for name in ["coscous avec poulet.jpg", "couscous avec legumes.jpg"]:
        f = les_pattes / name
        if f.exists():
            print(f"  NOTE: {name} is couscous, not pasta (keeping for now)")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 6: Fix misplaced images in legumes/pommes_de_terre/")
print("=" * 70)

pdt_dir = DATA_DIR / "legumes" / "pommes_de_terre"
if pdt_dir.exists():
    # Soups are not potatoes
    for name in ["soupe blanche avec un peu du champinion au mileu.jpg",
                  "soupe rouge avec les legumes.jpg"]:
        f = pdt_dir / name
        if f.exists():
            delete_file(f, "soup, not potato - confuses model")
    
    # Beef dinner is not potato
    for f in pdt_dir.glob("Savory Ground Beef*"):
        if f.exists():
            delete_file(f, "beef dinner, not potato")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 7: Fix mixed-food images in proteins/")
print("=" * 70)

proteins_dir = DATA_DIR / "proteins"
if proteins_dir.exists():
    # Soup is not protein
    f = proteins_dir / "soupe blanche.jpg"
    if f.exists():
        delete_file(f, "soup, not a protein dish")
    
    # Mixed rice+sauce images confuse the model
    for name in ["riz blanc avec sauce rouge.jpg"]:
        f = proteins_dir / name
        if f.exists():
            delete_file(f, "rice+sauce, confuses protein detection")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 8: Fix mixed-food in viandes/escalope_panee/")
print("=" * 70)
        
esc_dir = DATA_DIR / "viandes" / "escalope_panee"
if esc_dir.exists():
    f = esc_dir / "riz et scalope pannee.jpg"
    if f.exists():
        # Keep it but note it - escalope is the primary subject
        print(f"  NOTE: {f.name} is mixed (rice+escalope) but keeping for escalope category")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 9: Fix mixed-food in viandes/viande_sauce/")
print("=" * 70)

vs_dir = DATA_DIR / "viandes" / "viande_sauce"
if vs_dir.exists():
    f = vs_dir / "du riz blanc et une sauce rouge.jpg"
    if f.exists():
        delete_file(f, "rice+sauce, no visible meat")

# =============================================================================
print("\n" + "=" * 70)
print("STEP 10: Summary")
print("=" * 70)

# Count images in fries/ now
fries_count = len(list(fries_dir.glob("*.jpg")))
print(f"\nFries folder now has: {fries_count} images")

# Count all images  
total = 0
for img in DATA_DIR.rglob("*"):
    if img.suffix.lower() in {'.jpg', '.jpeg', '.png', '.webp', '.bmp'} and img.is_file():
        total += 1
print(f"Total images in dataset: {total}")

print("\n*** DONE! Now delete the index cache and restart FastAPI ***")
print("  1. Delete: python/data/.index_cache.pkl")
print("  2. Restart: start_fastapi.bat")
