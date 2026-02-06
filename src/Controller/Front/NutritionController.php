<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NutritionController extends AbstractController
{
    #[Route('/{_locale}/nutrition', name: 'app_my_nutrition', requirements: ['_locale' => 'fr|en|ar'])]
    public function index(): Response
    {
        // Mock user
        $user = [
            'firstName' => 'Marie',
            'lastName' => 'Dupont',
        ];

        // Mock nutrition data
        $todayMeals = [
            ['type' => 'breakfast', 'name' => 'Petit-déjeuner', 'time' => '08:00', 'items' => ['Café au lait', 'Tartines', 'Fruits frais'], 'completed' => true, 'calories' => 350],
            ['type' => 'lunch', 'name' => 'Déjeuner', 'time' => '12:30', 'items' => ['Salade composée', 'Poulet grillé', 'Riz complet'], 'completed' => true, 'calories' => 550],
            ['type' => 'snack', 'name' => 'Collation', 'time' => '16:00', 'items' => [], 'completed' => false, 'calories' => 0],
            ['type' => 'dinner', 'name' => 'Dîner', 'time' => '19:00', 'items' => [], 'completed' => false, 'calories' => 0],
        ];

        $waterIntake = 5; // glasses
        $waterGoal = 8;

        $nutritionPlan = [
            'name' => 'Plan Équilibré Santé',
            'dailyCalories' => 1800,
            'currentCalories' => 900,
            'notes' => 'Régime pauvre en sel, riche en fibres',
        ];

        return $this->render('front/nutrition/index.html.twig', [
            'user' => $user,
            'today_meals' => $todayMeals,
            'water_intake' => $waterIntake,
            'water_goal' => $waterGoal,
            'nutrition_plan' => $nutritionPlan,
        ]);
    }

    #[Route('/{_locale}/nutrition/add', name: 'app_my_nutrition_add', requirements: ['_locale' => 'fr|en|ar'])]
    public function add(): Response
    {
        return $this->render('front/nutrition/add.html.twig');
    }
}
