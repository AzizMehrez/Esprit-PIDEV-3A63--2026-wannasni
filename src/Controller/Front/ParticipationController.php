<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Entity\LoyaltyPoint;
use App\Repository\ParticipationRepository;
use App\Service\ActivityService;
use App\Service\LoyaltyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{_locale}/participations', requirements: ['_locale' => 'fr|en|ar'])]
class ParticipationController extends AbstractController
{
    public function __construct(
        private ParticipationRepository $participationRepository,
        private ActivityService $activityService,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private EntityManagerInterface $entityManager,
        private LoyaltyService $loyaltyService,
    ) {
    }

    #[Route('/{id}', name: 'app_participation_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        // Check if the logged-in user owns this participation
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User || $participation->getSeniorId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot view this participation');
        }

        return $this->render('front/participations/show.html.twig', [
            'participation' => $participation,
        ]);
    }

    #[Route('/history', name: 'app_participation_history')]
    public function history(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $participations = $this->participationRepository->findBySeniorId($user->getId());

        return $this->render('front/participations/history.html.twig', [
            'participations' => $participations,
        ]);
    }

    #[Route('/stats', name: 'app_participation_stats')]
    public function stats(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $participations = $this->participationRepository->findBySeniorId($user->getId());

        $stats = [
            'total' => count($participations),
            'inscrit' => 0,
            'présent' => 0,
            'annulé' => 0,
        ];

        foreach ($participations as $participation) {
            $status = $participation->getStatus();
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $this->render('front/participations/stats.html.twig', [
            'participations' => $participations,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/feedback', name: 'app_participation_feedback', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function submitFeedback(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté');
            return $this->redirectToRoute('app_login');
        }

        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        // Check if the logged-in user owns this participation
        if ($participation->getSeniorId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You cannot submit feedback for this participation');
        }

        // Validate CSRF token
        $token = new CsrfToken('feedback_' . $id, $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Token de sécurité invalide');
            return $this->redirectToRoute('app_participation_show', [
                'id' => $id,
                '_locale' => $request->getLocale()
            ]);
        }

        try {
            $rating = (int) $request->request->get('rating');
            $comment = $request->request->get('comment');
            $moodBefore = $request->request->get('mood_before') ? (int) $request->request->get('mood_before') : null;
            $moodAfter = $request->request->get('mood_after') ? (int) $request->request->get('mood_after') : null;
            $problemsEncountered = $request->request->get('problems_encountered');
            $recommendToFriends = $request->request->get('recommend_to_friends') ? true : false;
            $shareWithFamily = $request->request->get('share_with_family');

            // Update the participation directly with new fields
            $participation->setFeedbackRating($rating);
            $participation->setFeedbackComment($comment);
            $participation->setMoodBefore($moodBefore);
            $participation->setMoodAfter($moodAfter);
            $participation->setProblemsEncountered($problemsEncountered);
            $participation->setRecommendToFriends($recommendToFriends);
            $participation->setShareWithFamily($shareWithFamily);

            // Use entity manager to save changes
            $this->entityManager->flush();

            // ── Auto-award loyalty points for activity feedback ──
            try {
                $this->loyaltyService->awardBonusPoints(
                    $user,
                    20,
                    LoyaltyPoint::SOURCE_ACTIVITY,
                    $participation->getId(),
                    sprintf('Points activité : feedback pour participation #%d', $participation->getId())
                );
            } catch (\Exception $e) {
                // Loyalty error should not block feedback submission
            }

            $this->addFlash('success', 'Merci pour votre retour d\'expérience complet !');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_participation_show', [
            'id' => $id,
            '_locale' => $request->getLocale()
        ]);
    }
}
