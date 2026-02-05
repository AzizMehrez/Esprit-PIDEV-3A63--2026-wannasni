<?php

namespace App\Controller\Front;

use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    public function __construct(private ActivityRepository $activityRepository)
    {
    }

    #[Route('/{_locale}/dashboard', name: 'app_dashboard', requirements: ['_locale' => 'fr|en|ar'])]
    public function index(): Response
    {
        // Mock user data (in production, get from logged-in user)
        $user = [
            'firstName' => 'Marie',
            'lastName' => 'Dupont',
            'email' => 'marie.dupont@email.com',
        ];

        // Get upcoming activities from database
        $activities = $this->activityRepository->findUpcoming();
        $upcomingActivities = array_slice(
            array_map(function($activity) {
                return [
                    'name' => $activity->getTitle(),
                    'time' => $activity->getStartTime()?->format('H:i') ?? 'TBA',
                    'date' => $activity->getStartTime()?->format('D d M') ?? 'TBA',
                    'type' => $activity->getType(),
                    'location' => $activity->getLocation(),
                ];
            }, $activities),
            0,
            3 // Show only 3 upcoming
        );

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
        ]);
    }
}