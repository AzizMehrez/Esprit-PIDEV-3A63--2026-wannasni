<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Entity\LoyaltyReward;
use App\Service\LoyaltyService;
use App\Repository\LoyaltyPointRepository;
use App\Repository\LoyaltyRewardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/loyalty', requirements: ['_locale' => 'fr|en|ar'])]
class LoyaltyController extends AbstractController
{
    public function __construct(
        private LoyaltyService $loyaltyService,
        private LoyaltyPointRepository $pointRepo,
        private LoyaltyRewardRepository $rewardRepo,
    ) {}

    // ─── Dashboard Fidélité ─────────────────────────────────────────────

    #[Route('/', name: 'app_loyalty')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $dashboardData = $this->loyaltyService->getDashboardData($user);

        // Get reward catalog (what user can exchange + upcoming rewards)
        $rewardCatalog = $this->loyaltyService->getRewardCatalog($user);

        return $this->render('front/loyalty/index.html.twig', [
            'data' => $dashboardData,
            'catalog' => $rewardCatalog,
        ]);
    }

    // ─── Historique des Points ──────────────────────────────────────────

    #[Route('/history', name: 'app_loyalty_history')]
    public function history(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $history = $this->pointRepo->getHistory($user, 50);
        $totalPoints = $this->pointRepo->getTotalPoints($user);

        return $this->render('front/loyalty/history.html.twig', [
            'history' => $history,
            'totalPoints' => $totalPoints,
        ]);
    }

    // ─── Récompenses Disponibles ────────────────────────────────────────

    #[Route('/rewards', name: 'app_loyalty_rewards')]
    public function rewards(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $availableRewards = $this->rewardRepo->findAvailableForSenior($user);
        $redeemedRewards = $this->rewardRepo->findRedeemedForSenior($user);
        $totalPoints = $this->pointRepo->getTotalPoints($user);

        return $this->render('front/loyalty/rewards.html.twig', [
            'availableRewards' => $availableRewards,
            'redeemedRewards' => $redeemedRewards,
            'totalPoints' => $totalPoints,
        ]);
    }

    // ─── Échanger une Récompense ────────────────────────────────────────

    #[Route('/redeem/{id}', name: 'app_loyalty_redeem', methods: ['POST'])]
    public function redeem(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $reward = $this->rewardRepo->find($id);
        if (!$reward || $reward->getSenior()->getId() !== $user->getId()) {
            $this->addFlash('error', 'Récompense introuvable.');
            return $this->redirectToRoute('app_loyalty_rewards');
        }

        $success = $this->loyaltyService->redeemReward($user, $reward);

        if ($success) {
            $this->addFlash('success', '🎉 Récompense échangée avec succès : ' . $reward->getTitle());
        } else {
            $this->addFlash('error', 'Impossible d\'échanger cette récompense. Vérifiez votre solde de points.');
        }

        return $this->redirectToRoute('app_loyalty_rewards');
    }

    // ─── Générer une Récompense ML ──────────────────────────────────────

    #[Route('/generate-reward', name: 'app_loyalty_generate', methods: ['POST'])]
    public function generateReward(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $reward = $this->loyaltyService->generatePersonalizedReward($user);

        if ($reward) {
            return new JsonResponse([
                'success' => true,
                'reward' => [
                    'id' => $reward->getId(),
                    'type' => $reward->getType(),
                    'title' => $reward->getTitle(),
                    'description' => $reward->getDescription(),
                    'pointsCost' => $reward->getPointsCost(),
                    'confidence' => $reward->getMlConfidence(),
                ],
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => 'Impossible de générer une récompense pour le moment.',
        ]);
    }
}
