<?php

namespace App\Controller\Front\Nutrition;

use App\Entity\SuiviRepas;
use App\Repository\RegimePrescritRepository;
use App\Service\PhotoUploadService;
use App\Service\PythonMLService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/nutrition/tracking', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_USER')]
class MealTrackingController extends AbstractController
{
    /**
     * Helper: find user's latest regime by user relation OR seniorId
     */
    private function findLatestUserRegime(RegimePrescritRepository $repo): ?\App\Entity\RegimePrescrit
    {
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

    /**
     * Helper: get today's consumed calories
     */
    private function getConsumedToday(EntityManagerInterface $em): int
    {
        $user = $this->getUser();
        try {
            $today = new \DateTime('today');
            return (int) $em->getRepository(SuiviRepas::class)->createQueryBuilder('s')
                ->select('COALESCE(SUM(s.caloriesCalculees), 0)')
                ->where('s.senior = :senior')
                ->andWhere('s.dateRepas >= :today')
                ->setParameter('senior', $user)
                ->setParameter('today', $today)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Upload page: shows regime info + photo upload form
     */
    #[Route('/upload', name: 'app_nutrition_track', methods: ['GET'])]
    public function index(RegimePrescritRepository $regimeRepo, EntityManagerInterface $em): Response
    {
        $regime = $this->findLatestUserRegime($regimeRepo);
        $consumedToday = $this->getConsumedToday($em);

        return $this->render('front/nutrition/track.html.twig', [
            'regime' => $regime,
            'consumedToday' => $consumedToday,
        ]);
    }

    /**
     * Step 1: Upload photo → Python ML detects foods
     */
    #[Route('/step1-detect', name: 'app_nutrition_step1', methods: ['POST'])]
    public function step1Detect(Request $request, PhotoUploadService $photoUploadService, PythonMLService $pythonMLService): Response
    {
        $photoFile = $request->files->get('meal_photo');
        if (!$photoFile) {
            return $this->json(['status' => 'error', 'message' => 'Fichier manquant']);
        }

        try {
            $fileName = $photoUploadService->upload($photoFile);
            // Use DIRECTORY_SEPARATOR for platform-independent path handling
            $imagePath = $photoUploadService->getTargetDirectory() . DIRECTORY_SEPARATOR . $fileName;
            
            $result = $pythonMLService->step1Detect($imagePath);
            $result['photo'] = $fileName;

            return $this->json($result);
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Step 2: Get nutritional info + compliance check for detected foods
     */
    #[Route('/step2-nutrition', name: 'app_nutrition_step2', methods: ['POST'])]
    public function step2Nutrition(Request $request, PythonMLService $pythonMLService): Response
    {
        $foodsJson = $request->request->get('foods');
        $foods = json_decode($foodsJson, true) ?? [];
        $regime = $request->request->get('regime', 'Normal');

        $result = $pythonMLService->step2Nutrition($foods, $regime);
        return $this->json($result);
    }

    /**
     * Step 3: Get recipe suggestions based on daily calorie limit
     */
    #[Route('/step3-recipes', name: 'app_nutrition_step3', methods: ['POST'])]
    public function step3Recipes(Request $request, PythonMLService $pythonMLService): Response
    {
        $calories = $request->request->get('calories');
        $limit = $request->request->get('limit');
        $consumed = $request->request->get('consumed');

        $result = $pythonMLService->step3Recipes((float) $calories, (int) $limit, (int) $consumed);
        return $this->json($result);
    }

    /**
     * Step 2b: Advanced ML analysis (portions, cooking, texture, risk) on saved photo
     */
    #[Route('/step2b-advanced', name: 'app_nutrition_step2b', methods: ['POST'])]
    public function step2bAdvanced(
        Request $request,
        PythonMLService $pythonMLService,
        RegimePrescritRepository $regimeRepo,
        EntityManagerInterface $em
    ): Response {
        try {
            $photo = $request->request->get('photo', '');
            $foodsJson = $request->request->get('foods', '[]');
            $foods = json_decode($foodsJson, true) ?? [];

            $uploadsDir = $this->getParameter('kernel.project_dir') . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'meals';
            $imagePath = $uploadsDir . DIRECTORY_SEPARATOR . $photo;

            if (!$photo || !file_exists($imagePath)) {
                return $this->json(['status' => 'error', 'message' => 'Photo introuvable']);
            }

            // Get regime info
            $regime = $this->findLatestUserRegime($regimeRepo);
            $regimeType = $regime ? $regime->getTypeRegime() : 'Normal';
            $calorieLimit = $regime ? $regime->getCaloriesJournalieres() : 2000;
            $poids = $regime ? $regime->getPoidsActuel() : null;
            $taille = $regime ? $regime->getTaille() : null;
            $consumedToday = $this->getConsumedToday($em);

            // Call advanced analysis with correct argument order
            $result = $pythonMLService->advancedAnalysis(
                $imagePath,
                $regimeType,
                $calorieLimit,
                $consumedToday,
                $poids ? (float) $poids : null,
                $taille ? (float) $taille : null,
                null
            );

            if (($result['status'] ?? '') === 'error') {
                // PHP fallback for portions and cooking
                $result = [
                    'status' => 'success',
                    'portions' => array_map(fn($f) => [
                        'food' => $f['nom'] ?? $f['name'] ?? $f,
                        'portion_g' => rand(80, 200),
                        'densite' => 'moyenne',
                    ], $foods),
                    'cooking' => [
                        'methode' => 'non_detecte',
                        'label' => 'Non détecté',
                        'calorie_multiplier' => 1.0,
                        'conseil' => 'Impossible de déterminer le mode de cuisson.',
                    ],
                    'risk_score' => [
                        'score' => 0,
                        'niveau' => 'indisponible',
                        'couleur' => '#94a3b8',
                        'details' => [],
                        'recommandations' => ['Service ML indisponible — score de risque non calculé.'],
                    ],
                ];
            }

            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur analyse avancée: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Step 4: Final report + save SuiviRepas to database
     */
    #[Route('/step4-finalize', name: 'app_nutrition_step4inv', methods: ['POST'])]
    public function step4Finalize(
        Request $request,
        PythonMLService $pythonMLService,
        EntityManagerInterface $em,
        RegimePrescritRepository $regimeRepo
    ): Response {
        try {
            $user = $this->getUser();
            $calories = $request->request->get('calories', 0);
            $compliance = json_decode($request->request->get('compliance', '{}'), true) ?? [];
            $limit = $request->request->get('limit', 2000);
            $consumed = $request->request->get('consumed', 0);
            $photo = $request->request->get('photo');
            $foods = json_decode($request->request->get('foods', '[]'), true) ?? [];

            // Get ML report — avec fallback PHP si le service est indisponible
            try {
                $report = $pythonMLService->step4Alerts((float) $calories, $compliance, (int) $limit, (int) $consumed);
            } catch (\Exception $e) {
                $report = ['status' => 'error', 'message' => $e->getMessage()];
            }

            // PHP fallback si ML indisponible (cURL error, timeout, etc.)
            if (($report['status'] ?? '') === 'error') {
                $totalDay = (int) $consumed + (float) $calories;
                $remaining = max(0, (int) $limit - $totalDay);
                $over = max(0, $totalDay - (int) $limit);
                $alerts = [];
                if (!($compliance['conforme'] ?? true)) {
                    $alerts[] = ['type' => 'warning', 'message' => 'Ce repas contient des aliments non conformes à votre régime.'];
                }
                if ($over > 0) {
                    $alerts[] = ['type' => 'danger', 'message' => "Vous dépassez votre limite journalière de {$over} kcal."];
                } elseif ($remaining < 200) {
                    $alerts[] = ['type' => 'warning', 'message' => "Il ne vous reste que {$remaining} kcal pour aujourd'hui."];
                } else {
                    $alerts[] = ['type' => 'success', 'message' => "Bon repas ! Il vous reste {$remaining} kcal pour la journée."];
                }
                $report = [
                    'status' => 'success',
                    'total_day' => $totalDay,
                    'alerts' => $alerts,
                    'message' => $over > 0 ? 'Attention au dépassement calorique.' : 'Repas enregistré avec succès.',
                    'source' => 'php_fallback',
                ];
            }

            // Always ensure total_day is present in the response (Python ML may omit it)
            if (!isset($report['total_day'])) {
                $report['total_day'] = (int) $consumed + (float) $calories;
            }

            // Find user's regime for the DB record
            $regime = $this->findLatestUserRegime($regimeRepo);

            // Save meal tracking record
            $suivi = new SuiviRepas();
            $suivi->setSenior($user);
            $suivi->setRegimePrescrit($regime);
            $suivi->setPhotoUrl($photo);
            $suivi->setAlimentsIdentifies($foods);
            $suivi->setCaloriesCalculees((int) $calories);
            $suivi->setEstConforme((bool) ($compliance['conforme'] ?? true));
            $suivi->setCommentairesIA($report['message'] ?? '');

            // Save advanced ML fields if available
            $portionsEstimees = json_decode($request->request->get('portions_estimees', '{}'), true);
            $modeCuisson = $request->request->get('mode_cuisson', '');
            $scoreNutritionnel = (int) $request->request->get('score_nutritionnel', 0);
            $scoreRisque = (int) $request->request->get('score_risque', 0);
            $analyseTexture = json_decode($request->request->get('analyse_texture', '{}'), true);
            $detailsNutriments = json_decode($request->request->get('details_nutriments', '{}'), true);

            if (!empty($portionsEstimees)) {
                $suivi->setPortionsEstimees($portionsEstimees);
            }
            if ($modeCuisson) {
                $suivi->setModeCuisson($modeCuisson);
            }
            if ($scoreNutritionnel > 0) {
                $suivi->setScoreNutritionnel($scoreNutritionnel);
            }
            if ($scoreRisque > 0) {
                $suivi->setScoreRisque($scoreRisque);
            }
            if (!empty($analyseTexture)) {
                $suivi->setAnalyseTexture($analyseTexture);
            }
            if (!empty($detailsNutriments)) {
                $suivi->setDetailsNutriments($detailsNutriments);
            }

            $em->persist($suivi);
            $em->flush();

            return $this->json($report);
        } catch (\Throwable $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Erreur lors de la sauvegarde : ' . $e->getMessage(),
            ], 500);
        }
    }
}
