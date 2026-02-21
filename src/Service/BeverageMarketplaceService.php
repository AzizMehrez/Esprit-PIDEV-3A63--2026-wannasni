<?php

namespace App\Service;

use App\Entity\BeverageOrder;
use App\Entity\BeverageOrderItem;
use App\Entity\BeverageProduct;
use App\Entity\User;
use App\Repository\BeverageOrderRepository;
use App\Repository\BeverageProductRepository;
use Doctrine\ORM\EntityManagerInterface;

class BeverageMarketplaceService
{
    // Catalogue produits intégré — seeding par défaut
    private const PRODUCT_CATALOG = [
        // ─── Thés Premium ───
        ['name' => 'Matcha Ceremonial Grade Bio', 'category' => 'thé', 'short' => 'Matcha premium japonais de qualité cérémonielle',
         'desc' => 'Poudre de matcha de qualité cérémonielle provenant d\'Uji, Kyoto. Cultivé à l\'ombre pendant 3 semaines pour une saveur umami intense. Riche en L-théanine et catéchines EGCG. Couleur vert jade vif.',
         'price' => '89.00', 'volume' => '30g', 'brand' => 'Ippodo', 'origin' => 'Japon (Uji)',
         'calories' => 3, 'hydration' => 85, 'sugar_free' => true, 'caffeine_free' => false, 'bio' => true,
         'benefits' => ['Riche en antioxydants EGCG', 'Booste le métabolisme', 'Améliore la concentration', 'Énergie durable sans crash'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['100% feuilles de thé vert Camellia sinensis'], 'stock' => 25, 'featured' => true, 'rating' => '4.80', 'reviews' => 156, 'sales' => 420],

        ['name' => 'Thé Vert Sencha Biologique', 'category' => 'thé', 'short' => 'Sencha japonais bio aux notes herbacées',
         'desc' => 'Thé vert sencha de Shizuoka, cueilli au printemps. Notes herbacées fraîches, légèrement sucrées. Riche en vitamine C et polyphénols.',
         'price' => '45.00', 'volume' => '100g', 'brand' => 'Marukyu Koyamaen', 'origin' => 'Japon (Shizuoka)',
         'calories' => 2, 'hydration' => 88, 'sugar_free' => true, 'caffeine_free' => false, 'bio' => true,
         'benefits' => ['Antioxydant puissant', 'Aide à la digestion', 'Vitamine C naturelle'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['100% feuilles de thé vert Sencha bio'], 'stock' => 40, 'featured' => false, 'rating' => '4.60', 'reviews' => 89, 'sales' => 310],

        ['name' => 'Rooibos Rouge Bio d\'Afrique du Sud', 'category' => 'thé', 'short' => 'Rooibos naturellement sucré, sans caféine',
         'desc' => 'Rooibos premium du Cederberg, Afrique du Sud. Naturellement doux et sans caféine. Riche en antioxydants, minéraux et flavonoïdes.',
         'price' => '35.00', 'volume' => '150g', 'brand' => 'Cape Natural Tea', 'origin' => 'Afrique du Sud',
         'calories' => 2, 'hydration' => 92, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Sans caféine', 'Riche en minéraux', 'Anti-inflammatoire', 'Bon pour la peau'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['100% Rooibos bio (Aspalathus linearis)'], 'stock' => 55, 'featured' => true, 'rating' => '4.70', 'reviews' => 112, 'sales' => 380],

        // ─── Cafés ───
        ['name' => 'Café Arabica Éthiopie Yirgacheffe', 'category' => 'café', 'short' => 'Grains torréfiés medium, notes florales',
         'desc' => 'Café mono-origine d\'Éthiopie, berceau du café. Torréfaction moyenne. Notes de jasmin, agrumes et miel. Acidité vive et corps léger.',
         'price' => '55.00', 'volume' => '250g', 'brand' => 'Artisan Roasters', 'origin' => 'Éthiopie (Yirgacheffe)',
         'calories' => 3, 'hydration' => 60, 'sugar_free' => true, 'caffeine_free' => false, 'bio' => false,
         'benefits' => ['Stimulant naturel', 'Riche en antioxydants', 'Bénéfique pour le foie'],
         'regimes' => ['normal', 'sans_gluten'],
         'ingredients' => ['100% grains Arabica Éthiopie'], 'stock' => 30, 'featured' => true, 'rating' => '4.85', 'reviews' => 203, 'sales' => 520],

        ['name' => 'Décaféiné Swiss Water Process', 'category' => 'café', 'short' => 'Tout le goût sans la caféine',
         'desc' => 'Décaféiné par procédé à l\'eau suisse (Swiss Water), préservant 99.9% des arômes. Grains colombiens doux aux notes de chocolat et noisette.',
         'price' => '48.00', 'sale' => '39.00', 'volume' => '250g', 'brand' => 'Swiss Decaf', 'origin' => 'Colombie',
         'calories' => 2, 'hydration' => 78, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => false,
         'benefits' => ['Goût du café sans caféine', 'Antioxydants préservés', 'Adapté le soir'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['100% Arabica Colombie décaféiné naturellement'], 'stock' => 35, 'featured' => false, 'rating' => '4.50', 'reviews' => 67, 'sales' => 180],

        // ─── Infusions Bien-être ───
        ['name' => 'Coffret Infusions Relaxation Bio', 'category' => 'infusion', 'short' => '4 infusions pour se relaxer',
         'desc' => 'Coffret de 4 infusions bio : Camomille, Verveine, Tilleul-Miel, Lavande-Passiflore. 20 sachets en tout. Parfait pour une routine relaxation.',
         'price' => '42.00', 'volume' => '20 sachets', 'brand' => 'Herbalia Bio', 'origin' => 'France',
         'calories' => 1, 'hydration' => 95, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Relaxation profonde', 'Aide au sommeil', 'Anti-stress', 'Digestion'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['Camomille bio', 'Verveine bio', 'Tilleul bio', 'Miel', 'Lavande bio', 'Passiflore bio'],
         'stock' => 60, 'featured' => true, 'rating' => '4.75', 'reviews' => 178, 'sales' => 450],

        ['name' => 'Gingembre-Citron-Curcuma Immunité', 'category' => 'infusion', 'short' => 'Booster d\'immunité naturel',
         'desc' => 'Infusion puissante au gingembre frais, citron et curcuma. Renforcez votre immunité naturellement. Effet réchauffant et tonifiant.',
         'price' => '28.00', 'volume' => '15 sachets', 'brand' => 'Pukka', 'origin' => 'Inde/Asie',
         'calories' => 5, 'hydration' => 90, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Boost immunité', 'Anti-inflammatoire', 'Digestion', 'Antioxydant'],
         'regimes' => ['diabétique', 'normal', 'sans_gluten', 'cardioprotecteur'],
         'ingredients' => ['Gingembre bio', 'Citron bio', 'Curcuma bio', 'Poivre noir'],
         'stock' => 45, 'featured' => false, 'rating' => '4.65', 'reviews' => 93, 'sales' => 285],

        ['name' => 'Hibiscus (Bissap) Bio du Sénégal', 'category' => 'infusion', 'short' => 'Fleurs d\'hibiscus séchées',
         'desc' => 'Fleurs d\'hibiscus séchées bio du Sénégal. Infusion rouge rubis, acidulée et fruitée. Riche en vitamine C. Se boit chaud ou glacé.',
         'price' => '22.00', 'volume' => '200g', 'brand' => 'Bissap du Sénégal', 'origin' => 'Sénégal',
         'calories' => 3, 'hydration' => 92, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Riche en vitamine C', 'Contrôle la tension', 'Antioxydant puissant'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['100% fleurs d\'hibiscus sabdariffa bio'], 'stock' => 70, 'featured' => false, 'rating' => '4.55', 'reviews' => 74, 'sales' => 220],

        // ─── Eaux Santé ───
        ['name' => 'Eau Minérale Naturelle Sidi Ali Pack', 'category' => 'eau', 'short' => 'Pack de 6 bouteilles 1.5L',
         'desc' => 'Eau minérale naturelle marocaine Sidi Ali. Faiblement minéralisée, parfaite pour l\'hydratation quotidienne. Pack de 6 bouteilles de 1.5L.',
         'price' => '30.00', 'volume' => '6x1.5L', 'brand' => 'Sidi Ali', 'origin' => 'Maroc',
         'calories' => 0, 'hydration' => 100, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => false,
         'benefits' => ['Hydratation optimale', 'Minéraux essentiels', 'Zéro calorie'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['Eau minérale naturelle'], 'stock' => 100, 'featured' => false, 'rating' => '4.40', 'reviews' => 52, 'sales' => 600],

        ['name' => 'Eau de Coco Pure Bio', 'category' => 'eau', 'short' => 'Électrolytes naturels, réhydratation',
         'desc' => 'Eau de coco 100% pure, non pasteurisée. Électrolytes naturels (potassium, magnésium). Idéale après l\'effort ou comme boisson rafraîchissante.',
         'price' => '25.00', 'sale' => '19.90', 'volume' => '1L', 'brand' => 'CocoVita', 'origin' => 'Thaïlande',
         'calories' => 19, 'hydration' => 92, 'sugar_free' => false, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Riche en potassium', 'Électrolytes naturels', 'Réhydratation efficace'],
         'regimes' => ['normal', 'sans_gluten', 'cardioprotecteur'],
         'ingredients' => ['100% eau de coco'], 'stock' => 40, 'featured' => false, 'rating' => '4.50', 'reviews' => 88, 'sales' => 210],

        // ─── Jus Pressés & Smoothies ───
        ['name' => 'Cure Détox 3 Jours – Jus Pressés', 'category' => 'jus', 'short' => '18 jus pressés à froid pour 3 jours',
         'desc' => 'Cure détox complète de 3 jours : 18 jus pressés à froid (6/jour). Mélanges de légumes et fruits bio. Revitalisez votre corps.',
         'price' => '249.00', 'volume' => '18 bouteilles', 'brand' => 'Green Detox', 'origin' => 'Maroc',
         'calories' => 45, 'hydration' => 80, 'sugar_free' => false, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Détoxification', 'Vitamines & minéraux', 'Énergie naturelle', 'Peau éclatante'],
         'regimes' => ['normal', 'sans_gluten'],
         'ingredients' => ['Épinard', 'Céleri', 'Concombre', 'Pomme', 'Gingembre', 'Citron', 'Betterave', 'Carotte'],
         'stock' => 15, 'featured' => true, 'rating' => '4.90', 'reviews' => 64, 'sales' => 150],

        ['name' => 'Smoothie Protéiné Banane-Cacao', 'category' => 'smoothie', 'short' => 'Mix prêt à blender, riche en protéines',
         'desc' => 'Préparation pour smoothie protéiné : banane séchée, cacao cru, protéine de pois, graines de chia. 15g de protéines par portion.',
         'price' => '38.00', 'volume' => '500g (10 portions)', 'brand' => 'NutriBlend', 'origin' => 'France',
         'calories' => 65, 'hydration' => 70, 'sugar_free' => false, 'caffeine_free' => false, 'bio' => true,
         'benefits' => ['Riche en protéines', 'Fibres', 'Énergie durable', 'Oméga-3'],
         'regimes' => ['normal', 'sans_gluten'],
         'ingredients' => ['Banane séchée bio', 'Cacao cru bio', 'Protéine de pois', 'Graines de chia', 'Maca'],
         'stock' => 30, 'featured' => false, 'rating' => '4.55', 'reviews' => 41, 'sales' => 120],

        // ─── Compléments Liquides ───
        ['name' => 'Collagène Marin Buvable – Peau & Articulations', 'category' => 'complément', 'short' => 'Collagène marin hydrolysé + vitamine C',
         'desc' => 'Collagène marin hydrolysé de type I & III. Enrichi en vitamine C et acide hyaluronique. Goût pêche naturel. 30 doses.',
         'price' => '89.00', 'sale' => '69.00', 'volume' => '500ml (30 doses)', 'brand' => 'MarineCollagen+', 'origin' => 'France',
         'calories' => 15, 'hydration' => 60, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => false,
         'benefits' => ['Peau ferme et éclatante', 'Articulations souples', 'Récupération', 'Anti-âge'],
         'regimes' => ['normal', 'sans_gluten'],
         'ingredients' => ['Collagène marin hydrolysé', 'Vitamine C', 'Acide hyaluronique', 'Arôme pêche naturel'],
         'stock' => 20, 'featured' => true, 'rating' => '4.70', 'reviews' => 145, 'sales' => 350],

        ['name' => 'Aloe Vera Pur à Boire Bio', 'category' => 'complément', 'short' => 'Gel d\'aloe vera 99% pur',
         'desc' => 'Gel d\'aloe vera 99% pur bio. Favorise la digestion et l\'hydratation interne. Goût naturel d\'aloe. Sans sucre ajouté.',
         'price' => '32.00', 'volume' => '1L', 'brand' => 'AloeNatura', 'origin' => 'Espagne',
         'calories' => 8, 'hydration' => 85, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Digestion', 'Hydratation interne', 'Détoxification', 'Peau saine'],
         'regimes' => ['diabétique', 'normal', 'sans_gluten'],
         'ingredients' => ['99% gel d\'Aloe vera bio', 'Acide citrique', 'Vitamine E'],
         'stock' => 35, 'featured' => false, 'rating' => '4.45', 'reviews' => 56, 'sales' => 175],

        // ─── Superaliments ───
        ['name' => 'Poudre de Spiruline Bio', 'category' => 'superaliment', 'short' => 'Super-aliment riche en protéines',
         'desc' => 'Spiruline bio en poudre. 60% de protéines végétales, fer, B12, bêta-carotène. À mélanger dans smoothies, jus ou eau.',
         'price' => '35.00', 'volume' => '200g', 'brand' => 'SpiraVita', 'origin' => 'France',
         'calories' => 25, 'hydration' => 40, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['60% protéines', 'Riche en fer', 'Vitamine B12', 'Énergie', 'Détoxification'],
         'regimes' => ['diabétique', 'normal', 'sans_gluten', 'cardioprotecteur'],
         'ingredients' => ['100% Spiruline arthrospira platensis bio'], 'stock' => 50, 'featured' => false, 'rating' => '4.60', 'reviews' => 98, 'sales' => 260],

        ['name' => 'Poudre de Moringa Bio', 'category' => 'superaliment', 'short' => 'L\'arbre de vie en poudre',
         'desc' => 'Feuilles de Moringa oleifera séchées et réduites en poudre. 7x plus de vitamine C que les oranges, 4x plus de calcium que le lait.',
         'price' => '28.00', 'volume' => '150g', 'brand' => 'MoringaPlus', 'origin' => 'Maroc',
         'calories' => 20, 'hydration' => 45, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => true,
         'benefits' => ['Très riche en nutriments', 'Vitamine C', 'Calcium', 'Anti-inflammatoire'],
         'regimes' => ['diabétique', 'normal', 'sans_gluten', 'cardioprotecteur'],
         'ingredients' => ['100% poudre de feuilles de Moringa oleifera bio'], 'stock' => 45, 'featured' => false, 'rating' => '4.50', 'reviews' => 72, 'sales' => 195],

        // ─── Sirops & Mocktails ───
        ['name' => 'Coffret Sirops Sans Sucre (4 parfums)', 'category' => 'sirop_sans_sucre', 'short' => 'Menthe, Citron, Grenadine, Pêche',
         'desc' => 'Coffret de 4 sirops sans sucre ajouté : Menthe fraîche, Citron, Grenadine et Pêche. Édulcorés au stévia. Pour agrémenter eau et boissons.',
         'price' => '45.00', 'sale' => '35.00', 'volume' => '4x250ml', 'brand' => 'SiropSanté', 'origin' => 'France',
         'calories' => 3, 'hydration' => 88, 'sugar_free' => true, 'caffeine_free' => true, 'bio' => false,
         'benefits' => ['Sans sucre ajouté', 'Favorise l\'hydratation', 'Goût agréable', 'Faible en calories'],
         'regimes' => ['diabétique', 'cardioprotecteur', 'normal', 'hypo_sodé', 'sans_gluten'],
         'ingredients' => ['Eau', 'Arômes naturels', 'Stévia', 'Acide citrique'],
         'stock' => 40, 'featured' => true, 'rating' => '4.65', 'reviews' => 134, 'sales' => 390],

        ['name' => 'Kit Mocktails Premium (10 recettes)', 'category' => 'mocktail', 'short' => 'Tout pour créer 10 mocktails festifs',
         'desc' => 'Kit complet pour préparer 10 mocktails originaux : sirops artisanaux, bitters sans alcool, décorations, recettes illustrées et shaker professionnel.',
         'price' => '79.00', 'volume' => 'Kit complet', 'brand' => 'Mix0', 'origin' => 'France',
         'calories' => 25, 'hydration' => 82, 'sugar_free' => false, 'caffeine_free' => true, 'bio' => false,
         'benefits' => ['Festif sans alcool', 'Créatif', 'Convivial', 'Vitamines naturelles'],
         'regimes' => ['normal', 'sans_gluten'],
         'ingredients' => ['Sirops artisanaux', 'Bitters sans alcool', 'Shaker', 'Décorations', 'Livret recettes'],
         'stock' => 18, 'featured' => false, 'rating' => '4.80', 'reviews' => 48, 'sales' => 95],
    ];

    private BeverageProductRepository $productRepo;
    private BeverageOrderRepository $orderRepo;
    private EntityManagerInterface $em;

    public function __construct(
        BeverageProductRepository $productRepo,
        BeverageOrderRepository $orderRepo,
        EntityManagerInterface $em
    ) {
        $this->productRepo = $productRepo;
        $this->orderRepo = $orderRepo;
        $this->em = $em;
    }

    /**
     * Seed le catalogue de produits si vide
     */
    public function seedProductsIfEmpty(): void
    {
        $count = $this->productRepo->count([]);
        if ($count > 0) {
            return;
        }

        foreach (self::PRODUCT_CATALOG as $data) {
            $product = new BeverageProduct();
            $product->setName($data['name']);
            $product->setCategory($data['category']);
            $product->setShortDescription($data['short'] ?? null);
            $product->setDescription($data['desc'] ?? null);
            $product->setPrice($data['price']);
            if (isset($data['sale'])) {
                $product->setSalePrice($data['sale']);
            }
            $product->setVolume($data['volume'] ?? null);
            $product->setBrand($data['brand'] ?? null);
            $product->setOrigin($data['origin'] ?? null);
            $product->setCaloriesPer100ml($data['calories'] ?? null);
            $product->setHydrationScore($data['hydration'] ?? null);
            $product->setIsSugarFree($data['sugar_free'] ?? false);
            $product->setIsCaffeineFree($data['caffeine_free'] ?? false);
            $product->setIsBio($data['bio'] ?? false);
            $product->setHealthBenefits($data['benefits'] ?? []);
            $product->setCompatibleRegimes($data['regimes'] ?? []);
            $product->setIngredients($data['ingredients'] ?? []);
            $product->setStockQuantity($data['stock'] ?? 0);
            $product->setIsFeatured($data['featured'] ?? false);
            $product->setAverageRating($data['rating'] ?? null);
            $product->setReviewCount($data['reviews'] ?? 0);
            $product->setSalesCount($data['sales'] ?? 0);

            $this->em->persist($product);
        }

        $this->em->flush();
    }

    /**
     * Obtenir ou créer le panier actif
     */
    public function getOrCreateCart(User $user): BeverageOrder
    {
        $cart = $this->orderRepo->findActiveCart($user);
        if (!$cart) {
            $cart = new BeverageOrder();
            $cart->setUser($user);
            $cart->setStatus(BeverageOrder::STATUS_CART);
            $this->em->persist($cart);
            $this->em->flush();
        }
        return $cart;
    }

    /**
     * Ajouter un produit au panier
     */
    public function addToCart(User $user, BeverageProduct $product, int $quantity = 1): array
    {
        if (!$product->isInStock()) {
            return ['status' => 'error', 'message' => 'Ce produit est en rupture de stock.'];
        }

        if ($quantity > $product->getStockQuantity()) {
            return ['status' => 'error', 'message' => 'Stock insuffisant. Il reste ' . $product->getStockQuantity() . ' unité(s).'];
        }

        $cart = $this->getOrCreateCart($user);

        // Check if the product already exists in cart
        $existingItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                $existingItem = $item;
                break;
            }
        }

        if ($existingItem) {
            $newQty = $existingItem->getQuantity() + $quantity;
            if ($newQty > $product->getStockQuantity()) {
                return ['status' => 'error', 'message' => 'Stock insuffisant pour cette quantité.'];
            }
            $existingItem->setQuantity($newQty);
        } else {
            $item = new BeverageOrderItem();
            $item->setProduct($product);
            $item->setUnitPrice($product->getEffectivePrice());
            $item->setQuantity($quantity);
            $cart->addItem($item);
        }

        $cart->recalculateTotal();
        $this->em->flush();

        return [
            'status' => 'success',
            'message' => $product->getName() . ' ajouté au panier !',
            'cart_count' => $cart->getItemCount(),
            'cart_total' => $cart->getTotalAmount(),
        ];
    }

    /**
     * Modifier la quantité d'un item du panier
     */
    public function updateCartItem(User $user, int $itemId, int $quantity): array
    {
        $cart = $this->getOrCreateCart($user);

        foreach ($cart->getItems() as $item) {
            if ($item->getId() === $itemId) {
                if ($quantity <= 0) {
                    $cart->removeItem($item);
                    $this->em->remove($item);
                } else {
                    if ($quantity > $item->getProduct()->getStockQuantity()) {
                        return ['status' => 'error', 'message' => 'Stock insuffisant.'];
                    }
                    $item->setQuantity($quantity);
                }
                $cart->recalculateTotal();
                $this->em->flush();

                return [
                    'status' => 'success',
                    'cart_count' => $cart->getItemCount(),
                    'cart_total' => $cart->getTotalAmount(),
                ];
            }
        }

        return ['status' => 'error', 'message' => 'Article non trouvé dans le panier.'];
    }

    /**
     * Supprimer un item du panier
     */
    public function removeFromCart(User $user, int $itemId): array
    {
        return $this->updateCartItem($user, $itemId, 0);
    }

    /**
     * Valider la commande (panier → pending)
     */
    public function checkout(User $user, array $shippingInfo): array
    {
        $cart = $this->orderRepo->findActiveCart($user);
        if (!$cart) {
            return ['status' => 'error', 'message' => 'Panier non trouvé. Veuillez ajouter des produits.'];
        }
        
        if ($cart->getItems()->isEmpty()) {
            return ['status' => 'error', 'message' => 'Votre panier est vide.'];
        }

        // Verify stock for each item
        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();
            if ($item->getQuantity() > $product->getStockQuantity()) {
                return [
                    'status' => 'error',
                    'message' => "Stock insuffisant pour {$product->getName()}. ({$product->getStockQuantity()} restant(s))",
                ];
            }
        }

        // Compute shipping
        $total = (float)$cart->getTotalAmount();
        $shippingCost = $total >= 200 ? 0.00 : 25.00; // Free shipping over 200 DH
        $cart->setShippingCost(number_format($shippingCost, 2, '.', ''));

        // Fill shipping info
        $cart->setShippingAddress($shippingInfo['address'] ?? null);
        $cart->setShippingCity($shippingInfo['city'] ?? null);
        $cart->setShippingPostalCode($shippingInfo['postal_code'] ?? null);
        $cart->setPhone($shippingInfo['phone'] ?? null);
        $cart->setPaymentMethod($shippingInfo['payment_method'] ?? 'cash_on_delivery');
        $cart->setNotes($shippingInfo['notes'] ?? null);

        // Generate order number and change status
        $cart->generateOrderNumber();
        $cart->setStatus(BeverageOrder::STATUS_CONFIRMED);
        $cart->setConfirmedAt(new \DateTime());

        // Decrement stock
        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();
            $newStock = $product->getStockQuantity() - $item->getQuantity();
            $product->setStockQuantity(max(0, $newStock));
            $product->setSalesCount($product->getSalesCount() + $item->getQuantity());
            $this->em->persist($product);
        }

        // Persist cart changes
        $this->em->persist($cart);
        $this->em->flush();

        return [
            'status' => 'success',
            'message' => 'Commande confirmée !',
            'order_number' => $cart->getOrderNumber(),
            'grand_total' => $cart->getGrandTotal(),
            'shipping_cost' => $shippingCost,
        ];
    }

    /**
     * Produits groupés par catégorie pour la marketplace
     */
    public function getProductsByCategory(): array
    {
        $products = $this->productRepo->findAllActive();
        $grouped = [];
        foreach ($products as $product) {
            $cat = $product->getCategory();
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $product;
        }
        return $grouped;
    }

    /**
     * Récupérer les produits compatibles avec un régime
     */
    public function getRecommendedForRegime(?string $regimeType): array
    {
        if (!$regimeType) {
            return $this->productRepo->findBestSellers(8);
        }
        $products = $this->productRepo->findCompatibleWithRegime($regimeType);
        return array_slice($products, 0, 8);
    }
}
