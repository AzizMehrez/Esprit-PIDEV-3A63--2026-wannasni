<?php

namespace App\Controller\Front\Nutrition;

use App\Service\GeminiService;
use App\Service\MealDbService;
use App\Service\AIPrompts;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/nutrition', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_USER')]
class MealController extends AbstractController
{
    #[Route('/meals', name: 'app_nutrition_meals')]
    public function index(Request $request, MealDbService $mealDbService): Response
    {
        $query = $request->query->get('q');
        $category = $request->query->get('category');
        
        if ($query) {
            $meals = $mealDbService->searchMeals($query);
        } elseif ($category) {
            $meals = $mealDbService->filterByCategory($category);
        } else {
            // Default: Show some random or popular categories
            $meals = []; 
        }

        $categories = $mealDbService->getCategories();
        $randomMeal = $mealDbService->getRandomMeal();

        return $this->render('front/nutrition/meals/index.html.twig', [
            'meals' => $meals,
            'categories' => $categories,
            'random_meal' => $randomMeal,
            'current_query' => $query,
            'current_category' => $category
        ]);
    }

    #[Route('/meals/{id}', name: 'app_nutrition_meal_show')]
    public function show(string $id, MealDbService $mealDbService): Response
    {
        $meal = $mealDbService->getMealDetails($id);

        if (!$meal) {
            throw $this->createNotFoundException('Recette introuvable');
        }

        return $this->render('front/nutrition/meals/show.html.twig', [
            'meal' => $meal
        ]);
    }

    #[Route('/ai-coach', name: 'app_nutrition_ai_coach')]
    public function aiCoach(): Response
    {
        return $this->render('front/nutrition/ai_coach.html.twig');
    }

    #[Route('/ai-coach/ask', name: 'app_nutrition_ai_ask', methods: ['POST'])]
    public function askAi(Request $request, GeminiService $geminiService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';

        if (empty($question)) {
            return new JsonResponse(['error' => 'Question vide'], 400);
        }

        // Build prompt with system context
        $prompt = AIPrompts::CHAT_SYSTEM_PROMPT . "\n\nQuestion de l'utilisateur: " . $question;
        
        $response = $geminiService->generateText($prompt);

        return new JsonResponse(['answer' => $response]);
    }
}
