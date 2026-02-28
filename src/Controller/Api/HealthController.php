<?php

namespace App\Controller\Api;

use App\Service\HealthService;
use App\Exception\ValidationException;
use App\Exception\UnauthorizedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * HealthController - API endpoints for health data management
 * Handles sensitive health data with strict access control
 */
#[Route('/api/health')]
class HealthController extends AbstractController
{
    public function __construct(
        private HealthService $healthService
    ) {}

    /**
     * Add health vitals entry
     */
    #[Route('/vitals', name: 'api_health_add', methods: ['POST'])]
    public function addVitals(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = 1; // Mock user ID
            $roles = ['ROLE_SENIOR']; // Mock roles

            $seniorId = $data['senior_id'] ?? $userId;

            $journal = $this->healthService->addHealthData($seniorId, $data, $userId, $roles);

            return $this->json([
                'success' => true,
                'entry' => [
                    'id' => $journal->getId(),
                    'date' => $journal->getDate()?->format('Y-m-d'),
                    'tension_arterielle' => $journal->getTensionArterielle(),
                    'temperature' => $journal->getTemperature(),
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
     * Get health history
     */
    #[Route('/history/{seniorId}', name: 'api_health_history', methods: ['GET'])]
    public function getHistory(int $seniorId): JsonResponse
    {
        try {
            $userId = 1; // Mock user ID
            $roles = ['ROLE_SENIOR']; // Mock roles

            $history = $this->healthService->getHealthHistory($seniorId, $userId, $roles);

            return $this->json([
                'success' => true,
                'entries' => $history
            ]);

        } catch (UnauthorizedException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => $e->getMessage()]
            ], 403);
        }
    }

    /**
     * Prescribe treatment (doctors only)
     */
    #[Route('/treatments', name: 'api_treatment_prescribe', methods: ['POST'])]
    public function prescribeTreatment(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $doctorId = 1; // Mock doctor ID
            $roles = ['ROLE_DOCTOR']; // Mock roles

            $seniorId = $data['senior_id'] ?? 0;

            $treatment = $this->healthService->prescribeTreatment($seniorId, $data, $doctorId, $roles);

            return $this->json([
                'success' => true,
                'treatment' => [
                    'id' => $treatment->getId(),
                    'medicaments' => $treatment->getMedicaments(),
                    'posologie' => $treatment->getPosologie(),
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
     * Get active treatments
     */
    #[Route('/treatments/{seniorId}', name: 'api_treatments_list', methods: ['GET'])]
    public function getTreatments(int $seniorId): JsonResponse
    {
        try {
            $userId = 1; // Mock user ID
            $roles = ['ROLE_SENIOR']; // Mock roles

            $treatments = $this->healthService->getActiveTreatments($seniorId, $userId, $roles);

            return $this->json([
                'success' => true,
                'treatments' => $treatments
            ]);

        } catch (UnauthorizedException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => $e->getMessage()]
            ], 403);
        }
    }
}
