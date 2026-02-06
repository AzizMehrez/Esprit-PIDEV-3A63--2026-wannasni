<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use App\Service\ActivityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
#[Route('/{_locale}/my-activities', requirements: ['_locale' => 'fr|en|ar'])]
class UserActivityController extends AbstractController
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ParticipationRepository $participationRepository,
        private ActivityService $activityService,
        private CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    #[Route('/', name: 'app_my_activities')]
    public function index(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $userId = null;
        if ($user instanceof User) {
            $userId = $user->getId();
        }

        // Get user's enrolled activities (participations)
        $enrolledActivities = [];
        if ($userId) {
            $enrolledActivities = $this->participationRepository->findBySeniorId($userId);
        }

        // Get available activities (upcoming and active)
        $availableActivities = $this->activityRepository->findUpcoming();

        return $this->render('front/activities/index.html.twig', [
            'enrolled_activities' => $enrolledActivities,
            'available_activities' => $availableActivities,
        ]);
    }

    #[Route('/{id}/enroll', name: 'app_enroll_activity', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function enroll(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour vous inscrire à une activité');
            return $this->redirectToRoute('app_login');
        }

        // Validate CSRF token
        $token = new CsrfToken('enroll_activity_' . $id, $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Token de sécurité invalide');
            return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
        }

        try {
            $this->activityService->registerForActivity($id, $user->getId());
            $this->addFlash('success', 'Inscription réussie !');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
    }

    #[Route('/{id}/cancel', name: 'app_cancel_activity', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id, Request $request): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour annuler une activité');
            return $this->redirectToRoute('app_login');
        }

        // Validate CSRF token
        $token = new CsrfToken('cancel_activity_' . $id, $request->request->get('_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            $this->addFlash('error', 'Token de sécurité invalide');
            return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
        }

        try {
            $this->activityService->cancelParticipation($id, $user->getId());
            $this->addFlash('success', 'Inscription annulée avec succès');
        } catch (\Exception $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_my_activities', ['_locale' => $request->getLocale()]);
    }
}
