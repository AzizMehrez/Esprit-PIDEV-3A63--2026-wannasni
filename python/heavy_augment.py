#!/usr/bin/env python
"""
Heavy augmentation for food classes with few original images.
Creates 15 variants per original (instead of 7).
"""
import os
import numpy as np
from pathlib import Path
from PIL import Image, ImageEnhance
import random

DATA_DIR = Path(__file__).parent.parent / 'python' / 'data' / 'raw'
WEAK_CLASSES = ['lasagne', 'escalope_panee', 'spaghetti_bolognaise']
AUGMENTATION_VARIANTS = 15  # Increased from 7

def augment_image(img_path, output_folder, num_variants=15):
    """Create more aggressive augmentations"""
    try:
        img = Image.open(img_path).convert('RGB')
        w, h = img.size
        
        # Rotation + crop (5 variants)
        for i in range(5):
            angle = random.uniform(-20, 20)
            rotated = img.rotate(angle, expand=False, fillcolor='white')
            crop_box = (
                random.randint(0, max(1, w//15)),
                random.randint(0, max(1, h//15)),
                w - random.randint(0, max(1, w//15)),
                h - random.randint(0, max(1, h//15))
            )
            cropped = rotated.crop(crop_box).resize((224, 224))
            out = output_folder / f"aug_rotate_heavy_{i}_{img_path.stem}.jpg"
            cropped.save(out, quality=90)
        
        # Color variations (5 variants)
        for i in range(5):
            bright = ImageEnhance.Brightness(img).enhance(random.uniform(0.6, 1.4))
            contrast = ImageEnhance.Contrast(bright).enhance(random.uniform(0.7, 1.3))
            saturation = ImageEnhance.Color(contrast).enhance(random.uniform(0.6, 1.3))
            hue = ImageEnhance.Sharpness(saturation).enhance(random.uniform(0.8, 1.3))
            
            out = output_folder / f"aug_color_heavy_{i}_{img_path.stem}.jpg"
            hue.save(out, quality=90)
        
        # Perspective + zoom (5 variants)
        for i in range(5):
            flipped = img.transpose(Image.FLIP_LEFT_RIGHT) if random.random() > 0.5 else img
            zoom = random.uniform(0.7, 1.0)
            left = int((1 - zoom) * w / 2)
            top = int((1 - zoom) * h / 2)
            right = int((1 + zoom) * w / 2)
            bottom = int((1 + zoom) * h / 2)
            zoomed = flipped.crop((left, top, right, bottom)).resize((224, 224))
            
            out = output_folder / f"aug_zoom_heavy_{i}_{img_path.stem}.jpg"
            zoomed.save(out, quality=90)
        
        return True
    except Exception as e:
        print(f"  ✗ {img_path.name}: {str(e)[:40]}")
        return False

print("🔄 Heavy Augmentation Pipeline (15 variants per original)\n")

total_original = 0
total_augmented = 0

# Find all food images
for category_folder in sorted(DATA_DIR.iterdir()):
    if not category_folder.is_dir():
        continue
    
    for subfolder in category_folder.iterdir():
        if not subfolder.is_dir():
            continue
        
        images = list(subfolder.glob('*.jpg')) + list(subfolder.glob('*.jpeg'))
        # Only augment weak classes
        if subfolder.name.lower() not in WEAK_CLASSES:
            continue
        
        if not images:
            continue
        
        print(f"📁 {subfolder.relative_to(DATA_DIR)}:")
        
        for img_path in images:
            original_name = img_path.stem
            if 'aug_' in original_name:
                continue  # Skip already augmented
            
            if augment_image(img_path, subfolder, AUGMENTATION_VARIANTS):
                total_original += 1
                total_augmented += AUGMENTATION_VARIANTS
                print(f"  ✓ {img_path.name} → +{AUGMENTATION_VARIANTS} variants")

print(f"\n✅ Heavy augmentation complete!")
print(f"   Original (weak classes): {total_original}")
print(f"   New augmented variants: {total_augmented}")
print(f"   Total new images: {total_augmented}")
