"""
Image Similarity Matcher using MobileNetV2 Deep Features + Color Histograms.
Designed for few-shot food recognition with limited reference images.

Uses cosine similarity in deep feature space (1280-dimensional MobileNetV2 embeddings)
which is far more robust than ORB/AKAZE keypoint matching for food images.

Enhanced with data augmentation to improve accuracy with few reference images.

FIXES v2:
- find_match() retourne maintenant aussi les top_matches secondaires FIABLES
- map_category_to_food() supporte plusieurs catégories simultanément
- _detect_multiple_regions() : nouvelle méthode pour détecter plusieurs aliments
  en divisant l'image en zones et en agrégeant les votes par région
- Seuils SECONDARY_MATCH_THRESHOLD plus permissifs pour les aliments secondaires
"""

import cv2
import numpy as np
import os
import logging
import pickle
from pathlib import Path

logger = logging.getLogger("SimilarityMatcher")

# ============================================================================
# CATEGORY MAPPING: directory name -> list of NUTRITION_DATA keys
# Maps folder names in data/raw/ to actual food entries in the knowledge base.
# Multiple mappings allow context-aware disambiguation.
# ============================================================================
CATEGORY_FOOD_MAPPING = {
    # Specific subcategories (leaf directories with images)
    "pomme": "pomme",
    "autres_fruits": ["banane", "orange", "fraise", "kiwi", "poire", "raisin", "melon", "pastèque", "abricot", "cerise"],
    "burger": ["burger_classique", "burger_double", "burger_poulet"],
    "pancake": "pancakes_classiques",
    "shawarma": "wrap_poulet",
    "pizza": "pizza",
    "glace": "glace",
    "milkshake": "milkshake_fraise",
    "frites_maison": ["frites_moyenne", "frites_grande"],
    "frites": ["frites_moyenne", "frites_grande"],
    "legumes_variés": ["salade_verte", "brocoli", "haricots_verts", "courgette", "aubergine", "poivron", "épinard"],
    "legumes_vari├®s": ["salade_verte", "brocoli", "haricots_verts", "courgette", "aubergine", "poivron", "épinard"],
    "legumes_varies": ["salade_verte", "brocoli", "haricots_verts", "courgette", "aubergine", "poivron", "épinard"],
    "pommes_de_terre": "pomme_de_terre_vapeur",
    "oeufs": ["oeuf", "oeufs_brouilles", "oeufs_coque"],
    "lasagne": "lasagnes",
    "lasagnes_legumes": "lasagnes_legumes",
    "macaroni": "pates_completes",
    "pates_generiques": "pates_completes",
    "spaghetti": "spaghetti_bolognaise",
    "spaghetti_crevettes": "spaghetti_bolognaise",
    "escalope_panee": ["escalope_poulet_pane", "escalope_veau_pane", "cordons_bleus", "schnitzel"],
    "poulet": ["poulet_grille", "poulet_frit"],
    "viande_hachee": ["steak_hache", "steak_boeuf"],
    "viande_sauce": ["boulettes_viande", "steak_boeuf"],
    "riz": ["riz_blanc", "riz_complet"],
    "riz_blanc": "riz_blanc",
    "riz_complet": "riz_complet",
    "couscous": "couscous",
    "salade": "salade_verte",
    "champignon": "champignon_cuit",
    "champignons": "champignon_cuit",
    "tomate": "tomate",
    "carotte": "carotte",
    "brocoli": "brocoli",
    "courgette": "courgette",
    "aubergine": "aubergine",
    "poivron": "poivron",
    "epinard": "épinard",
    "chou_fleur": "chou_fleur",
    "concombre": "concombre",
    "saumon": "saumon",
    "poisson": ["poisson_blanc", "saumon", "poisson_pane"],
    "divers": None,
    "general": None,
    # Parent/broad directories (fallback)
    "desserts": None,
    "fast food": ["burger_classique", "frites_moyenne", "nuggets_poulet_6pcs", "pizza"],
    "fast_food": ["burger_classique", "frites_moyenne", "nuggets_poulet_6pcs", "pizza"],
    "fruits": ["pomme", "banane", "orange", "fraise", "kiwi", "poire", "raisin", "melon"],
    "legumes": ["salade_verte", "carotte", "brocoli", "tomate", "courgette", "aubergine", "poivron", "haricots_verts"],
    "les legumes": ["salade_verte", "carotte", "brocoli", "tomate", "courgette", "aubergine"],
    "les pattes": "pates_completes",
    "les sucres": "chocolat",  # ← CHOCOLAT directement, pas glace!
    "plats_pates": "pates_completes",
    "proteines_generiques": ["poulet_grille", "steak_boeuf", "poisson_blanc", "saumon"],
    "proteins": ["poulet_grille", "steak_boeuf", "poisson_blanc", "saumon"],
    "viandes": ["steak_boeuf", "steak_hache", "poulet_grille", "poulet_frit"],
}

# Color profiles per food category to help disambiguation
FOOD_COLOR_PROFILES = {
    "pomme": {"dominant_hue_range": (0, 30), "alt_hue_range": (35, 85)},
    "banane": {"dominant_hue_range": (20, 35)},
    "orange": {"dominant_hue_range": (10, 25)},
    "fraise": {"dominant_hue_range": (0, 10)},
    "cerise": {"dominant_hue_range": (0, 10)},
    "abricot": {"dominant_hue_range": (10, 25)},
    "kiwi": {"dominant_hue_range": (35, 75)},
    "melon": {"dominant_hue_range": (12, 28)},
    "pastèque": {"dominant_hue_range": (0, 10)},
    "raisin": {"dominant_hue_range": (120, 160), "alt_hue_range": (35, 55)},
    "salade_verte": {"dominant_hue_range": (35, 85)},
    "carotte": {"dominant_hue_range": (10, 25)},
    "tomate": {"dominant_hue_range": (0, 10)},
    "brocoli": {"dominant_hue_range": (35, 75)},
    "courgette": {"dominant_hue_range": (35, 75)},
    "aubergine": {"dominant_hue_range": (120, 160)},
    "poivron": {"dominant_hue_range": (0, 10), "alt_hue_range": (35, 75)},
    "épinard": {"dominant_hue_range": (35, 75)},
    "haricots_verts": {"dominant_hue_range": (35, 75)},
    "chou_fleur": {"low_saturation": True},
    "concombre": {"dominant_hue_range": (35, 75)},
    "riz_blanc": {"low_saturation": True},
    "riz_complet": {"dominant_hue_range": (15, 30)},
    "couscous": {"dominant_hue_range": (20, 35), "low_saturation": True},
    "poulet_grille": {"dominant_hue_range": (15, 30)},
    "poulet_frit": {"dominant_hue_range": (15, 30)},
    "steak_boeuf": {"dominant_hue_range": (0, 15)},
    "saumon": {"dominant_hue_range": (5, 20)},
    "poisson_blanc": {"low_saturation": True},
    "lasagnes": {"dominant_hue_range": (10, 25)},
}

# ============================================================================
# SEUILS POUR LA DÉTECTION MULTI-ALIMENTS
# ============================================================================
# Seuil pour le match principal (image complète)
# BAISSÉ de 0.55 à 0.45 pour permettre la détection d'aliments simples comme le chocolat
PRIMARY_MATCH_THRESHOLD = 0.45

# Seuil pour les aliments secondaires (régions)
SECONDARY_MATCH_THRESHOLD = 0.38

# Confiance minimale pour qu'un vote de région compte
REGION_VOTE_MIN_CONF = 0.35

# Nombre minimum de régions pour valider un secondaire multi-région
MIN_REGIONS_FOR_SECONDARY = 2

# Nombre max d'aliments secondaires à retourner
MAX_SECONDARY_RESULTS = 4


class ImageSimilarityMatcher:
    """
    Few-shot food recognition using:
    1. MobileNetV2 deep features (primary - 70% weight)
    2. Color histograms in HSV space (secondary - 20% weight)
    3. Texture features via LBP (tertiary - 10% weight)

    Enhanced with data augmentation (flip, brightness, crop) to turn
    1-3 reference images into 6-18 virtual samples per category.

    v2: Ajout de detect_multiple_foods() pour plats composés.
    """

    def __init__(self, raw_data_dir):
        self.raw_data_dir = raw_data_dir
        self.index = []
        self.feature_extractor = None

        # ORB as fallback if MobileNetV2 fails
        self.orb = cv2.ORB_create(nfeatures=3000)
        self.bf = cv2.BFMatcher(cv2.NORM_HAMMING)

        self._load_feature_extractor()

    def _load_feature_extractor(self):
        """Load MobileNetV2 pretrained on ImageNet as feature extractor."""
        try:
            from tensorflow.keras.applications import MobileNetV2
            self.feature_extractor = MobileNetV2(
                weights='imagenet',
                include_top=False,
                pooling='avg',
                input_shape=(224, 224, 3)
            )
            logger.info("MobileNetV2 feature extractor loaded successfully")
        except Exception as e:
            logger.warning(f"Could not load MobileNetV2: {e}")
            logger.warning("Falling back to ORB-only matching (less accurate)")
            self.feature_extractor = None

    def _extract_deep_features(self, img_bgr):
        """Extract 1280-dimensional deep features using MobileNetV2."""
        if self.feature_extractor is None:
            return None
        try:
            from tensorflow.keras.applications.mobilenet_v2 import preprocess_input
            img_rgb = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2RGB)
            img_resized = cv2.resize(img_rgb, (224, 224))
            img_array = np.expand_dims(img_resized.astype(np.float32), axis=0)
            img_array = preprocess_input(img_array)
            features = self.feature_extractor.predict(img_array, verbose=0)
            return features.flatten()
        except Exception as e:
            logger.error(f"Deep feature extraction error: {e}")
            return None

    def _extract_color_histogram(self, img_bgr):
        """Extract multi-bin color histogram in HSV space."""
        try:
            hsv = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2HSV)
            hist = cv2.calcHist(
                [hsv], [0, 1, 2], None,
                [16, 16, 8], [0, 180, 0, 256, 0, 256]
            )
            cv2.normalize(hist, hist)
            return hist.flatten()
        except Exception:
            return None

    def _extract_texture_features(self, img_bgr):
        """Extract texture features using Local Binary Pattern (LBP)-like approach."""
        try:
            gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
            gray = cv2.resize(gray, (128, 128))
            gx = cv2.Sobel(gray, cv2.CV_64F, 1, 0, ksize=3)
            gy = cv2.Sobel(gray, cv2.CV_64F, 0, 1, ksize=3)
            magnitude = np.sqrt(gx**2 + gy**2)
            angle = np.arctan2(gy, gx) * 180 / np.pi + 180
            hist_mag, _ = np.histogram(magnitude, bins=16, range=(0, 500))
            hist_ang, _ = np.histogram(angle, bins=18, range=(0, 360))
            texture = np.concatenate([hist_mag, hist_ang]).astype(np.float32)
            texture = texture / (np.linalg.norm(texture) + 1e-7)
            return texture
        except Exception:
            return None

    def _extract_orb_features(self, img_bgr):
        """Extract ORB keypoint descriptors (fallback method)."""
        try:
            gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)
            _, des = self.orb.detectAndCompute(gray, None)
            return des
        except Exception:
            return None

    def _cosine_similarity(self, a, b):
        """Compute cosine similarity between two feature vectors."""
        if a is None or b is None:
            return 0.0
        dot = np.dot(a, b)
        norm_a = np.linalg.norm(a)
        norm_b = np.linalg.norm(b)
        if norm_a == 0 or norm_b == 0:
            return 0.0
        return float(dot / (norm_a * norm_b))

    def _generate_augmentations(self, img):
        """
        Generate augmented versions of an image for better matching.
        Returns list of augmented images (including the original).
        """
        augmented = [img]
        h, w = img.shape[:2]

        augmented.append(cv2.flip(img, 1))

        bright = cv2.convertScaleAbs(img, alpha=1.15, beta=20)
        augmented.append(bright)

        dark = cv2.convertScaleAbs(img, alpha=0.85, beta=-20)
        augmented.append(dark)

        margin_h, margin_w = h // 10, w // 10
        if margin_h > 5 and margin_w > 5:
            cropped = img[margin_h:h-margin_h, margin_w:w-margin_w]
            cropped = cv2.resize(cropped, (w, h))
            augmented.append(cropped)

        M = cv2.getRotationMatrix2D((w/2, h/2), -10, 1.0)
        rotated = cv2.warpAffine(img, M, (w, h), borderMode=cv2.BORDER_REFLECT)
        augmented.append(rotated)

        return augmented

    def build_index(self):
        """
        Index all reference images in data/raw/ with deep features, histograms,
        and texture features. Applies data augmentation to multiply effective
        training samples.

        Caches index to disk to avoid expensive MobileNetV2 re-extraction on startup.

        Handles Unicode paths (é, è, etc.) by reading files as byte arrays
        when cv2.imread fails on non-ASCII paths.
        """
        cache_path = os.path.join(os.path.dirname(self.raw_data_dir), '.index_cache.pkl')
        if os.path.exists(cache_path):
            try:
                with open(cache_path, 'rb') as f:
                    self.index = pickle.load(f)
                logger.info(f"Loaded {len(self.index)} cached index entries")
                return
            except Exception as e:
                logger.warning(f"Could not load index cache: {e}. Rebuilding...")

        self.index = []
        raw_path = Path(self.raw_data_dir)
        if not raw_path.exists():
            logger.warning(f"Raw data directory not found: {self.raw_data_dir}")
            return

        extensions = {'.jpg', '.jpeg', '.png', '.webp', '.bmp'}
        count = 0
        original_count = 0
        failed = 0

        all_items = list(raw_path.rglob('*'))
        image_items = [f for f in all_items if f.suffix.lower() in extensions and f.is_file()]
        logger.info(f"Found {len(image_items)} image files to index out of {len(all_items)} total items")

        for img_idx, img_path_obj in enumerate(image_items):
            if img_idx % 50 == 0:
                logger.info(f"Processing image {img_idx+1}/{len(image_items)}...")

            try:
                img = cv2.imread(str(img_path_obj))

                if img is None:
                    try:
                        with open(img_path_obj, 'rb') as f:
                            raw_bytes = f.read()
                        img_bytes = np.frombuffer(raw_bytes, dtype=np.uint8)
                        img = cv2.imdecode(img_bytes, cv2.IMREAD_COLOR)
                    except Exception:
                        pass

                if img is None:
                    logger.debug(f"Could not load image: {img_path_obj.name}")
                    failed += 1
                    continue

                category = img_path_obj.parent.name
                original_count += 1

                augmented_images = self._generate_augmentations(img)

                for i, aug_img in enumerate(augmented_images):
                    deep_feats = self._extract_deep_features(aug_img)
                    color_hist = self._extract_color_histogram(aug_img)
                    texture_feats = self._extract_texture_features(aug_img)
                    orb_des = self._extract_orb_features(aug_img) if i == 0 else None

                    self.index.append({
                        "path": str(img_path_obj),
                        "category": category,
                        "deep_features": deep_feats,
                        "color_hist": color_hist,
                        "texture_features": texture_feats,
                        "orb_des": orb_des,
                        "is_augmented": i > 0,
                    })
                    count += 1

            except Exception as e:
                logger.debug(f"Error indexing {img_path_obj.name}: {str(e)[:100]}")
                failed += 1
                continue

        logger.info(f"✅ Built index: {original_count} original images, {count} with augmentations. Failed: {failed}")

        if count == 0 and original_count == 0:
            logger.error(f"⚠️  NO IMAGES INDEXED! Check if {self.raw_data_dir} contains valid image files")

        try:
            cache_dir = os.path.dirname(cache_path)
            os.makedirs(cache_dir, exist_ok=True)
            with open(cache_path, 'wb') as f:
                pickle.dump(self.index, f)
            logger.info(f"Index cached to {cache_path}")
        except Exception as e:
            logger.warning(f"Could not save index cache: {e}")

        categories = set(i['category'] for i in self.index)
        logger.info(
            f"Indexed {count} images ({original_count} originals + "
            f"{count - original_count} augmented) across {len(categories)} categories"
            f"{f' ({failed} failed)' if failed else ''}"
        )
        logger.info(f"Categories: {sorted(categories)}")

    def _detect_composition_type(self, img_bgr):
        """
        Detect if image contains a simple ingredient (single main color)
        or a complete dish (multiple components visible).

        Returns: 'simple' or 'complete'
        """
        try:
            hsv = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2HSV)
            h, s, v = cv2.split(hsv)

            hue_bins = [0, 30, 60, 90, 120, 150, 180]
            bin_counts = []
            for i in range(len(hue_bins)-1):
                mask = cv2.inRange(h, hue_bins[i], hue_bins[i+1])
                count = np.count_nonzero(mask)
                if count > 0:
                    bin_counts.append(count)

            num_colors = len([c for c in bin_counts if c > (img_bgr.size / 30)])

            if num_colors <= 1:
                return 'simple'
            else:
                return 'complete'
        except:
            return 'unknown'

    def _detect_advanced_colors(self, img_bgr):
        """
        Advanced multi-criteria color detection per food type.
        Returns dict: {food_name: confidence} with multi-criteria analysis.
        """
        results = {}
        try:
            hsv = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2HSV)
            h, s, v = cv2.split(hsv)
            gray = cv2.cvtColor(img_bgr, cv2.COLOR_BGR2GRAY)

            # POULET / ESCALOPE: Orange-brown (10-25°) + texture analysis
            try:
                gold_mask = cv2.inRange(h, 10, 25)
                gold_ratio = np.count_nonzero(gold_mask) / gold_mask.size if gold_mask.size > 0 else 0

                if gold_ratio > 0.05:
                    sobel_x = cv2.Sobel(gray, cv2.CV_64F, 1, 0, ksize=3)
                    sobel_y = cv2.Sobel(gray, cv2.CV_64F, 0, 1, ksize=3)
                    magnitude = np.sqrt(sobel_x**2 + sobel_y**2)
                    texture_score = np.mean(magnitude) / 255.0

                    edges = cv2.Canny(gray, 50, 150)
                    edge_density = np.count_nonzero(edges) / edges.size

                    if texture_score > 0.03:
                        if edge_density > 0.15:
                            escalope_conf = min(0.95, 0.45 + (gold_ratio * 0.4) + (edge_density * 0.3))
                            results['escalope_poulet_pane'] = escalope_conf
                            results['poulet_grille'] = escalope_conf * 0.8
                        else:
                            poulet_conf = min(0.95, 0.40 + (gold_ratio * 0.6) + (texture_score * 0.4))
                            results['poulet_grille'] = poulet_conf
            except:
                pass

            # FRITES: Orange (8-22°) + long shapes
            try:
                orange_mask = cv2.inRange(h, 8, 22)
                orange_ratio = np.count_nonzero(orange_mask) / orange_mask.size if orange_mask.size > 0 else 0

                contours, _ = cv2.findContours(orange_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
                aspect_ratios = []
                for cnt in contours:
                    if cv2.contourArea(cnt) > 10:
                        x, y, w_cnt, h_cnt = cv2.boundingRect(cnt)
                        if h_cnt > 0:
                            aspect_ratios.append(w_cnt / h_cnt if w_cnt >= h_cnt else h_cnt / w_cnt)

                long_aspect = np.mean([ar for ar in aspect_ratios if ar > 2.5]) if any(ar > 2.5 for ar in aspect_ratios) else 0

                s_masked = s[orange_mask > 0]
                s_uniformity = 0
                if len(s_masked) > 0:
                    s_var = np.std(s_masked)
                    s_uniformity = max(0, 1.0 - (s_var / 100.0))

                if orange_ratio > 0.1 and long_aspect > 0:
                    frites_conf = min(0.95, 0.35 + (orange_ratio * 0.4) + (s_uniformity * 0.3) + (min(1.0, long_aspect/4.0) * 0.3))
                    results['frites_moyenne'] = frites_conf
            except:
                pass

            # SALADE: Green (35-85°) + hue variation
            try:
                green_mask = cv2.inRange(h, 35, 85)
                green_ratio = np.count_nonzero(green_mask) / green_mask.size if green_mask.size > 0 else 0

                h_masked = h[green_mask > 0]
                hue_variation = 0
                if len(h_masked) > 10:
                    hue_variation = np.std(h_masked) / 180.0

                if hue_variation > 0.10:
                    salade_conf = min(0.95, 0.30 + (green_ratio * 0.6) + (hue_variation * 0.4))
                    results['salade_verte'] = salade_conf
            except:
                pass

            # RIZ: White/cream (V > 180) + visible grains
            try:
                white_mask = cv2.inRange(v, 180, 255)
                white_ratio = np.count_nonzero(white_mask) / white_mask.size if white_mask.size > 0 else 0

                _, binary = cv2.threshold(gray, 200, 255, cv2.THRESH_BINARY)
                num_labels, _ = cv2.connectedComponents(binary)
                granule_score = min(1.0, num_labels / 20.0) if num_labels > 5 else 0

                if white_ratio > 0.15 and granule_score > 0.10:
                    riz_conf = min(0.95, 0.30 + (white_ratio * 0.5) + (granule_score * 0.5))
                    results['riz_blanc'] = riz_conf
            except:
                pass

            # CHAMPIGNON: Brown (5-25°) + smooth + rounded
            try:
                brown_mask = cv2.inRange(h, 5, 25)
                brown_ratio = np.count_nonzero(brown_mask) / brown_mask.size if brown_mask.size > 0 else 0

                edges = cv2.Canny(gray, 50, 150)
                smoothness = 1.0 - (np.count_nonzero(edges) / edges.size) if edges.size > 0 else 0

                contours, _ = cv2.findContours(brown_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
                circularities = []
                for cnt in contours:
                    area = cv2.contourArea(cnt)
                    perimeter = cv2.arcLength(cnt, True)
                    if perimeter > 0:
                        circularity = 4 * np.pi * area / (perimeter ** 2)
                        circularities.append(circularity)

                avg_roundness = np.mean(circularities) if circularities else 0

                if brown_ratio > 0.1 and smoothness > 0.20:
                    champ_conf = min(0.95, 0.25 + (brown_ratio * 0.35) + (smoothness * 0.35) + (avg_roundness * 0.30))
                    results['champignon_cuit'] = champ_conf
            except:
                pass

            # TOMATE: Red (0-10° or 170-180°) + high brightness
            try:
                red_mask1 = cv2.inRange(h, 0, 10)
                red_mask2 = cv2.inRange(h, 170, 180)
                red_mask = cv2.bitwise_or(red_mask1, red_mask2)
                red_ratio = np.count_nonzero(red_mask) / red_mask.size if red_mask.size > 0 else 0

                v_masked = v[red_mask > 0]
                brightness = np.mean(v_masked) / 255.0 if len(v_masked) > 0 else 0

                if red_ratio > 0.1 and brightness > 0.70:
                    tomate_conf = min(0.95, 0.25 + (red_ratio * 0.5) + (brightness * 0.5))
                    results['tomate'] = tomate_conf
            except:
                pass

            # LASAGNES: Golden/orange (10-30°) + layered texture
            try:
                golden_mask = cv2.inRange(h, 10, 30)
                golden_ratio = np.count_nonzero(golden_mask) / golden_mask.size if golden_mask.size > 0 else 0

                if golden_ratio > 0.15:
                    sobel_y = cv2.Sobel(gray, cv2.CV_64F, 0, 1, ksize=3)
                    layer_score = np.std(sobel_y) / 255.0
                    h_diversity = np.std(h) / 180.0

                    if layer_score > 0.05:
                        lasagne_conf = min(0.95, 0.35 + (golden_ratio * 0.3) + (layer_score * 0.4) + (h_diversity * 0.2))
                        results['lasagnes'] = lasagne_conf
            except:
                pass

        except Exception as e:
            logger.debug(f"Error in advanced color detection: {e}")

        return results

    def _score_image(self, query_deep, query_hist, query_texture, query_orb, item):
        """
        Calcule le score de similarité entre les features query et un item indexé.
        Retourne un score normalisé entre 0 et 1.
        """
        score = 0.0
        weight_total = 0.0

        if query_deep is not None and item['deep_features'] is not None:
            deep_sim = self._cosine_similarity(query_deep, item['deep_features'])
            score += deep_sim * 0.70
            weight_total += 0.70

        if query_hist is not None and item['color_hist'] is not None:
            color_sim = cv2.compareHist(
                np.float32(query_hist),
                np.float32(item['color_hist']),
                cv2.HISTCMP_CORREL
            )
            score += max(0, color_sim) * 0.15
            weight_total += 0.15

        if query_texture is not None and item.get('texture_features') is not None:
            tex_sim = self._cosine_similarity(query_texture, item['texture_features'])
            score += max(0, tex_sim) * 0.15
            weight_total += 0.15

        if query_deep is None and query_orb is not None and item.get('orb_des') is not None:
            try:
                matches = self.bf.knnMatch(query_orb, item['orb_des'], k=2)
                good = []
                for match_pair in matches:
                    if len(match_pair) == 2:
                        m, n = match_pair
                        if m.distance < 0.75 * n.distance:
                            good.append(m)
                orb_score = min(len(good) / 50.0, 1.0)
                score += orb_score * 0.35
                weight_total += 0.35
            except Exception:
                pass

        if weight_total > 0:
            return score / weight_total
        return 0.0

    def find_match(self, image_path):
        """
        Find the best matching food category for a query image.
        Uses top-K voting per category for robust matching.

        Returns:
            dict with 'category', 'confidence', and 'top_matches' if match found.
            None if no confident match.
        """
        img = cv2.imread(image_path)
        if img is None:
            try:
                img_bytes = np.fromfile(image_path, dtype=np.uint8)
                img = cv2.imdecode(img_bytes, cv2.IMREAD_COLOR)
            except Exception:
                pass
        if img is None:
            logger.error(f"Cannot read image: {image_path}")
            return None

        if not self.index:
            logger.warning("Index is empty - no reference images available")
            return None

        query_deep = self._extract_deep_features(img)
        query_hist = self._extract_color_histogram(img)
        query_texture = self._extract_texture_features(img)
        query_orb = self._extract_orb_features(img)

        composition_type = self._detect_composition_type(img)
        advanced_colors = self._detect_advanced_colors(img)

        scores_per_image = []

        for item in self.index:
            normalized = self._score_image(query_deep, query_hist, query_texture, query_orb, item)

            # Pénaliser les plats complets si image est simple
            plat_complet_keywords = ["bolognaise", "complete", "garnie", "sauce", "fromagee"]
            if composition_type == 'simple' and any(keyword in item['category'].lower() for keyword in plat_complet_keywords):
                normalized = normalized * 0.50

            # Léger boost couleur avancée (très limité)
            for color_food, color_conf in advanced_colors.items():
                if color_food in item['category'] or item['category'] in color_food:
                    if color_conf > 0.75:
                        boost = color_conf * 0.03
                        normalized = min(0.99, normalized + boost)

            scores_per_image.append((item['category'], normalized))

        if not scores_per_image:
            return None

        # Meilleur score par catégorie
        category_best = {}
        for cat, sc in scores_per_image:
            if cat not in category_best or sc > category_best[cat]:
                category_best[cat] = sc

        sorted_cats = sorted(category_best.items(), key=lambda x: x[1], reverse=True)

        best_category = sorted_cats[0][0]
        best_confidence = sorted_cats[0][1]

        # Vérifier si la détection couleur peut aider à désambiguïser
        is_color_detected = False
        if best_confidence < 0.70 and advanced_colors:
            for tech_name, tech_conf in advanced_colors.items():
                if tech_conf > 0.65:
                    for idx, (cat, conf) in enumerate(sorted_cats[:3]):
                        if tech_name in cat or cat in tech_name:
                            is_color_detected = True
                            best_confidence = min(0.95, best_confidence + 0.10)

        margin = 0.0
        if len(sorted_cats) > 1:
            margin = best_confidence - sorted_cats[1][1]

        WEAK_CATEGORIES = {
            'lasagnes': 0.45,
            'lasagne': 0.45,
            'escalope_panee': 0.45,
            'escalope_poulet_pane': 0.45,
            'escalope_veau_pane': 0.45,
            'spaghetti': 0.45,
            'spaghetti_bolognaise': 0.45,
            'spaghetti_crevettes': 0.45,
            'pates_completes': 0.48,
            'macaroni': 0.48,
            'poulet': 0.48,
            'poulet_grille': 0.48,
            'poulet_frit': 0.48,
            'cordons_bleus': 0.48,
            'schnitzel': 0.48,
        }
        MATCH_THRESHOLD = WEAK_CATEGORIES.get(best_category, PRIMARY_MATCH_THRESHOLD)

        if best_confidence >= 0.75 or \
           (best_confidence >= MATCH_THRESHOLD and margin >= 0.04) or \
           (is_color_detected and best_confidence >= 0.50):
            logger.info(
                f"Match found: {best_category} (confidence: {best_confidence:.3f}, "
                f"margin: {margin:.3f}, threshold: {MATCH_THRESHOLD:.2f})"
            )
            MIN_REPORT_THRESHOLD = WEAK_CATEGORIES.get(best_category, PRIMARY_MATCH_THRESHOLD) - 0.05
            top_matches = [
                {"category": cat, "confidence": round(conf, 3)}
                for cat, conf in sorted_cats
                if conf >= MIN_REPORT_THRESHOLD
            ][:5]  # ← AUGMENTÉ de 3 à 5 pour exposer plus de candidats
            return {
                "category": best_category,
                "confidence": round(float(best_confidence), 3),
                "margin": round(float(margin), 3),
                "top_matches": top_matches,
                "all_sorted": [(cat, round(conf, 3)) for cat, conf in sorted_cats[:10]],
            }

        logger.info(
            f"No confident match. Best: {best_category} "
            f"({best_confidence:.3f}, margin={margin:.3f}) < threshold"
        )
        return None

    # ============================================================================
    # NOUVELLE MÉTHODE : detect_multiple_foods()
    # Retourne une LISTE d'aliments détectés dans l'image (pour les plats composés)
    # ============================================================================

    def detect_multiple_foods(self, image_path):
        """
        Détecte PLUSIEURS aliments dans une même image en combinant :
        1. Analyse de l'image complète (aliment principal)
        2. Analyse par régions (quadrants + centre) pour les secondaires
        3. Vote agrégé : un aliment secondaire est accepté s'il apparaît
           dans >= MIN_REGIONS_FOR_SECONDARY régions distinctes.

        Retourne:
            Liste de dicts:
            [
              {"category": "poulet_grille", "confidence": 0.72, "source": "full"},
              {"category": "riz_blanc",     "confidence": 0.55, "source": "region_3x"},
              {"category": "salade_verte",  "confidence": 0.48, "source": "region_2x"},
            ]
        """
        import tempfile

        img = cv2.imread(image_path)
        if img is None:
            try:
                img_bytes = np.fromfile(image_path, dtype=np.uint8)
                img = cv2.imdecode(img_bytes, cv2.IMREAD_COLOR)
            except Exception:
                pass
        if img is None:
            logger.error(f"Cannot read image for multi-detection: {image_path}")
            return []

        h, w = img.shape[:2]
        results = []
        seen_categories = set()

        # ===== ÉTAPE 1 : Image complète (aliment principal) =====
        full_match = self.find_match(image_path)
        if full_match and full_match['confidence'] >= PRIMARY_MATCH_THRESHOLD:
            results.append({
                "category": full_match['category'],
                "confidence": full_match['confidence'],
                "source": "full",
            })
            seen_categories.add(full_match['category'])
            logger.info(f"[multi] Principal: {full_match['category']} ({full_match['confidence']:.3f})")
            
            # Si confiance TRÈS ÉLEVÉE (>0.90), c'est un seul aliment simple
            # Ne pas chercher de secondaires pour éviter du bruit
            if full_match['confidence'] >= 0.90:
                logger.info(f"[multi] Confiance très élevée (>0.90) - aliment simple détecté, pas de secondaires")
                return results

        # ===== ÉTAPE 2 : Analyse par régions =====
        # Définir les régions : quadrants + centre + colonnes et bandes
        regions = self._get_image_regions(img)

        # Votes par catégorie : cat -> [(conf, region_name), ...]
        region_votes = {}

        for region_name, region_img in regions:
            if region_img is None or region_img.size == 0:
                continue
            rh, rw = region_img.shape[:2]
            if rh < 50 or rw < 50:
                continue

            try:
                # Sauvegarder la région dans un fichier temporaire
                tmp_fd, tmp_path = tempfile.mkstemp(suffix='.jpg')
                os.close(tmp_fd)
                cv2.imwrite(tmp_path, region_img)

                # Extraire les features pour cette région
                region_deep = self._extract_deep_features(region_img)
                region_hist = self._extract_color_histogram(region_img)
                region_texture = self._extract_texture_features(region_img)
                region_orb = self._extract_orb_features(region_img)

                # Scorer toutes les catégories de l'index pour cette région
                region_cat_best = {}
                for item in self.index:
                    sc = self._score_image(region_deep, region_hist, region_texture, region_orb, item)
                    cat = item['category']
                    if cat not in region_cat_best or sc > region_cat_best[cat]:
                        region_cat_best[cat] = sc

                # Garder les top catégories de cette région
                for cat, conf in region_cat_best.items():
                    if conf >= REGION_VOTE_MIN_CONF:
                        if cat not in region_votes:
                            region_votes[cat] = []
                        region_votes[cat].append((conf, region_name))

                os.remove(tmp_path)

            except Exception as e:
                logger.warning(f"[multi] Erreur région {region_name}: {e}")
                try:
                    os.remove(tmp_path)
                except:
                    pass

        # ===== ÉTAPE 3 : Agrégation des votes régions =====
        for cat, votes in region_votes.items():
            if cat in seen_categories:
                # Peut-être booster la confiance du principal si beaucoup de votes
                continue  # Ne pas dupliquer

            distinct_regions = set(v[1] for v in votes)
            num_regions = len(distinct_regions)
            best_conf = max(v[0] for v in votes)
            avg_conf = sum(v[0] for v in votes) / len(votes)

            # Bonus multi-région
            if num_regions >= MIN_REGIONS_FOR_SECONDARY:
                # Multi-région = plus fiable
                adjusted_conf = min(0.95, avg_conf * 1.05 + (num_regions - 1) * 0.02)
                threshold = SECONDARY_MATCH_THRESHOLD
            else:
                # Mono-région = moins fiable, pénalité légère
                adjusted_conf = best_conf * 0.92
                threshold = SECONDARY_MATCH_THRESHOLD + 0.08  # Plus strict pour mono-région

            if adjusted_conf >= threshold:
                results.append({
                    "category": cat,
                    "confidence": round(adjusted_conf, 3),
                    "source": f"region_{num_regions}x",
                    "num_regions": num_regions,
                })
                seen_categories.add(cat)
                logger.info(
                    f"[multi] Secondaire: {cat} ({adjusted_conf:.3f}) "
                    f"dans {num_regions} régions [{', '.join(distinct_regions)}]"
                )
            else:
                logger.debug(
                    f"[multi] Rejeté: {cat} ({adjusted_conf:.3f} < {threshold:.3f}) "
                    f"dans {num_regions} régions"
                )

        # ===== ÉTAPE 4 : Détection couleur avancée (complément) =====
        # Uniquement si peu de résultats trouvés jusqu'ici
        if len(results) < 3:
            advanced_colors = self._detect_advanced_colors(img)
            for food_name, color_conf in advanced_colors.items():
                if food_name in seen_categories:
                    continue
                # Seuil plus strict pour la couleur seule
                if color_conf >= 0.72:
                    # Vérifier si cet aliment apparaît dans les votes région
                    region_support = region_votes.get(food_name, [])
                    # Bonus si la couleur confirme les votes région
                    if region_support:
                        adjusted = min(0.90, color_conf * 0.8 + max(v[0] for v in region_support) * 0.2)
                    else:
                        adjusted = color_conf * 0.75  # Pénalité si seulement couleur

                    if adjusted >= SECONDARY_MATCH_THRESHOLD:
                        results.append({
                            "category": food_name,
                            "confidence": round(adjusted, 3),
                            "source": "color_advanced",
                        })
                        seen_categories.add(food_name)
                        logger.info(f"[multi] Couleur: {food_name} ({adjusted:.3f})")

        # Trier par confiance décroissante
        results.sort(key=lambda x: x['confidence'], reverse=True)

        # Limiter le total
        max_total = 1 + MAX_SECONDARY_RESULTS  # 1 principal + MAX_SECONDARY_RESULTS secondaires
        results = results[:max_total]

        logger.info(f"[multi] Résultat final: {[(r['category'], r['confidence']) for r in results]}")
        return results

    def _get_image_regions(self, img):
        """
        Découpe l'image en régions pour l'analyse multi-aliments.
        Retourne une liste de (nom_region, img_region).

        Régions : 4 quadrants + centre + bande horizontale haute/basse
        + bande verticale gauche/droite = jusqu'à 9 régions.
        """
        h, w = img.shape[:2]
        regions = []

        # Quadrants
        regions.append(("top_left",     img[0:h//2, 0:w//2]))
        regions.append(("top_right",    img[0:h//2, w//2:w]))
        regions.append(("bottom_left",  img[h//2:h, 0:w//2]))
        regions.append(("bottom_right", img[h//2:h, w//2:w]))

        # Centre (50% de l'image)
        ch, cw = h // 4, w // 4
        regions.append(("center", img[ch:h-ch, cw:w-cw]))

        # Tiers horizontaux
        t = h // 3
        regions.append(("top_third",    img[0:t,    :]))
        regions.append(("middle_third", img[t:2*t,  :]))
        regions.append(("bottom_third", img[2*t:h,  :]))

        # Tiers verticaux
        tv = w // 3
        regions.append(("left_third",   img[:, 0:tv]))
        regions.append(("mid_third_v",  img[:, tv:2*tv]))
        regions.append(("right_third",  img[:, 2*tv:w]))

        return regions

    def map_category_to_food(self, category, color_hint=None):
        """
        Map a directory category name to a NUTRITION_DATA key.
        Uses color_hint to disambiguate when a category maps to multiple foods.
        Returns None if no valid mapping exists.
        """
        if not category:
            return None

        cat_lower = category.lower().strip()

        mapping = None
        if cat_lower in CATEGORY_FOOD_MAPPING:
            mapping = CATEGORY_FOOD_MAPPING[cat_lower]
        else:
            cat_underscore = cat_lower.replace(" ", "_").replace("-", "_")
            if cat_underscore in CATEGORY_FOOD_MAPPING:
                mapping = CATEGORY_FOOD_MAPPING[cat_underscore]

        if mapping is None:
            from .nutrition_knowledge import NUTRITION_DATA
            if cat_lower in NUTRITION_DATA:
                return cat_lower
            cat_underscore = cat_lower.replace(" ", "_").replace("-", "_")
            if cat_underscore in NUTRITION_DATA:
                return cat_underscore
            logger.warning(f"No mapping found for category: '{category}'")
            return None

        if isinstance(mapping, str):
            from .nutrition_knowledge import NUTRITION_DATA
            return mapping if mapping in NUTRITION_DATA else None

        if isinstance(mapping, list) and len(mapping) > 0:
            from .nutrition_knowledge import NUTRITION_DATA
            valid = [f for f in mapping if f in NUTRITION_DATA]
            if not valid:
                return None
            if len(valid) == 1:
                return valid[0]
            return valid[0]

        return None

    def map_categories_to_foods(self, categories):
        """
        Mapper une liste de catégories (résultat de detect_multiple_foods)
        vers des clés NUTRITION_DATA valides.

        Entrée:
            [{"category": "poulet", "confidence": 0.72, ...}, ...]

        Sortie:
            [{"food_key": "poulet_grille", "confidence": 0.72, "source": "full"}, ...]
        """
        result = []
        seen_keys = set()

        for item in categories:
            cat = item.get('category', '')
            food_key = self.map_category_to_food(cat)
            if food_key and food_key not in seen_keys:
                seen_keys.add(food_key)
                result.append({
                    "food_key": food_key,
                    "confidence": item.get('confidence', 0.0),
                    "source": item.get('source', 'unknown'),
                    "original_category": cat,
                })

        return result