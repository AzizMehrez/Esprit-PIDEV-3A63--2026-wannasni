<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/nutrition')]
class NutritionAdminController extends AbstractController
{
    private function getMockNutritionPlans(): array
    {
        return [
            ['id' => 1, 'user' => 'Marie Dupont', 'name' => 'Heart Healthy Diet', 'type' => 'therapeutic', 'calories' => 1800, 'startDate' => new \DateTime('-30 days'), 'status' => 'active', 'notes' => 'Low sodium, high fiber'],
            ['id' => 2, 'user' => 'Jean Martin', 'name' => 'Diabetic Meal Plan', 'type' => 'therapeutic', 'calories' => 2000, 'startDate' => new \DateTime('-20 days'), 'status' => 'active', 'notes' => 'Blood sugar management'],
            ['id' => 3, 'user' => 'Sophie Bernard', 'name' => 'Mediterranean Diet', 'type' => 'general', 'calories' => 1600, 'startDate' => new \DateTime('-15 days'), 'status' => 'active', 'notes' => 'Balanced nutrition'],
            ['id' => 4, 'user' => 'Pierre Durand', 'name' => 'Weight Management', 'type' => 'weight_loss', 'calories' => 1500, 'startDate' => new \DateTime('-45 days'), 'status' => 'completed', 'notes' => 'Calorie deficit diet'],
        ];
    }

    #[Route('/', name: 'admin_nutrition')]
    public function index(): Response
    {
        return $this->render('admin/nutrition/index.html.twig', [
            'plans' => $this->getMockNutritionPlans(),
        ]);
    }

    #[Route('/{id}', name: 'admin_nutrition_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $plans = $this->getMockNutritionPlans();
        $plan = null;
        foreach ($plans as $p) {
            if ($p['id'] === $id) {
                $plan = $p;
                break;
            }
        }

        if (!$plan) {
            throw $this->createNotFoundException('Nutrition plan not found');
        }

        return $this->render('admin/nutrition/show.html.twig', [
            'plan' => $plan,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_nutrition_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $plans = $this->getMockNutritionPlans();
        $plan = null;
        foreach ($plans as $p) {
            if ($p['id'] === $id) {
                $plan = $p;
                break;
            }
        }

        if (!$plan) {
            throw $this->createNotFoundException('Nutrition plan not found');
        }

        return $this->render('admin/nutrition/edit.html.twig', [
            'plan' => $plan,
        ]);
    }

    #[Route('/new', name: 'admin_nutrition_new')]
    public function new(): Response
    {
        return $this->render('admin/nutrition/new.html.twig');
    }
}
