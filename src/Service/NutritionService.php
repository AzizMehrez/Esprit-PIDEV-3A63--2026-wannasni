<?php

namespace App\Service;

use App\Entity\NutritionPlan;
use App\Entity\NutritionJournal;
use App\Exception\ValidationException;
use App\Exception\BusinessRuleException;

/**
 * NutritionService - Business logic for nutrition management
 */
class NutritionService
{
    private const VALID_MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];
    private const DAILY_WATER_TARGET_ML = 2000;

    /**
     * Log a meal with validation against dietary restrictions
     */
    public function logMeal(int $seniorId, array $data): NutritionJournal
    {
        // Validate meal type
        $mealType = $data['meal_type'] ?? 'snack';
        if (!in_array($mealType, self::VALID_MEAL_TYPES)) {
            throw new ValidationException('Invalid meal type. Valid options: ' . implode(', ', self::VALID_MEAL_TYPES));
        }

        // Get active nutrition plan
        $plan = $this->getActiveNutritionPlan($seniorId);

        // Validate against allergies
        if (isset($data['ingredients'])) {
            $this->validateMealAgainstPlan($data['ingredients'], $plan);
        }

        $journal = new NutritionJournal();
        $journal->setId(rand(1, 10000));
        $journal->setSeniorId($seniorId);
        $journal->setDate(new \DateTime($data['date'] ?? 'now'));
        $journal->setMealType($mealType);
        $journal->setDescription($data['description'] ?? '');
        $journal->setCalories($data['calories'] ?? 0);

        return $journal;
    }

    /**
     * Log water intake
     */
    public function logWaterIntake(int $seniorId, float $ml): array
    {
        if ($ml <= 0 || $ml > 2000) {
            throw new ValidationException('Water intake must be between 1 and 2000 ml');
        }

        // Get today's total (mock)
        $currentTotal = 800; // Mock current intake
        $newTotal = $currentTotal + $ml;

        $remainingForTarget = max(0, self::DAILY_WATER_TARGET_ML - $newTotal);

        return [
            'logged' => $ml,
            'total_today' => $newTotal,
            'target' => self::DAILY_WATER_TARGET_ML,
            'remaining' => $remainingForTarget,
            'target_reached' => $newTotal >= self::DAILY_WATER_TARGET_ML,
        ];
    }

    /**
     * Get daily nutrition summary
     */
    public function getDailySummary(int $seniorId, string $date = 'today'): array
    {
        // Mock: Return sample summary
        return [
            'date' => $date === 'today' ? date('Y-m-d') : $date,
            'total_calories' => 1450,
            'calorie_target' => 2000,
            'meal_count' => 3,
            'water_intake_ml' => 1200,
            'water_target_ml' => self::DAILY_WATER_TARGET_ML,
            'meals' => [
                ['type' => 'breakfast', 'description' => 'Yaourt et fruits', 'calories' => 350],
                ['type' => 'lunch', 'description' => 'Poulet grillé, légumes', 'calories' => 650],
                ['type' => 'snack', 'description' => 'Pomme', 'calories' => 100],
            ]
        ];
    }

    /**
     * Create or update nutrition plan
     */
    public function createNutritionPlan(int $seniorId, array $data): NutritionPlan
    {
        $plan = new NutritionPlan();
        $plan->setId(rand(1, 10000));
        $plan->setSeniorId($seniorId);
        $plan->setDailyCalorieTarget($data['calorie_target'] ?? 2000);
        $plan->setDietaryRestrictions($data['dietary_restrictions'] ?? []);
        $plan->setAllergies($data['allergies'] ?? []);
        $plan->setStartDate(new \DateTime());
        $plan->setIsActive(true);

        return $plan;
    }

    /**
     * Validate meal against dietary plan (allergies and restrictions)
     */
    private function validateMealAgainstPlan(array $ingredients, NutritionPlan $plan): void
    {
        $allergies = $plan->getAllergies();

        foreach ($ingredients as $ingredient) {
            $ingredient = strtolower($ingredient);
            
            // Check allergies
            foreach ($allergies as $allergy) {
                if (str_contains($ingredient, strtolower($allergy))) {
                    throw new BusinessRuleException("Meal contains allergen: {$allergy}");
                }
            }
        }
    }

    /**
     * Get active nutrition plan for senior (mock)
     */
    private function getActiveNutritionPlan(int $seniorId): NutritionPlan
    {
        // Mock: Return a sample plan
        $plan = new NutritionPlan();
        $plan->setId(1);
        $plan->setSeniorId($seniorId);
        $plan->setDailyCalorieTarget(2000);
        $plan->setAllergies(['nuts', 'shellfish']);
        $plan->setDietaryRestrictions(['low-sodium']);
        $plan->setIsActive(true);

        return $plan;
    }
}
