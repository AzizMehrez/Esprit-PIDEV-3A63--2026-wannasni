<?php

namespace App\Controller\Api;

use App\Service\ActivityService;
use App\Exception\ValidationException;
use App\Exception\BusinessRuleException;
use App\Exception\UnauthorizedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ActivityController - API endpoints for activities and events
 */
#[Route('/api/activities')]
class ActivityController extends AbstractController
{
    public function __construct(
        private ActivityService $activityService
    ) {}

    /**
     * Create new activity (coaches only)
     */
    #[Route('', name: 'api_activity_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = 1; // Mock user ID
            $roles = ['ROLE_COACH']; // Mock roles

            $activity = $this->activityService->createActivity($data, $userId, $roles);

            return $this->json([
                'success' => true,
                'activity' => [
                    'id' => $activity->getId(),
                    'title' => $activity->getTitle(),
                    'type' => $activity->getType(),
                ]
            ], 201);

        } catch (ValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ], 422);

        } catch (UnauthorizedException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => $e->getMessage()]
            ], 403);
        }
    }

    /**
     * Get upcoming activities
     */
    #[Route('', name: 'api_activities_list', methods: ['GET'])]
    public function getUpcoming(): JsonResponse
    {
        $activities = $this->activityService->getUpcomingActivities();

        return $this->json([
            'success' => true,
            'activities' => $activities
        ]);
    }

    /**
     * Register for activity
     */
    #[Route('/{id}/register', name: 'api_activity_register', methods: ['POST'])]
    public function register(int $id): JsonResponse
    {
        try {
            $userId = 1; // Mock user ID

            $participation = $this->activityService->registerForActivity($id, $userId);

            return $this->json([
                'success' => true,
                'participation' => [
                    'id' => $participation->getId(),
                    'status' => $participation->getStatus(),
                ]
            ], 201);

        } catch (BusinessRuleException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'BUSINESS_RULE', 'message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Cancel participation
     */
    #[Route('/participations/{id}/cancel', name: 'api_participation_cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        try {
            $userId = 1; // Mock user ID

            $this->activityService->cancelParticipation($id, $userId);

            return $this->json([
                'success' => true,
                'message' => 'Participation cancelled'
            ]);

        } catch (BusinessRuleException | UnauthorizedException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Submit feedback for attended activity
     */
    #[Route('/participations/{id}/feedback', name: 'api_participation_feedback', methods: ['POST'])]
    public function submitFeedback(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $participation = $this->activityService->submitFeedback(
                $id,
                $data['rating'] ?? 0,
                $data['feedback'] ?? null
            );

            return $this->json([
                'success' => true,
                'rating' => $participation->getRating()
            ]);

        } catch (ValidationException | BusinessRuleException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Get user's participations
     */
    #[Route('/participations', name: 'api_participations_list', methods: ['GET'])]
    public function getParticipations(): JsonResponse
    {
        $userId = 1; // Mock user ID

        $participations = $this->activityService->getUserParticipations($userId);

        return $this->json([
            'success' => true,
            'participations' => $participations
        ]);
    }
}
