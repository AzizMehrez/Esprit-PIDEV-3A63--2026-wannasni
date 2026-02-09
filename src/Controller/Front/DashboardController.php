<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/{_locale}/dashboard', name: 'app_dashboard', requirements: ['_locale' => 'fr|en|ar'])]
    public function index(Request $request): Response
    {
        // Get the currently logged-in user
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        // Check if profile is incomplete
        $profileIncomplete = !$user->getDateNaissance() || !$user->getAdresse() || 
                           !$user->getVille() || !$user->getCodePostal() || !$user->getPays();

        // Mock dashboard data
        $upcomingActivities = [
            ['name' => 'Morning Walk', 'time' => '8:00', 'date' => 'Demain', 'type' => 'physical'],
            ['name' => 'Memory Games', 'time' => '10:00', 'date' => 'Mer 5 Fév', 'type' => 'cognitive'],
            ['name' => 'Yoga Class', 'time' => '9:00', 'date' => 'Jeu 6 Fév', 'type' => 'physical'],
        ];

        $recentHealth = [
            'date' => new \DateTime('-1 day'),
            'bloodPressure' => '120/80',
            'heartRate' => 72,
            'mood' => 'good',
        ];

        $services = [
            ['type' => 'Medical Transport', 'status' => 'pending', 'requestedAt' => new \DateTime('-2 hours')],
            ['type' => 'Home Care', 'status' => 'in_progress', 'requestedAt' => new \DateTime('-1 day')],
            ['type' => 'Grocery Shopping', 'status' => 'completed', 'requestedAt' => new \DateTime('-3 days')],
        ];

        // Mock nutrition data
        $nutrition = [
            'meals_completed' => 2,
            'meals_total' => 4,
            'water_intake' => 5,
            'water_goal' => 8,
            'calories_today' => 900,
            'calories_goal' => 1800,
            'next_meal' => 'Collation à 16:00',
        ];

        return $this->render('front/dashboard/index.html.twig', [
            'user' => $user,
            'upcoming_activities' => $upcomingActivities,
            'recent_health' => $recentHealth,
            'services' => $services,
            'nutrition' => $nutrition,
            'profile_incomplete' => $profileIncomplete,
        ]);
    }
}

