<?php

namespace App\Controller\Front;

use App\Entity\DemandeRegime;
use App\Entity\RegimePrescrit;
use App\Entity\SuiviRepas;
use App\Form\DemandeRegimeType;
use App\Repository\DemandeRegimeRepository;
use App\Repository\RegimePrescritRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\MealDbService;
use App\Service\NutritionAnalysisService;
use App\Service\PythonMLService;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/nutrition', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_USER')]
class NutritionController extends AbstractController
{
    // ──────────────────────────────────────────────
    // Helper: Find all regimes for the current user
    // ──────────────────────────────────────────────
    private function findUserRegimes(RegimePrescritRepository $repo): array
    {
        $user = $this->getUser();
        return $repo->createQueryBuilder('r')
            ->where('r.user = :user OR r.seniorId = :seniorId')
            ->setParameter('user', $user)
            ->setParameter('seniorId', $user->getId())
            ->orderBy('r.datePrescription', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    private function findLatestUserRegime(RegimePrescritRepository $repo): ?RegimePrescrit
    {
        $regimes = $this->findUserRegimes($repo);
        return $regimes[0] ?? null;
    }

    private function findUserDemands(DemandeRegimeRepository $repo): array
    {
        $user = $this->getUser();
        return $repo->createQueryBuilder('d')
            ->where('d.user = :user OR d.seniorId = :seniorId')
            ->setParameter('user', $user)
            ->setParameter('seniorId', $user->getId())
            ->orderBy('d.dateDemande', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    private function isOwnerOfDemand(DemandeRegime $demande): bool
    {
        $user = $this->getUser();
        return $demande->getUser() === $user || $demande->getSeniorId() === $user->getId();
    }

    private function isOwnerOfRegime(RegimePrescrit $regime): bool
    {
        $user = $this->getUser();
        return $regime->getUser() === $user || $regime->getSeniorId() === $user->getId();
    }

    private function getTodayMealStats(EntityManagerInterface $em): array
    {
        $user = $this->getUser();
        $today = new \DateTime('today');
        try {
            $result = $em->getRepository(SuiviRepas::class)->createQueryBuilder('s')
                ->select('COUNT(s.id) AS mealCount, COALESCE(SUM(s.caloriesCalculees), 0) AS totalCalories')
                ->where('s.senior = :senior')
                ->andWhere('s.dateRepas >= :today')
                ->setParameter('senior', $user)
                ->setParameter('today', $today)
                ->getQuery()
                ->getSingleResult();

            $mealsToday = (int) $result['mealCount'];
            $caloriesConsumedToday = (int) $result['totalCalories'];
        } catch (\Exception $e) {
            $mealsToday = 0;
            $caloriesConsumedToday = 0;
        }

        return ['meals' => $mealsToday, 'calories' => $caloriesConsumedToday];
    }

    // ══════════════════════════════════════════════
    //  ROUTES
    // ══════════════════════════════════════════════

    #[Route('/', name: 'app_my_nutrition')]
    public function index(
        DemandeRegimeRepository $demandeRegimeRepository,
        RegimePrescritRepository $regimePrescritRepository,
        EntityManagerInterface $em
    ): Response {
        $demandeRegimes = $this->findUserDemands($demandeRegimeRepository);
        $regimesPrescrits = $this->findUserRegimes($regimePrescritRepository);

        // Fallback: collect regimes from treated demands
        if (empty($regimesPrescrits)) {
            $existingIds = [];
            foreach ($demandeRegimes as $demande) {
                foreach ($demande->getRegimesPrescrits() as $regime) {
                    if (!in_array($regime->getId(), $existingIds)) {
                        $regimesPrescrits[] = $regime;
                        $existingIds[] = $regime->getId();
                    }
                }
            }
        }

        $todayStats = $this->getTodayMealStats($em);
        $activeRegime = $regimesPrescrits[0] ?? null;

        return $this->render('front/nutrition/index.html.twig', [
            'demande_regimes' => $demandeRegimes,
            'regimes_prescrits' => $regimesPrescrits,
            'active_regime' => $activeRegime,
            'meals_today' => $todayStats['meals'],
            'calories_consumed_today' => $todayStats['calories'],
        ]);
    }

    #[Route('/meal-reminders', name: 'app_nutrition_meal_reminders', methods: ['POST'])]
    public function mealReminders(
        Request $request,
        RegimePrescritRepository $regimePrescritRepository,
        EntityManagerInterface $em,
        PythonMLService $pythonMLService,
        HttpClientInterface $httpClient
    ): JsonResponse {
        $user = $this->getUser();
        $regimeId = $request->request->get('regime_id');
        $regime = $regimePrescritRepository->find($regimeId);

        if (!$regime || !$this->isOwnerOfRegime($regime)) {
            return new JsonResponse(['error' => 'Régime introuvable'], 404);
        }

        $todayStats = $this->getTodayMealStats($em);
        $repasParJour = (int) $regime->getRepasParJour();
        $caloriesLimite = $regime->getCaloriesJournalieres();
        $repasConsommes = $todayStats['meals'];
        $caloriesConsommees = $todayStats['calories'];

        // Calcul des variables dérivées (nécessaires avant le fallback)
        $repasRestants    = max(0, $repasParJour - $repasConsommes);
        $caloriesRestantes = max(0, $caloriesLimite - $caloriesConsommees);

        // ── Appel ML optionnel : uniquement si use_ml=1 (bouton IA côté JS) ──
        if ($request->request->get('use_ml') === '1') {
            try {
                $mlResult = $pythonMLService->getMealReminders(
                    $regime->getTypeRegime(),
                    $repasParJour,
                    $repasConsommes,
                    $caloriesConsommees,
                    $caloriesLimite,
                    $regime->getAlimentsRecommandes() ?? [],
                    $regime->getAlimentsInterdits() ?? []
                );
                if (($mlResult['status'] ?? '') === 'success') {
                    return new JsonResponse($mlResult);
                }
            } catch (\Exception $e) {
                // ML indisponible — continuer avec le fallback PHP
            }
        }

        // ── Fallback: Generate reminders server-side ──
        $notifications = [];
        $suggestionsRepas = [];
        $hour = (int) date('G');

        // Meal schedule based on repas_par_jour
        $mealSchedules = [
            3 => [
                ['type' => 'Petit-déjeuner', 'debut' => 7, 'fin' => 9],
                ['type' => 'Déjeuner', 'debut' => 12, 'fin' => 14],
                ['type' => 'Dîner', 'debut' => 19, 'fin' => 21],
            ],
            4 => [
                ['type' => 'Petit-déjeuner', 'debut' => 7, 'fin' => 9],
                ['type' => 'Déjeuner', 'debut' => 12, 'fin' => 13],
                ['type' => 'Goûter', 'debut' => 16, 'fin' => 17],
                ['type' => 'Dîner', 'debut' => 19, 'fin' => 21],
            ],
            5 => [
                ['type' => 'Petit-déjeuner', 'debut' => 7, 'fin' => 8],
                ['type' => 'Collation matin', 'debut' => 10, 'fin' => 11],
                ['type' => 'Déjeuner', 'debut' => 12, 'fin' => 13],
                ['type' => 'Goûter', 'debut' => 16, 'fin' => 17],
                ['type' => 'Dîner', 'debut' => 19, 'fin' => 21],
            ],
            6 => [
                ['type' => 'Petit-déjeuner', 'debut' => 7, 'fin' => 8],
                ['type' => 'Collation matin', 'debut' => 10, 'fin' => 11],
                ['type' => 'Déjeuner', 'debut' => 12, 'fin' => 13],
                ['type' => 'Goûter', 'debut' => 15, 'fin' => 16],
                ['type' => 'Collation soir', 'debut' => 17, 'fin' => 18],
                ['type' => 'Dîner', 'debut' => 19, 'fin' => 21],
            ],
        ];

        $schedule = $mealSchedules[$repasParJour] ?? $mealSchedules[3];
        $caloriesParRepas = $repasRestants > 0 ? round($caloriesRestantes / $repasRestants) : 0;

        // Build timeline suggestions for remaining meals
        foreach ($schedule as $i => $meal) {
            if ($i < $repasConsommes) continue; // Already consumed

            $statut = 'a_venir';
            if ($hour >= $meal['debut'] && $hour < $meal['fin']) {
                $statut = 'maintenant';
            } elseif ($hour >= $meal['fin']) {
                $statut = 'en_retard';
            }

            $suggestionsRepas[] = [
                'type_repas' => $meal['type'],
                'heure_debut' => $meal['debut'],
                'heure_fin' => $meal['fin'],
                'statut' => $statut,
                'calories_suggerees' => $caloriesParRepas,
            ];
        }

        // Generate smart notifications
        if ($repasConsommes === 0 && $hour >= 9) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'exclamation-triangle',
                'titre' => 'Aucun repas enregistré',
                'message' => "Vous n'avez encore rien mangé aujourd'hui. Prenez un repas équilibré !",
            ];
            // Envoi notification Twilio si aucun repas pris
            $numeroProche = $regime->getDemande() ? $regime->getDemande()->getNumeroProche() : null;
            if ($numeroProche) {
                $today = new \DateTime('today');
                $alreadySent = $em->getRepository(\App\Entity\Notification::class)->createQueryBuilder('n')
                    ->select('COUNT(n.id)')
                    ->where('n.type = :type')
                    ->andWhere('n.relatedId = :seniorId')
                    ->andWhere('n.createdAt >= :today')
                    ->setParameter('type', 'sms_alert_no_meal')
                    ->setParameter('seniorId', $user->getId())
                    ->setParameter('today', $today)
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($alreadySent == 0) {
                    $this->sendTwilioAlert($httpClient, $numeroProche, "Alerte : Aucun repas pris aujourd'hui par votre proche.");
                    
                    // Enregistrer l'envoi
                    $notif = new \App\Entity\Notification();
                    $notif->setType('sms_alert_no_meal');
                    $notif->setMessage("SMS envoyé au proche : Aucun repas pris.");
                    $notif->setRelatedId($user->getId());
                    $em->persist($notif);
                    $em->flush();
                }
            }
        }

        if ($caloriesConsommees > $caloriesLimite) {
            $depassement = $caloriesConsommees - $caloriesLimite;
            $notifications[] = [
                'type' => 'danger',
                'icon' => 'times-circle',
                'titre' => 'Limite calorique dépassée',
                'message' => "Vous avez dépassé votre limite de {$depassement} kcal. Privilégiez des aliments légers.",
            ];
            // Envoi notification Twilio si dépassement > triple
            $numeroProche = $regime->getDemande() ? $regime->getDemande()->getNumeroProche() : null;
            if ($numeroProche && $caloriesConsommees > $caloriesLimite * 3) {
                $today = new \DateTime('today');
                $alreadySent = $em->getRepository(\App\Entity\Notification::class)->createQueryBuilder('n')
                    ->select('COUNT(n.id)')
                    ->where('n.type = :type')
                    ->andWhere('n.relatedId = :seniorId')
                    ->andWhere('n.createdAt >= :today')
                    ->setParameter('type', 'sms_alert_excess_calories')
                    ->setParameter('seniorId', $user->getId())
                    ->setParameter('today', $today)
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($alreadySent == 0) {
                    $msg = "Alerte : Votre proche a un dépassement calorique aujourd'hui ({$caloriesConsommees} kcal). C'est dangereux, il a dépassé trois fois sa limite autorisée. Veuillez le contacter rapidement.";
                    $this->sendTwilioAlert($httpClient, $numeroProche, $msg);
                    
                    // Enregistrer l'envoi
                    $notif = new \App\Entity\Notification();
                    $notif->setType('sms_alert_excess_calories');
                    $notif->setMessage("SMS envoyé au proche : Dépassement calorique triple.");
                    $notif->setRelatedId($user->getId());
                    $em->persist($notif);
                    $em->flush();
                }
            }
        } elseif ($caloriesConsommees > $caloriesLimite * 0.85 && $repasRestants > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'exclamation-triangle',
                'titre' => 'Proche de la limite',
                'message' => "Il vous reste {$caloriesRestantes} kcal pour {$repasRestants} repas. Faites attention aux portions.",
            ];
        }


        // Current meal reminder
        foreach ($suggestionsRepas as $meal) {
            if ($meal['statut'] === 'maintenant') {
                $notifications[] = [
                    'type' => 'info',
                    'icon' => 'clock',
                    'titre' => "C'est l'heure : {$meal['type_repas']}",
                    'message' => "Votre {$meal['type_repas']} est prévu maintenant (~{$meal['calories_suggerees']} kcal).",
                ];
                break;
            }
            if ($meal['statut'] === 'en_retard') {
                $notifications[] = [
                    'type' => 'warning',
                    'icon' => 'exclamation-circle',
                    'titre' => "Repas en retard : {$meal['type_repas']}",
                    'message' => "Vous avez raté le créneau de votre {$meal['type_repas']}.",
                ];
            }
        }

        // Hydration reminder
        if ($regime->getHydratationQuotidienne() && $hour >= 10) {
            $litres = round($regime->getHydratationQuotidienne() / 1000, 1);
            $notifications[] = [
                'type' => 'info',
                'icon' => 'tint',
                'titre' => 'Rappel hydratation',
                'message' => "N'oubliez pas de boire vos {$litres}L d'eau aujourd'hui.",
            ];
        }

        // Regime-specific tips
        $tips = [
            'diabétique' => 'Privilégiez les aliments à index glycémique bas. Évitez le sucre raffiné.',
            'hypo_sodé' => 'Limitez le sel. Préférez les herbes et épices pour assaisonner.',
            'sans_gluten' => 'Vérifiez les étiquettes. Attention aux contaminations croisées.',
            'cardioprotecteur' => 'Favorisez les oméga-3 et les fibres. Limitez les graisses saturées.',
        ];
        $regimeType = $regime->getTypeRegime();
        if (isset($tips[$regimeType])) {
            $notifications[] = [
                'type' => 'success',
                'icon' => 'lightbulb',
                'titre' => "Conseil régime " . ucfirst(str_replace('_', ' ', $regimeType)),
                'message' => $tips[$regimeType],
            ];
        }

        if (empty($notifications)) {
            $notifications[] = [
                'type' => 'success',
                'icon' => 'check-circle',
                'titre' => 'Tout est en ordre',
                'message' => 'Continuez à suivre votre régime. Vous êtes sur la bonne voie !',
            ];
        }

        return new JsonResponse([
            'status' => 'success',
            'notifications' => $notifications,
            'suggestions_repas' => $suggestionsRepas,
            'repas_consommes' => $repasConsommes,
            'repas_restants' => $repasRestants,
            'calories_restantes' => $caloriesRestantes,
            'calories_consommees' => $caloriesConsommees,
        ]);
    }

    #[Route('/scan', name: 'app_nutrition_scan')]
    public function scan(RegimePrescritRepository $regimePrescritRepository): Response
    {
        $regime = $this->findLatestUserRegime($regimePrescritRepository);

        return $this->render('front/nutrition/scan.html.twig', [
            'regime' => $regime,
        ]);
    }

    #[Route('/scan/analyze', name: 'app_nutrition_scan_analyze', methods: ['POST'])]
    public function analyze(
        Request $request,
        RegimePrescritRepository $regimePrescritRepository,
        HttpClientInterface $httpClient,
        NutritionAnalysisService $nutritionAnalyzer
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            $barcode = $data['barcode'] ?? null;

            if (!$barcode) {
                return new JsonResponse(['error' => 'Code-barres manquant'], 400);
            }

            // 1. Get the user's active regime to know which diet to check
            $regime = $this->findLatestUserRegime($regimePrescritRepository);
            $dietType = $regime ? $regime->getTypeRegime() : 'normal';

            // 2. Call OpenFoodFacts API
            $url = "https://world.openfoodfacts.org/api/v0/product/{$barcode}.json";
            try {
                $response = $httpClient->request('GET', $url);
                $offData = $response->toArray();
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Erreur de communication avec OpenFoodFacts'], 502);
            }

            if (($offData['status'] ?? 0) !== 1) {
                return new JsonResponse(['error' => 'Produit non trouvé dans la base OpenFoodFacts'], 404);
            }

            $product = $offData['product'];

            // 3. Analyze compatibility with the user's regime
            $analysis = $nutritionAnalyzer->analyze($product, $dietType);

            // 4. Return result to frontend
            return new JsonResponse([
                'success' => true,
                'produit' => [
                    'product_name' => $product['product_name'] ?? 'Produit inconnu',
                    'brands' => $product['brands'] ?? '',
                    'image_url' => $product['image_url'] ?? null,
                    'nutriments' => $product['nutriments'] ?? [],
                    'nutriscore_grade' => $product['nutriscore_grade'] ?? null,
                ],
                'regime' => [
                    'type' => $dietType,
                    'compatible' => $analysis['compatible'],
                    'raison' => $analysis['raison'],
                ],
                'analyse_complete' => $analysis['details'],
                'analyse_globale' => $analysis['all_diets'] ?? [],
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Erreur interne du serveur : ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/request', name: 'app_nutrition_request', methods: ['GET', 'POST'])]
    public function request(Request $request, EntityManagerInterface $em): Response
    {
        $demandeRegime = new DemandeRegime();
        $form = $this->createForm(DemandeRegimeType::class, $demandeRegime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();

            $demandeRegime->setUser($user);
            $demandeRegime->setSeniorId($user->getId());
            $demandeRegime->setNutritionnisteId(2);

            $em->persist($demandeRegime);
            $em->flush();

            $this->addFlash('success', 'Votre demande de régime a été créée avec succès !');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/request.html.twig', [
            'demande_regime' => $demandeRegime,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_nutrition_show', requirements: ['id' => '\d+'])]
    public function show(int $id, DemandeRegimeRepository $demandeRegimeRepository): Response
    {
        $demandeRegime = $demandeRegimeRepository->find($id);

        if (!$demandeRegime) {
            $this->addFlash('error', 'Demande de régime introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        if (!$this->isOwnerOfDemand($demandeRegime)) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cette demande.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/show.html.twig', [
            'demande_regime' => $demandeRegime,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_nutrition_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, DemandeRegimeRepository $demandeRegimeRepository, EntityManagerInterface $em): Response
    {
        $demandeRegime = $demandeRegimeRepository->find($id);

        if (!$demandeRegime) {
            $this->addFlash('error', 'Demande de régime introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        if (!$this->isOwnerOfDemand($demandeRegime)) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette demande.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        if ($demandeRegime->getStatut() === DemandeRegime::STATUT_TRAITE) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée et ne peut plus être modifiée.');
            return $this->redirectToRoute('app_nutrition_show', ['id' => $id]);
        }

        $form = $this->createForm(DemandeRegimeType::class, $demandeRegime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Demande de régime mise à jour avec succès !');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/edit.html.twig', [
            'demande_regime' => $demandeRegime,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_nutrition_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, DemandeRegimeRepository $demandeRegimeRepository, EntityManagerInterface $em): Response
    {
        $demandeRegime = $demandeRegimeRepository->find($id);

        if (!$demandeRegime) {
            $this->addFlash('error', 'Demande de régime introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        if (!$this->isOwnerOfDemand($demandeRegime)) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette demande.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        if ($this->isCsrfTokenValid('delete' . $demandeRegime->getId(), $request->request->get('_token'))) {
            $em->remove($demandeRegime);
            $em->flush();
            $this->addFlash('success', 'Demande de régime supprimée avec succès !');
        }

        return $this->redirectToRoute('app_my_nutrition');
    }

    #[Route('/regime/{id}', name: 'app_nutrition_regime', requirements: ['id' => '\d+'])]
    public function regime(int $id, RegimePrescritRepository $regimePrescritRepository): Response
    {
        $regimePrescrit = $regimePrescritRepository->find($id);

        if (!$regimePrescrit) {
            $this->addFlash('error', 'Régime prescrit introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        if (!$this->isOwnerOfRegime($regimePrescrit)) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce régime.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/regime.html.twig', [
            'regime_prescrit' => $regimePrescrit,
        ]);
    }

    #[Route('/recipe-suggestions', name: 'app_nutrition_recipe_suggestions', methods: ['POST'])]
    public function recipeSuggestions(
        Request $request,
        RegimePrescritRepository $regimePrescritRepository,
        EntityManagerInterface $em,
        MealDbService $mealDbService
    ): JsonResponse {
        $regimeId = $request->request->get('regime_id');
        $regime = $regimePrescritRepository->find($regimeId);

        if (!$regime || !$this->isOwnerOfRegime($regime)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Régime introuvable'], 404);
        }

        $todayStats = $this->getTodayMealStats($em);
        $caloriesRestantes = max(0, $regime->getCaloriesJournalieres() - $todayStats['calories']);
        $repasRestants = max(0, (int) $regime->getRepasParJour() - $todayStats['meals']);

        if ($repasRestants === 0) {
            return new JsonResponse([
                'status' => 'success',
                'recipes' => [],
                'message' => 'Vous avez atteint votre nombre de repas pour aujourd\'hui !',
                'calories_restantes' => $caloriesRestantes,
                'repas_restants' => 0,
            ]);
        }

        // Calories per remaining meal
        $caloriesParRepas = $repasRestants > 0 ? round($caloriesRestantes / $repasRestants) : 0;

        // Get smart suggestions from MealDB based on regime
        $suggestions = $mealDbService->getRegimeSuggestions(
            $regime->getAlimentsRecommandes() ?? [],
            $regime->getAlimentsInterdits() ?? [],
            6
        );

        // Translation maps EN → FR
        $catFr = [
            'Beef' => 'Bœuf', 'Chicken' => 'Poulet', 'Dessert' => 'Dessert',
            'Lamb' => 'Agneau', 'Miscellaneous' => 'Divers', 'Pasta' => 'Pâtes',
            'Pork' => 'Porc', 'Seafood' => 'Fruits de mer', 'Side' => 'Accompagnement',
            'Starter' => 'Entrée', 'Vegan' => 'Végan', 'Vegetarian' => 'Végétarien',
            'Breakfast' => 'Petit-déjeuner', 'Goat' => 'Chèvre',
        ];
        $areaFr = [
            'American' => 'Américaine', 'British' => 'Britannique', 'Canadian' => 'Canadienne',
            'Chinese' => 'Chinoise', 'Croatian' => 'Croate', 'Dutch' => 'Néerlandaise',
            'Egyptian' => 'Égyptienne', 'Filipino' => 'Philippine', 'French' => 'Française',
            'Greek' => 'Grecque', 'Indian' => 'Indienne', 'Irish' => 'Irlandaise',
            'Italian' => 'Italienne', 'Jamaican' => 'Jamaïcaine', 'Japanese' => 'Japonaise',
            'Kenyan' => 'Kényane', 'Malaysian' => 'Malaisienne', 'Mexican' => 'Mexicaine',
            'Moroccan' => 'Marocaine', 'Polish' => 'Polonaise', 'Portuguese' => 'Portugaise',
            'Russian' => 'Russe', 'Spanish' => 'Espagnole', 'Thai' => 'Thaïlandaise',
            'Tunisian' => 'Tunisienne', 'Turkish' => 'Turque', 'Vietnamese' => 'Vietnamienne',
            'Unknown' => '', '' => '',
        ];
        $tagFr = [
            'Meat' => 'Viande', 'Healthy' => 'Sain', 'Light' => 'Léger', 'Spicy' => 'Épicé',
            'Sweet' => 'Sucré', 'Savoury' => 'Salé', 'Soup' => 'Soupe', 'Stew' => 'Ragoût',
            'Snack' => 'En-cas', 'Breakfast' => 'Petit-déjeuner', 'Lunch' => 'Déjeuner',
            'Dinner' => 'Dîner', 'Side' => 'Accompagnement', 'Comfort' => 'Réconfortant',
            'Dairy' => 'Laitier', 'Curry' => 'Curry', 'BBQ' => 'Barbecue',
            'Seafood' => 'Fruits de mer', 'Fish' => 'Poisson', 'Pasta' => 'Pâtes',
            'Pudding' => 'Pudding', 'Cake' => 'Gâteau', 'Pie' => 'Tarte',
            'Alcoholic' => 'Alcoolisé', 'Baking' => 'Boulangerie', 'Calorific' => 'Calorique',
            'Speciality' => 'Spécialité', 'Vegetarian' => 'Végétarien',
            'DairyFree' => 'Sans lactose', 'UnHealthy' => 'Gourmand',
            'Fruity' => 'Fruité', 'Exotic' => 'Exotique', 'Mild' => 'Doux',
            'HalloweenHalloween' => 'Halloween', 'SideDish' => 'Accompagnement',
            'OnTheGo' => 'À emporter', 'FingerFood' => 'Bouchées',
        ];
        $mealNameFr = [
            'Chicken' => 'Poulet', 'Roast' => 'Rôti', 'Grilled' => 'Grillé',
            'Baked' => 'Au four', 'Fried' => 'Frit', 'Stew' => 'Ragoût de',
            'Soup' => 'Soupe de', 'Salad' => 'Salade de', 'Pie' => 'Tourte',
            'Pasta' => 'Pâtes', 'Rice' => 'Riz', 'Beef' => 'Bœuf',
            'Lamb' => 'Agneau', 'Pork' => 'Porc', 'Fish' => 'Poisson',
            'Salmon' => 'Saumon', 'Tuna' => 'Thon', 'Shrimp' => 'Crevettes',
            'Prawn' => 'Crevettes', 'Cake' => 'Gâteau', 'Bread' => 'Pain',
            'Pancakes' => 'Crêpes', 'with' => 'avec', 'and' => 'et',
            'in' => 'en', 'Spicy' => 'Épicé', 'Sweet' => 'Sucré',
            'Cream' => 'Crème', 'Sauce' => 'Sauce', 'Mushroom' => 'Champignon',
            'Tomato' => 'Tomate', 'Potato' => 'Pomme de terre', 'Garlic' => 'Ail',
            'Onion' => 'Oignon', 'Lemon' => 'Citron', 'Honey' => 'Miel',
            'Cheese' => 'Fromage', 'Egg' => 'Œuf', 'Scrambled' => 'Brouillés',
            'Stuffed' => 'Farci', 'Smoked' => 'Fumé', 'Braised' => 'Braisé',
            'Steamed' => 'À la vapeur', 'Curry' => 'Curry', 'Teriyaki' => 'Teriyaki',
        ];

        $translateName = function (string $name) use ($mealNameFr): string {
            $translated = $name;
            // Sort by length descending to match longer phrases first
            $sorted = $mealNameFr;
            uksort($sorted, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
            foreach ($sorted as $en => $fr) {
                $translated = preg_replace('/\b' . preg_quote($en, '/') . '\b/i', $fr, $translated);
            }
            return $translated;
        };

        $translateTags = function (?string $tags) use ($tagFr): string {
            if (!$tags) return '';
            $parts = array_map('trim', explode(',', $tags));
            $frParts = array_map(fn($t) => $tagFr[$t] ?? $tagFr[ucfirst(strtolower($t))] ?? $t, $parts);
            return implode(',', $frParts);
        };

        // Build French description from category + area
        $buildDescription = function (array $detail) use ($catFr, $areaFr): string {
            $cat = $catFr[$detail['strCategory'] ?? ''] ?? ($detail['strCategory'] ?? '');
            $area = $areaFr[$detail['strArea'] ?? ''] ?? ($detail['strArea'] ?? '');
            $desc = 'Recette';
            if ($cat) $desc .= ' de ' . mb_strtolower($cat);
            if ($area) $desc .= ', cuisine ' . mb_strtolower($area);
            $desc .= '. ';

            // Extract key ingredients for a French summary
            $ingredients = [];
            for ($i = 1; $i <= 6; $i++) {
                $ing = $detail['strIngredient' . $i] ?? '';
                if ($ing && trim($ing) !== '') {
                    $ingredients[] = trim($ing);
                }
            }
            if ($ingredients) {
                $desc .= 'Ingrédients principaux : ' . implode(', ', $ingredients) . '.';
            }
            return $desc;
        };

        // Enrich with details — all translated to French
        $enriched = [];
        foreach (array_slice($suggestions, 0, 6) as $meal) {
            try {
                $detail = $mealDbService->getMealDetails($meal['idMeal']);
                if ($detail) {
                    $enriched[] = [
                        'id' => $detail['idMeal'],
                        'name' => $translateName($detail['strMeal']),
                        'category' => $catFr[$detail['strCategory'] ?? ''] ?? ($detail['strCategory'] ?? ''),
                        'area' => $areaFr[$detail['strArea'] ?? ''] ?? ($detail['strArea'] ?? ''),
                        'image' => $detail['strMealThumb'] ?? '',
                        'instructions' => $buildDescription($detail),
                        'tags' => $translateTags($detail['strTags'] ?? ''),
                    ];
                }
            } catch (\Exception $e) {
                $enriched[] = [
                    'id' => $meal['idMeal'],
                    'name' => $translateName($meal['strMeal'] ?? 'Recette'),
                    'image' => $meal['strMealThumb'] ?? '',
                    'category' => '',
                    'area' => '',
                    'instructions' => '',
                    'tags' => '',
                ];
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'recipes' => $enriched,
            'calories_restantes' => $caloriesRestantes,
            'calories_par_repas' => $caloriesParRepas,
            'repas_restants' => $repasRestants,
            'regime_type' => $regime->getTypeRegime(),
            'aliments_recommandes' => $regime->getAlimentsRecommandes() ?? [],
            'aliments_interdits' => $regime->getAlimentsInterdits() ?? [],
        ]);
    }

    // ══════════════════════════════════════════════
    //  ADVANCED ML ROUTES
    // ══════════════════════════════════════════════

    #[Route('/trends', name: 'app_nutrition_trends', methods: ['POST'])]
    public function trends(
        Request $request,
        RegimePrescritRepository $regimePrescritRepository,
        EntityManagerInterface $em,
        PythonMLService $pythonMLService
    ): JsonResponse {
        $regime = $this->findLatestUserRegime($regimePrescritRepository);
        if (!$regime) {
            return new JsonResponse(['status' => 'error', 'message' => 'Aucun régime trouvé'], 404);
        }

        // Get meal history for the last 30 days
        $user = $this->getUser();
        $since = new \DateTime('-30 days');
        $meals = $em->getRepository(SuiviRepas::class)->createQueryBuilder('s')
            ->where('s.senior = :senior')
            ->andWhere('s.dateRepas >= :since')
            ->setParameter('senior', $user)
            ->setParameter('since', $since)
            ->orderBy('s.dateRepas', 'ASC')
            ->getQuery()
            ->getResult();

        $mealHistory = [];
        foreach ($meals as $meal) {
            $mealHistory[] = [
                'date' => $meal->getDateRepas()->format('Y-m-d H:i'),
                'aliments' => $meal->getAlimentsIdentifies() ?? [],
                'calories' => $meal->getCaloriesCalculees() ?? 0,
                'estConforme' => $meal->isEstConforme(),
            ];
        }

        // ── Appel ML optionnel : uniquement si use_ml=1 (bouton IA côté JS) ──
        if ($request->request->get('use_ml') === '1') {
            try {
                $result = $pythonMLService->analyzeTrends($mealHistory);
                if (($result['status'] ?? '') === 'success') {
                    return new JsonResponse($result);
                }
            } catch (\Exception $e) {
                // ML indisponible — continuer avec le fallback PHP
            }
        }

        // Fallback: comprehensive PHP trend analysis
        $foodCounts = [];
        $totalMeals = count($meals);
        $totalCalories = 0;
        $conformes = 0;
        $dailyCalories = [];

        foreach ($meals as $meal) {
            $totalCalories += $meal->getCaloriesCalculees() ?? 0;
            if ($meal->isEstConforme()) $conformes++;
            $date = $meal->getDateRepas()->format('Y-m-d');
            $dailyCalories[$date] = ($dailyCalories[$date] ?? 0) + ($meal->getCaloriesCalculees() ?? 0);

            foreach ($meal->getAlimentsIdentifies() ?? [] as $aliment) {
                $name = is_string($aliment) ? $aliment : ($aliment['nom'] ?? '');
                if ($name && $name !== 'non_detecte') {
                    $foodCounts[$name] = ($foodCounts[$name] ?? 0) + 1;
                }
            }
        }
        arsort($foodCounts);
        $topFoods = array_slice($foodCounts, 0, 10, true);

        // Build tendances (calorie trend per day)
        $tendances = [];
        foreach ($dailyCalories as $date => $cals) {
            $tendances[] = ['date' => $date, 'calories' => $cals];
        }

        // Build alerts
        $alertes = [];
        if ($totalMeals < 5) {
            $alertes[] = ['type' => 'info', 'icon' => 'info-circle', 'titre' => 'Données insuffisantes', 'message' => "Seulement $totalMeals repas enregistrés sur 30 jours. Continuez à photographier vos repas pour une analyse plus précise."];
        }
        if ($totalMeals > 0) {
            $avgCals = round($totalCalories / $totalMeals);
            $caloriesLimit = $regime->getCaloriesJournalieres();
            $caloriesPerMeal = round($caloriesLimit / ($regime->getRepasParJour() ?: 3));
            if ($avgCals > $caloriesPerMeal * 1.2) {
                $alertes[] = ['type' => 'warning', 'icon' => 'exclamation-triangle', 'titre' => 'Calories moyennes élevées', 'message' => "Vos repas apportent en moyenne {$avgCals} kcal, soit plus que l'objectif de {$caloriesPerMeal} kcal/repas."];
            }
            // Check food variety
            if (count($foodCounts) < 5 && $totalMeals >= 10) {
                $alertes[] = ['type' => 'warning', 'icon' => 'exclamation-circle', 'titre' => 'Faible variété alimentaire', 'message' => "Seulement " . count($foodCounts) . " aliments différents détectés. Essayez de diversifier votre alimentation."];
            }
            // Conformity alert
            $tauxConf = round($conformes / $totalMeals * 100);
            if ($tauxConf < 50) {
                $alertes[] = ['type' => 'danger', 'icon' => 'times-circle', 'titre' => 'Conformité faible', 'message' => "Seulement {$tauxConf}% de vos repas sont conformes à votre régime. Consultez les aliments recommandés."];
            }
        }

        return new JsonResponse([
            'status' => 'success',
            'tendances' => $tendances,
            'alertes' => $alertes,
            'variete_score' => min(100, count($foodCounts) * 10),
            'aliments_uniques' => count($foodCounts),
            'aliments_frequents' => array_map(fn($k, $v) => ['nom' => str_replace('_', ' ', ucfirst($k)), 'count' => $v], array_keys($topFoods), array_values($topFoods)),
            'jours_analyses' => (int)$since->diff(new \DateTime())->days,
        ]);
    }

    #[Route('/risk-score', name: 'app_nutrition_risk_score', methods: ['POST'])]
    public function riskScore(
        Request $request,
        RegimePrescritRepository $regimePrescritRepository,
        EntityManagerInterface $em,
        PythonMLService $pythonMLService
    ): JsonResponse {
        $regime = $this->findLatestUserRegime($regimePrescritRepository);
        if (!$regime) {
            return new JsonResponse(['status' => 'error', 'message' => 'Aucun régime trouvé'], 404);
        }

        $user = $this->getUser();
        $since = new \DateTime('-14 days');
        $meals = $em->getRepository(SuiviRepas::class)->createQueryBuilder('s')
            ->where('s.senior = :senior')
            ->andWhere('s.dateRepas >= :since')
            ->setParameter('senior', $user)
            ->setParameter('since', $since)
            ->orderBy('s.dateRepas', 'ASC')
            ->getQuery()
            ->getResult();

        $mealHistory = [];
        foreach ($meals as $meal) {
            $mealHistory[] = [
                'date' => $meal->getDateRepas()->format('Y-m-d H:i'),
                'aliments' => $meal->getAlimentsIdentifies() ?? [],
                'calories' => $meal->getCaloriesCalculees() ?? 0,
                'estConforme' => $meal->isEstConforme(),
            ];
        }

        // Try Python ML
        try {
            $result = $pythonMLService->calculateRiskScore(
                $regime->getPoidsActuel(),
                $regime->getTaille(),
                null, // Age not on regime
                $mealHistory,
                $regime->getTypeRegime(),
                $regime->getCaloriesJournalieres()
            );
            if (($result['status'] ?? '') === 'success') {
                return new JsonResponse($result);
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // Comprehensive PHP fallback for risk score
        $score = 15;
        $details = [];
        $recommandations = [];
        $totalMeals = count($meals);
        $totalCalories = 0;
        $conformes = 0;

        foreach ($meals as $meal) {
            $totalCalories += $meal->getCaloriesCalculees() ?? 0;
            if ($meal->isEstConforme()) $conformes++;
        }

        // IMC analysis
        if ($regime->getPoidsActuel() && $regime->getTaille()) {
            $imc = $regime->getImc();
            if ($imc) {
                if ($imc < 18.5) {
                    $score += 25;
                    $details[] = "IMC $imc : sous-poids — risque de carences nutritionnelles";
                    $recommandations[] = "Augmentez progressivement votre apport calorique avec des aliments nutritifs (oléagineux, avocats, féculents complets).";
                } elseif ($imc > 30) {
                    $score += 20;
                    $details[] = "IMC $imc : obésité — risque cardiovasculaire et métabolique augmenté";
                    $recommandations[] = "Consultez un professionnel de santé pour un suivi personnalisé. Privilégiez les légumes et protéines maigres.";
                } elseif ($imc > 25) {
                    $score += 10;
                    $details[] = "IMC $imc : surpoids — surveillance recommandée";
                    $recommandations[] = "Surveillez vos portions et augmentez votre activité physique.";
                } else {
                    $details[] = "IMC $imc : poids normal";
                }
            }
        }

        // Calorie compliance analysis
        if ($totalMeals > 0) {
            $avgDailyCalories = round($totalCalories / max(1, count(array_unique(array_map(fn($m) => $m->getDateRepas()->format('Y-m-d'), $meals)))));
            $limit = $regime->getCaloriesJournalieres();
            if ($avgDailyCalories > $limit * 1.3) {
                $score += 20;
                $details[] = "Apport calorique moyen ({$avgDailyCalories} kcal/jour) dépasse significativement l'objectif ({$limit} kcal)";
                $recommandations[] = "Réduisez les portions et limitez les aliments riches en calories (fast-food, sucres).";
            } elseif ($avgDailyCalories > $limit * 1.1) {
                $score += 10;
                $details[] = "Apport calorique moyen ({$avgDailyCalories} kcal/jour) légèrement au-dessus de l'objectif ({$limit} kcal)";
            }
        }

        // Conformity analysis
        if ($totalMeals > 3) {
            $tauxConf = round($conformes / $totalMeals * 100);
            if ($tauxConf < 40) {
                $score += 15;
                $details[] = "Taux de conformité faible : {$tauxConf}%";
                $recommandations[] = "Suivez de plus près les recommandations de votre régime {$regime->getTypeRegime()}.";
            } elseif ($tauxConf < 70) {
                $score += 5;
                $details[] = "Taux de conformité moyen : {$tauxConf}%";
            }
        }

        // Insufficient data penalty
        if ($totalMeals < 3) {
            $recommandations[] = "Enregistrez régulièrement vos repas pour un score plus fiable.";
        }

        $score = min(100, $score);

        return new JsonResponse([
            'status' => 'success',
            'score' => $score,
            'niveau' => $score >= 70 ? 'critique' : ($score >= 50 ? 'eleve' : ($score >= 30 ? 'modere' : 'faible')),
            'couleur' => $score >= 70 ? '#dc2626' : ($score >= 50 ? '#f59e0b' : ($score >= 30 ? '#f97316' : '#10b981')),
            'imc' => $regime->getImc(),
            'details' => $details,
            'recommandations' => $recommandations,
        ]);
    }

    #[Route('/nutritionist-summary', name: 'app_nutrition_summary', methods: ['POST'])]
    public function nutritionistSummary(
        Request $request,
        RegimePrescritRepository $regimePrescritRepository,
        EntityManagerInterface $em,
        PythonMLService $pythonMLService
    ): JsonResponse {
        $regime = $this->findLatestUserRegime($regimePrescritRepository);
        if (!$regime) {
            return new JsonResponse(['status' => 'error', 'message' => 'Aucun régime trouvé'], 404);
        }

        $user = $this->getUser();
        $since = new \DateTime('-30 days');
        $meals = $em->getRepository(SuiviRepas::class)->createQueryBuilder('s')
            ->where('s.senior = :senior')
            ->andWhere('s.dateRepas >= :since')
            ->setParameter('senior', $user)
            ->setParameter('since', $since)
            ->orderBy('s.dateRepas', 'ASC')
            ->getQuery()
            ->getResult();

        $mealHistory = [];
        foreach ($meals as $meal) {
            $mealHistory[] = [
                'date' => $meal->getDateRepas()->format('Y-m-d H:i'),
                'aliments' => $meal->getAlimentsIdentifies() ?? [],
                'calories' => $meal->getCaloriesCalculees() ?? 0,
                'estConforme' => $meal->isEstConforme(),
            ];
        }

        // Try Python ML
        try {
            $result = $pythonMLService->generateNutritionistSummary(
                $mealHistory,
                $regime->getTypeRegime(),
                $regime->getCaloriesJournalieres(),
                $regime->getPoidsActuel(),
                $regime->getTaille(),
                null,
                $regime->getAlimentsRecommandes() ?? [],
                $regime->getAlimentsInterdits() ?? []
            );
            if (($result['status'] ?? '') === 'success') {
                return new JsonResponse($result);
            }
        } catch (\Exception $e) {
            // Fallback
        }

        // Comprehensive PHP fallback for nutritionist summary
        $totalMeals = count($meals);
        $conformes = 0;
        $totalCalories = 0;
        $foodCounts = [];
        $interditsConsommes = [];
        $alimentsInterdits = $regime->getAlimentsInterdits() ?? [];

        foreach ($meals as $meal) {
            if ($meal->isEstConforme()) $conformes++;
            $totalCalories += $meal->getCaloriesCalculees() ?? 0;

            foreach ($meal->getAlimentsIdentifies() ?? [] as $aliment) {
                $name = is_string($aliment) ? $aliment : ($aliment['nom'] ?? '');
                if ($name && $name !== 'non_detecte') {
                    $foodCounts[$name] = ($foodCounts[$name] ?? 0) + 1;
                    // Check if this food is in the forbidden list
                    foreach ($alimentsInterdits as $interdit) {
                        if (stripos($name, $interdit) !== false || stripos($interdit, $name) !== false) {
                            if (!in_array($name, $interditsConsommes)) {
                                $interditsConsommes[] = str_replace('_', ' ', ucfirst($name));
                            }
                        }
                    }
                }
            }
        }
        arsort($foodCounts);
        $topFoods = array_slice($foodCounts, 0, 8, true);

        $tauxConformite = $totalMeals > 0 ? round($conformes / $totalMeals * 100, 1) : 0;
        $avgCalories = $totalMeals > 0 ? round($totalCalories / $totalMeals) : 0;
        $varieteScore = min(100, count($foodCounts) * 10);

        // Generate personalized suggestions
        $suggestions = [];
        if ($tauxConformite < 50) {
            $suggestions[] = "Votre taux de conformité est faible ({$tauxConformite}%). Essayez de suivre les recommandations de votre régime '{$regime->getTypeRegime()}' plus strictement.";
        } elseif ($tauxConformite < 75) {
            $suggestions[] = "Bon effort ({$tauxConformite}% conformité) ! Continuez à améliorer votre alimentation.";
        }

        $avgDailyLimit = $regime->getCaloriesJournalieres();
        $caloriesPerMeal = round($avgDailyLimit / ($regime->getRepasParJour() ?: 3));
        if ($avgCalories > $caloriesPerMeal * 1.2) {
            $suggestions[] = "Vos repas sont en moyenne à {$avgCalories} kcal, au-dessus de l'objectif de {$caloriesPerMeal} kcal/repas. Réduisez les portions.";
        } elseif ($avgCalories > 0 && $avgCalories < $caloriesPerMeal * 0.7) {
            $suggestions[] = "Vos repas semblent légers (moy. {$avgCalories} kcal). Assurez-vous de manger suffisamment pour couvrir vos besoins.";
        }

        if ($varieteScore < 40) {
            $suggestions[] = "Diversifiez votre alimentation : essayez d'incorporer plus de fruits, légumes et protéines variées.";
        }

        if (!empty($interditsConsommes)) {
            $suggestions[] = "Attention : certains aliments interdits par votre régime ont été détectés dans vos repas.";
        }

        if ($totalMeals < 5) {
            $suggestions[] = "Enregistrez plus de repas pour obtenir un bilan plus complet et précis.";
        }

        return new JsonResponse([
            'status' => 'success',
            'resume' => [
                'nombre_repas' => $totalMeals,
                'taux_conformite' => $tauxConformite,
                'calories_moyenne' => $avgCalories,
                'objectif_calories' => $avgDailyLimit,
                'regime' => $regime->getTypeRegime(),
            ],
            'aliments_frequents' => array_map(fn($k, $v) => ['nom' => str_replace('_', ' ', ucfirst($k)), 'count' => $v], array_keys($topFoods), array_values($topFoods)),
            'aliments_interdits_consommes' => $interditsConsommes,
            'suggestions_ajustement' => $suggestions,
            'variete_score' => $varieteScore,
        ]);
    }
    #[Route('/test-alert-triple-calories', name: 'app_nutrition_test_alert', methods: ['GET'])]
    public function testAlert(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        
        // Simuler un repas à 5000 calories pour aujourd'hui
        $suivi = new SuiviRepas();
        $suivi->setSenior($user);
        $suivi->setDateRepas(new \DateTime());
        $suivi->setAlimentsIdentifies(['Test Alert Aliments']);
        $suivi->setCaloriesCalculees(5000); // Triple de la plupart des régimes (souvent 1500-2000)
        $suivi->setEstConforme(false);
        $suivi->setCommentairesIA("Simulation pour test Twilio");
        
        $em->persist($suivi);
        $em->flush();
        
        return $this->render('front/nutrition/test_alert.html.twig', [
            'message' => 'Repas de 5000 calories ajouté. Allez sur le tableau de bord pour déclencher l\'alerte.',
        ]);
    }

    private function sendTwilioAlert(HttpClientInterface $httpClient, string $to, string $message): void
    {
        $sid = $_ENV['TWILIO_ACCOUNT_SID'] ?? null;
        $token = $_ENV['TWILIO_AUTH_TOKEN'] ?? null;
        $from = $_ENV['TWILIO_FROM_NUMBER'] ?? null;

        if (!$sid || !$token || !$from) {
            return;
        }

        try {
            $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";
            $httpClient->request('POST', $url, [
                'auth_basic' => [$sid, $token],
                'body' => [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $message,
                ],
            ]);
        } catch (\Exception $e) {
            // Log error if needed, but don't crash the app
        }
    }
}
