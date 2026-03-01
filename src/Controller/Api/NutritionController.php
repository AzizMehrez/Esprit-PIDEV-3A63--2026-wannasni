<?php

namespace App\Controller\Api;

use App\Service\NutritionService;
use App\Exception\ValidationException;
use App\Exception\BusinessRuleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * NutritionController - API endpoints for nutrition management
 */
#[Route('/api/nutrition')]
class NutritionController extends AbstractController
{
    public function __construct(
        private NutritionService $nutritionService
    ) {}

    /**
     * Log a meal
     */
    #[Route('/meals', name: 'api_meal_log', methods: ['POST'])]
    public function logMeal(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = 1; // Mock user ID

            $meal = $this->nutritionService->logMeal($userId, $data);

            return $this->json([
                'success' => true,
                'meal' => [
                    'id' => $meal->getId(),
                    'meal_type' => $meal->getMealType(),
                    'calories' => $meal->getCalories(),
                ]
            ], 201);

        } catch (ValidationException | BusinessRuleException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Log water intake
     */
    #[Route('/water', name: 'api_water_log', methods: ['POST'])]
    public function logWater(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = 1; // Mock user ID

            $result = $this->nutritionService->logWaterIntake($userId, $data['amount_ml'] ?? 0);

            return $this->json([
                'success' => true,
                'hydration' => $result
            ]);

        } catch (ValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ], 422);
        }
    }

    /**
     * Get daily nutrition summary
     */
    #[Route('/summary', name: 'api_nutrition_summary', methods: ['GET'])]
    public function getDailySummary(Request $request): JsonResponse
    {
        $userId = 1; // Mock user ID
        $date = $request->query->get('date', 'today');

        $summary = $this->nutritionService->getDailySummary($userId, $date);

        return $this->json([
            'success' => true,
            'summary' => $summary
        ]);
    }

    /**
     * Create nutrition plan
     */
    #[Route('/plan', name: 'api_nutrition_plan', methods: ['POST'])]
    public function createPlan(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userId = 1; // Mock user ID

        $plan = $this->nutritionService->createNutritionPlan($userId, $data);

        return $this->json([
            'success' => true,
            'plan' => [
                'id' => $plan->getId(),
                'calorie_target' => $plan->getDailyCalorieTarget(),
                'allergies' => $plan->getAllergies(),
            ]
        ], 201);
    }
}
