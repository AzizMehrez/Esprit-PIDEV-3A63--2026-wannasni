<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'admin_dashboard')]
    public function index(): Response
    {
        // Mock statistics data
        $stats = [
            'total_users' => 1247,
            'active_users' => 892,
            'services_pending' => 23,
            'activities_today' => 45,
            'health_records' => 3456,
            'nutrition_plans' => 178,
        ];

        // Mock recent activities
        $recentActivities = [
            ['user' => 'Marie Dupont', 'action' => 'New service request', 'time' => '5 min ago', 'type' => 'service'],
            ['user' => 'Jean Martin', 'action' => 'Health journal updated', 'time' => '12 min ago', 'type' => 'health'],
            ['user' => 'Sophie Bernard', 'action' => 'Joined activity "Morning Walk"', 'time' => '25 min ago', 'type' => 'activity'],
            ['user' => 'Pierre Durand', 'action' => 'Profile updated', 'time' => '1 hour ago', 'type' => 'user'],
            ['user' => 'Françoise Petit', 'action' => 'New nutrition plan created', 'time' => '2 hours ago', 'type' => 'nutrition'],
        ];

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recent_activities' => $recentActivities,
        ]);
    }
}
