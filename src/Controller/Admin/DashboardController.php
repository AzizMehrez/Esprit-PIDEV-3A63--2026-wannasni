<?php

namespace App\Controller\Admin;

use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    public function __construct(
        private ParticipationRepository $participationRepository
    ) {
    }

    #[Route('/', name: 'admin_dashboard')]
    public function index(): Response
    {
        // Get real participation statistics
        $totalParticipations = $this->participationRepository->count([]);
        $presentParticipations = $this->participationRepository->count(['status' => 'présent']);
        $pendingParticipations = $this->participationRepository->count(['status' => 'inscrit']);
        $withFeedback = $this->participationRepository->countWithFeedback();

        // Mock statistics data (you can replace with real data later)
        $stats = [
            'total_users' => 1247,
            'active_users' => 892,
            'total_participations' => $totalParticipations,
            'present_participations' => $presentParticipations,
            'pending_participations' => $pendingParticipations,
            'participations_with_feedback' => $withFeedback,
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
