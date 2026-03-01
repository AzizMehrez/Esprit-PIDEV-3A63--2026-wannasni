<?php

namespace App\Controller\Front\Nutrition;

use App\Entity\BeverageLog;
use App\Entity\BeverageOrder;
use App\Repository\BeverageLogRepository;
use App\Repository\BeverageOrderRepository;
use App\Repository\BeverageProductRepository;
use App\Repository\BeverageRepository;
use App\Repository\RegimePrescritRepository;
use App\Service\BeverageMarketplaceService;
use App\Service\BeveragePhotoUploadService;
use App\Service\GeminiService;
use App\Service\PhotoUploadService;
use App\Service\SommelierService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/nutrition/sommelier', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_USER')]
class SommelierController extends AbstractController
{
    private function findLatestUserRegime(RegimePrescritRepository $repo): ?\App\Entity\RegimePrescrit
    {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return null;
        }

        $regimes = $repo->createQueryBuilder('r')
            ->where('r.user = :user OR r.seniorId = :seniorId')
            ->setParameter('user', $user)
            ->setParameter('seniorId', $user->getId())
            ->orderBy('r.datePrescription', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
        return $regimes[0] ?? null;
    }

    // ═══════════════════════════════════════
    //  PAGE PRINCIPALE SOMMELIER
    // ═══════════════════════════════════════
    #[Route('/', name: 'app_nutrition_sommelier')]
    public function index(
        SommelierService $sommelierService,
        BeverageMarketplaceService $marketplaceService,
        RegimePrescritRepository $regimePrescritRepository,
        BeverageLogRepository $beverageLogRepository,
        BeverageOrderRepository $orderRepository,
        BeverageProductRepository $productRepo
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $regime = $this->findLatestUserRegime($regimePrescritRepository);

        // Seed catalogs if needed
        $sommelierService->seedCatalogIfEmpty();
        $marketplaceService->seedProductsIfEmpty();

        // Hydration data
        $hydrationData = $sommelierService->getPersonalizedHydrationAdvice($user, $regime);

        // Today's logs
        $todayLogs = $beverageLogRepository->findTodayLogs($user);

        // Beverage catalog by category
        $catalog = $sommelierService->getCatalogByCategory();

        // Marketplace products grouped by category
        $marketplaceProducts = $productRepo->findBy(['isActive' => true], ['category' => 'ASC', 'name' => 'ASC']);
        $productsByCategory = [];
        foreach ($marketplaceProducts as $p) {
            $productsByCategory[$p->getCategory()][] = $p;
        }

        // Partners
        $partners = $sommelierService->getPartners();

        // Cart count
        $cartCount = $orderRepository->getCartItemCount($user);

        return $this->render('front/nutrition/sommelier/index.html.twig', [
            'regime' => $regime,
            'hydration' => $hydrationData,
            'today_logs' => $todayLogs,
            'catalog' => $catalog,
            'products_by_category' => $productsByCategory,
            'partners' => $partners,
            'cart_count' => $cartCount,
        ]);
    }

    // ═══════════════════════════════════════
    //  SUGGESTIONS DE BOISSONS PAR REPAS (AJAX)
    // ═══════════════════════════════════════
    #[Route('/suggest', name: 'app_nutrition_sommelier_suggest', methods: ['POST'])]
    public function suggestBeverages(
        Request $request,
        SommelierService $sommelierService,
        RegimePrescritRepository $regimePrescritRepository
    ): JsonResponse {
        $mealType = $request->request->get('meal_type', 'déjeuner');
        $regime = $this->findLatestUserRegime($regimePrescritRepository);

        $suggestions = $sommelierService->suggestForMeal($mealType, $regime);

        // Convert entity-based suggestions to array
        $results = [];
        foreach ($suggestions as $item) {
            if (isset($item['beverage']) && $item['beverage'] instanceof \App\Entity\Beverage) {
                $bev = $item['beverage'];
                $results[] = [
                    'id' => $bev->getId(),
                    'name' => $bev->getName(),
                    'category' => $bev->getCategory(),
                    'emoji' => $bev->getCategoryEmoji(),
                    'description' => $bev->getDescription(),
                    'calories' => $bev->getCaloriesPer100ml(),
                    'hydration_score' => $bev->getHydrationScore(),
                    'benefits' => $bev->getHealthBenefits(),
                    'preparation' => $bev->getPreparationInstructions(),
                    'origin' => $bev->getOrigin(),
                    'temperature' => $bev->getTemperatureRange(),
                    'sugar_free' => $bev->isSugarFree(),
                    'caffeine_free' => $bev->isCaffeineFree(),
                    'pairing' => $bev->getPairingMeals(),
                    'score' => $item['score'],
                ];
            } else {
                // Already array format (from catalog fallback)
                $catEmoji = match ($item['category'] ?? '') {
                    'thé' => '🍵', 'café' => '☕', 'infusion' => '🌿', 'eau' => '💧',
                    'jus' => '🧃', 'smoothie' => '🥤', 'sirop_sans_sucre' => '🍯', 'mocktail' => '🍹',
                    default => '🥂',
                };
                $results[] = array_merge($item, ['emoji' => $catEmoji]);
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'meal_type' => $mealType,
            'suggestions' => $results,
        ]);
    }

    // ═══════════════════════════════════════
    //  ENREGISTRER UNE CONSOMMATION (AJAX)
    // ═══════════════════════════════════════
    #[Route('/log', name: 'app_nutrition_sommelier_log', methods: ['POST'])]
    public function logBeverage(
        Request $request,
        BeverageRepository $beverageRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $beverageId = $request->request->get('beverage_id');
        $customName = $request->request->get('custom_name');
        $category = $request->request->get('category', 'autre');
        $quantityMl = (int) $request->request->get('quantity_ml', 250);
        $moment = $request->request->get('moment');
        $rating = $request->request->get('rating');

        $log = new BeverageLog();
        $log->setUser($user);
        $log->setQuantityMl($quantityMl);
        $log->setMoment($moment);
        $log->setCategory($category);

        if ($beverageId) {
            $beverage = $beverageRepository->find($beverageId);
            if ($beverage) {
                $log->setBeverage($beverage);
                $log->setCustomBeverageName($beverage->getName());
                $log->setCategory($beverage->getCategory());
                $log->setWasRecommended(true);
            }
        } elseif ($customName) {
            $log->setCustomBeverageName($customName);
        }

        if ($rating) {
            $log->setSatisfactionRating(min(5, max(1, (int) $rating)));
        }

        $em->persist($log);
        $em->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Boisson enregistrée !',
            'log_id' => $log->getId(),
            'quantity_ml' => $quantityMl,
        ]);
    }

    // ═══════════════════════════════════════
    //  DONNÉES D'HYDRATATION (AJAX)
    // ═══════════════════════════════════════
    #[Route('/hydration', name: 'app_nutrition_sommelier_hydration', methods: ['POST'])]
    public function hydrationData(
        SommelierService $sommelierService,
        RegimePrescritRepository $regimePrescritRepository
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $regime = $this->findLatestUserRegime($regimePrescritRepository);
        $data = $sommelierService->getPersonalizedHydrationAdvice($user, $regime);

        return new JsonResponse(array_merge(['status' => 'success'], $data));
    }

    // ═══════════════════════════════════════
    //  STATISTIQUES HYDRATATION (AJAX)
    // ═══════════════════════════════════════
    #[Route('/stats', name: 'app_nutrition_sommelier_stats', methods: ['POST'])]
    public function hydrationStats(
        BeverageLogRepository $beverageLogRepository
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $stats = $beverageLogRepository->getHydrationStats($user, 7);
        $favorites = $beverageLogRepository->getFavoriteBeverages($user, 5);

        return new JsonResponse([
            'status' => 'success',
            'daily_stats' => $stats,
            'favorites' => $favorites,
        ]);
    }

    // ═══════════════════════════════════════
    //  DÉGUSTATION VIRTUELLE (AJAX)
    // ═══════════════════════════════════════
    #[Route('/tasting', name: 'app_nutrition_sommelier_tasting', methods: ['POST'])]
    public function virtualTasting(
        Request $request,
        SommelierService $sommelierService,
        RegimePrescritRepository $regimePrescritRepository
    ): JsonResponse {
        $type = $request->request->get('type', 'thé');
        $regime = $this->findLatestUserRegime($regimePrescritRepository);

        $result = $sommelierService->getVirtualTasting($type, $regime);

        return new JsonResponse($result);
    }

    // ═══════════════════════════════════════
    //  HISTORIQUE BOISSONS
    // ═══════════════════════════════════════
    #[Route('/history', name: 'app_nutrition_sommelier_history')]
    public function history(
        BeverageLogRepository $beverageLogRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $logs = $beverageLogRepository->findHistoryDays($user, 30);

        return $this->render('front/nutrition/sommelier/history.html.twig', [
            'logs' => $logs,
        ]);
    }

    // ═══════════════════════════════════════════════
    //  ANALYSE PHOTO DE BOISSON (AJAX)
    // ═══════════════════════════════════════════════
    #[Route('/photo-analyze', name: 'app_nutrition_sommelier_photo_analyze', methods: ['POST'])]
    public function photoAnalyze(
        Request $request,
        GeminiService $geminiService,
        BeveragePhotoUploadService $beveragePhotoUploadService
    ): JsonResponse {
        try {
            $photoFile = $request->files->get('photo');
            if (!$photoFile) {
                return new JsonResponse(['status' => 'error', 'message' => 'Aucune photo reçue.']);
            }

            $fileName = $beveragePhotoUploadService->upload($photoFile);
            $imagePath = $beveragePhotoUploadService->getTargetDirectory() . '/' . $fileName;

            // Check if file was uploaded successfully
            if (!file_exists($imagePath)) {
                return new JsonResponse(['status' => 'error', 'message' => 'Erreur lors du téléchargement de l\'image.']);
            }

            $analysis = $geminiService->analyzeBeverageImage($imagePath);

            if (isset($analysis['error'])) {
                // Log the error for debugging
                error_log('Gemini API Error: ' . $analysis['error']);
                
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Impossible d\'analyser cette photo. Vérifiez que l\'image est claire et lisible. Erreur: ' . $analysis['error'],
                ]);
            }

            return new JsonResponse([
                'status' => 'success',
                'photo' => $fileName,
                'analysis' => $analysis,
            ]);
        } catch (\Throwable $e) {
            error_log('Photo analyze exception: ' . $e->getMessage());
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de l\'analyse : ' . $e->getMessage(),
            ], 500);
        }
    }

    // ═══════════════════════════════════════════════
    //  ENREGISTRER BOISSON DEPUIS PHOTO (AJAX)
    // ═══════════════════════════════════════════════
    #[Route('/photo-log', name: 'app_nutrition_sommelier_photo_log', methods: ['POST'])]
    public function photoLog(
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $name = $request->request->get('name', 'Boisson inconnue');
        $category = $request->request->get('category', 'autre');
        $quantityMl = (int) $request->request->get('quantity_ml', 250);
        $calories = (int) $request->request->get('calories', 0);
        $moment = $request->request->get('moment');

        $log = new BeverageLog();
        $log->setUser($user);
        $log->setCustomBeverageName($name);
        $log->setCategory($category);
        $log->setQuantityMl($quantityMl);
        $log->setMoment($moment);
        $log->setWasRecommended(false);
        $log->setMealContext([
            'source' => 'photo_analysis',
            'calories_estimated' => $calories,
        ]);

        $em->persist($log);
        $em->flush();

        return new JsonResponse([
            'status' => 'success',
            'message' => $name . ' (' . $quantityMl . 'ml) enregistré depuis photo !',
            'log_id' => $log->getId(),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  MARKETPLACE - PAGE BOUTIQUE
    // ═══════════════════════════════════════════════════════
    #[Route('/marketplace', name: 'app_nutrition_sommelier_marketplace')]
    public function marketplace(
        BeverageMarketplaceService $marketplaceService,
        BeverageProductRepository $productRepo,
        BeverageOrderRepository $orderRepo,
        RegimePrescritRepository $regimePrescritRepository
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $regime = $this->findLatestUserRegime($regimePrescritRepository);
        $regimeType = $regime ? $regime->getTypeRegime() : null;

        $marketplaceService->seedProductsIfEmpty();

        $productsByCategory = $marketplaceService->getProductsByCategory();
        $featured = $productRepo->findFeatured();
        $onSale = $productRepo->findOnSale();
        $recommended = $marketplaceService->getRecommendedForRegime($regimeType);
        $cartCount = $orderRepo->getCartItemCount($user);

        return $this->render('front/nutrition/sommelier/marketplace.html.twig', [
            'products_by_category' => $productsByCategory,
            'featured' => $featured,
            'on_sale' => $onSale,
            'recommended' => $recommended,
            'regime' => $regime,
            'cart_count' => $cartCount,
        ]);
    }

    // ═══════════════════════════════════════
    //  MARKETPLACE - AJOUTER AU PANIER (AJAX)
    // ═══════════════════════════════════════
    #[Route('/marketplace/add-to-cart', name: 'app_nutrition_sommelier_add_to_cart', methods: ['POST'])]
    public function addToCart(
        Request $request,
        BeverageMarketplaceService $marketplaceService,
        BeverageProductRepository $productRepo
    ): JsonResponse {
        try {
            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['status' => 'error', 'message' => 'Vous devez être connecté.'], 401);
            }

            $productId = (int) $request->request->get('product_id');
            $quantity = (int) $request->request->get('quantity', 1);

            if ($quantity < 1) {
                return new JsonResponse(['status' => 'error', 'message' => 'Quantité invalide.']);
            }

            $product = $productRepo->find($productId);
            if (!$product) {
                return new JsonResponse(['status' => 'error', 'message' => 'Produit introuvable.']);
            }

            $result = $marketplaceService->addToCart($user, $product, $quantity);
            
            // Ajouter le compte du panier
            $cart = $marketplaceService->getOrCreateCart($user);
            $result['cart_count'] = $cart->getItemCount();
            
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            error_log('Add to cart error: ' . $e->getMessage());
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors de l\'ajout au panier: ' . $e->getMessage()
            ], 500);
        }
    }

    // ═══════════════════════════════════════
    //  MARKETPLACE - METTRE À JOUR PANIER (AJAX)
    // ═══════════════════════════════════════
    #[Route('/marketplace/update-cart', name: 'app_nutrition_sommelier_update_cart', methods: ['POST'])]
    public function updateCart(
        Request $request,
        BeverageMarketplaceService $marketplaceService
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $itemId = (int) $request->request->get('item_id');
        $quantity = (int) $request->request->get('quantity', 1);

        $result = $marketplaceService->updateCartItem($user, $itemId, $quantity);
        return new JsonResponse($result);
    }

    // ═══════════════════════════════════════
    //  MARKETPLACE - SUPPRIMER DU PANIER (AJAX)
    // ═══════════════════════════════════════
    #[Route('/marketplace/remove-from-cart', name: 'app_nutrition_sommelier_remove_from_cart', methods: ['POST'])]
    public function removeFromCart(
        Request $request,
        BeverageMarketplaceService $marketplaceService
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $itemId = (int) $request->request->get('item_id');

        $result = $marketplaceService->removeFromCart($user, $itemId);
        return new JsonResponse($result);
    }

    // ═══════════════════════════════════════
    //  MARKETPLACE - DONNÉES DU PANIER (AJAX)
    // ═══════════════════════════════════════
    #[Route('/marketplace/cart-data', name: 'app_nutrition_sommelier_cart_data', methods: ['POST'])]
    public function cartData(
        BeverageMarketplaceService $marketplaceService
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $cart = $marketplaceService->getOrCreateCart($user);

        $items = [];
        foreach ($cart->getItems() as $item) {
            $p = $item->getProduct();
            $items[] = [
                'item_id' => $item->getId(),
                'product_id' => $p->getId(),
                'name' => $p->getName(),
                'emoji' => $p->getCategoryEmoji(),
                'brand' => $p->getBrand(),
                'volume' => $p->getVolume(),
                'unit_price' => $item->getUnitPrice(),
                'quantity' => $item->getQuantity(),
                'line_total' => $item->getLineTotal(),
                'stock' => $p->getStockQuantity(),
            ];
        }

        $total = (float)$cart->getTotalAmount();
        $shippingCost = $total >= 200 ? 0.00 : 25.00;

        return new JsonResponse([
            'status' => 'success',
            'items' => $items,
            'total' => $cart->getTotalAmount(),
            'shipping_cost' => number_format($shippingCost, 2, '.', ''),
            'grand_total' => number_format($total + $shippingCost, 2, '.', ''),
            'cart_count' => $cart->getItemCount(),
            'free_shipping_at' => 200,
        ]);
    }

    // ═══════════════════════════════════════
    //  MARKETPLACE - CHECKOUT (AJAX)
    // ═══════════════════════════════════════
    #[Route('/marketplace/checkout', name: 'app_nutrition_sommelier_checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        BeverageMarketplaceService $marketplaceService
    ): JsonResponse {
        try {
            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();
            if (!$user) {
                return new JsonResponse(['status' => 'error', 'message' => 'Vous devez être connecté pour commander.'], 401);
            }

            $shippingInfo = [
                'address' => $request->request->get('address'),
                'city' => $request->request->get('city'),
                'postal_code' => $request->request->get('postal_code'),
                'phone' => $request->request->get('phone'),
                'payment_method' => $request->request->get('payment_method', 'cash_on_delivery'),
                'notes' => $request->request->get('notes'),
            ];

            if (empty($shippingInfo['address']) || empty($shippingInfo['city']) || empty($shippingInfo['phone'])) {
                return new JsonResponse(['status' => 'error', 'message' => 'Veuillez remplir adresse, ville et téléphone.']);
            }

            $result = $marketplaceService->checkout($user, $shippingInfo);
            return new JsonResponse($result);
        } catch (\Throwable $e) {
            error_log('Checkout error: ' . $e->getMessage());
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Erreur lors du checkout: ' . $e->getMessage()
            ], 500);
        }
    }

    // ═══════════════════════════════════════
    //  MARKETPLACE - MES COMMANDES
    // ═══════════════════════════════════════
    #[Route('/marketplace/orders', name: 'app_nutrition_sommelier_orders')]
    public function orders(
        BeverageOrderRepository $orderRepo
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $orders = $orderRepo->findUserOrders($user);

        return $this->render('front/nutrition/sommelier/orders.html.twig', [
            'orders' => $orders,
        ]);
    }
}
