<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\LoyaltyService;
use App\Repository\LoyaltyPointRepository;
use App\Repository\LoyaltyRewardRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/loyalty')]
class LoyaltyAdminController extends AbstractController
{
    public function __construct(
        private LoyaltyService $loyaltyService,
        private LoyaltyPointRepository $pointRepo,
        private LoyaltyRewardRepository $rewardRepo,
        private UserRepository $userRepo,
    ) {}

    // ─── Dashboard Admin Fidélité (lecture seule) ─────────────────────

    #[Route('/', name: 'admin_loyalty')]
    public function index(): Response
    {
        $stats = $this->loyaltyService->getAdminStats();

        // Get leaderboard with user details
        $leaderboard = [];
        foreach ($stats['leaderboard'] as $entry) {
            $user = $this->userRepo->find($entry['seniorId']);
            if ($user) {
                $leaderboard[] = [
                    'user' => $user,
                    'totalPoints' => (int) $entry['totalPoints'],
                ];
            }
        }

        // Recent rewards
        $recentRewards = $this->rewardRepo->findBy([], ['createdAt' => 'DESC'], 20);

        // Recent points history (last 30 entries)
        $recentPoints = $this->pointRepo->findBy([], ['earnedAt' => 'DESC'], 30);

        return $this->render('admin/loyalty/index.html.twig', [
            'rewardStats' => $stats['rewardStats'],
            'leaderboard' => $leaderboard,
            'recentRewards' => $recentRewards,
            'recentPoints' => $recentPoints,
        ]);
    }

    // ─── Détail d'un Utilisateur ────────────────────────────────────────

    #[Route('/user/{id}', name: 'admin_loyalty_user')]
    public function userDetail(int $id): Response
    {
        $user = $this->userRepo->find($id);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('admin_loyalty');
        }

        $dashboardData = $this->loyaltyService->getDashboardData($user);

        return $this->render('admin/loyalty/user_detail.html.twig', [
            'user' => $user,
            'data' => $dashboardData,
        ]);
    }
}
