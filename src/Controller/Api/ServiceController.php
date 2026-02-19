<?php

namespace App\Controller\Api;

use App\Service\ServiceManagementService;
use App\Exception\ValidationException;
use App\Exception\BusinessRuleException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * ServiceController - API endpoints for services and interventions
 */
#[Route('/api/services')]
class ServiceController extends AbstractController
{
    public function __construct(
        private ServiceManagementService $serviceManagement
    ) {}

    /**
     * Create new service request
     */
    #[Route('/requests', name: 'api_service_create', methods: ['POST'])]
    public function createRequest(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = 1; // Mock user ID

            $serviceRequest = $this->serviceManagement->createServiceRequest($userId, $data);

            return $this->json([
                'success' => true,
                'request' => [
                    'id' => $serviceRequest->getId(),
                    'category' => $serviceRequest->getCategory(),
                    'status' => $serviceRequest->getStatus(),
                ]
            ], 201);

        } catch (ValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ], 422);
        }
    }

    /**
     * Get all service requests
     */
    #[Route('/requests', name: 'api_service_list', methods: ['GET'])]
    public function getRequests(): JsonResponse
    {
        $userId = 1; // Mock user ID
        $role = 'ROLE_SENIOR'; // Mock role

        $requests = $this->serviceManagement->getServiceRequests($userId, $role);

        return $this->json([
            'success' => true,
            'requests' => $requests
        ]);
    }

    /**
     * Assign technician to request
     */
    #[Route('/requests/{id}/assign', name: 'api_service_assign', methods: ['POST'])]
    public function assignTechnician(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $result = $this->serviceManagement->assignServiceRequest(
                $id,
                $data['technician_id'] ?? 0
            );

            return $this->json([
                'success' => true,
                'status' => $result->getStatus()
            ]);

        } catch (BusinessRuleException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'BUSINESS_RULE', 'message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Update request status
     */
    #[Route('/requests/{id}/status', name: 'api_service_status', methods: ['PUT'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $result = $this->serviceManagement->updateRequestStatus(
                $id,
                $data['status'] ?? ''
            );

            return $this->json([
                'success' => true,
                'status' => $result->getStatus()
            ]);

        } catch (BusinessRuleException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'BUSINESS_RULE', 'message' => $e->getMessage()]
            ], 400);
        }
    }

    /**
     * Rate completed service
     */
    #[Route('/requests/{id}/rate', name: 'api_service_rate', methods: ['POST'])]
    public function rateService(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $result = $this->serviceManagement->rateService(
                $id,
                $data['rating'] ?? 0,
                $data['feedback'] ?? null
            );

            return $this->json([
                'success' => true,
                'rating' => $result->getRating()
            ]);

        } catch (ValidationException | BusinessRuleException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'ERROR', 'message' => $e->getMessage()]
            ], 400);
        }
    }
}
