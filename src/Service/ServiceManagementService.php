<?php

namespace App\Service;

use App\Entity\ServiceRequest;
use App\Entity\Intervention;
use App\Exception\ValidationException;
use App\Exception\BusinessRuleException;
use App\Exception\UnauthorizedException;

/**
 * ServiceManagementService - Business logic for services and interventions
 */
class ServiceManagementService
{
    private const VALID_CATEGORIES = ['plumbing', 'groceries', 'cleaning', 'repairs', 'transport', 'other'];
    private const VALID_URGENCIES = ['low', 'normal', 'high'];
    
    private const STATUS_TRANSITIONS = [
        'requested' => ['assigned'],
        'assigned' => ['in_progress'],
        'in_progress' => ['completed'],
        'completed' => ['rated'],
    ];

    /**
     * Create new service request
     */
    public function createServiceRequest(int $seniorId, array $data): ServiceRequest
    {
        // Validation
        if (empty($data['category'])) {
            throw new ValidationException('Category is required');
        }

        if (!in_array($data['category'], self::VALID_CATEGORIES)) {
            throw new ValidationException('Invalid category. Valid options: ' . implode(', ', self::VALID_CATEGORIES));
        }

        if (empty($data['description'])) {
            throw new ValidationException('Description is required');
        }

        $urgency = $data['urgency'] ?? 'normal';
        if (!in_array($urgency, self::VALID_URGENCIES)) {
            throw new ValidationException('Invalid urgency level');
        }

        $request = new ServiceRequest();
        $request->setId(random_int(1, 10000)); // Mock ID
        $request->setSeniorId($seniorId);
        $request->setCategory($data['category']);
        $request->setDescription($data['description']);
        $request->setUrgency($urgency);
        $request->setStatus('requested');

        return $request;
    }

    /**
     * Get all service requests for a user
     */
    public function getServiceRequests(int $userId, string $role): array
    {
        // Mock: Return sample requests
        $request = new ServiceRequest();
        $request->setId(1);
        $request->setSeniorId($userId);
        $request->setCategory('plumbing');
        $request->setDescription('Fix kitchen sink');
        $request->setUrgency('normal');
        $request->setStatus('requested');

        return [$this->toArray($request)];
    }

    /**
     * Assign technician to service request
     */
    public function assignServiceRequest(int $requestId, int $technicianId): ServiceRequest
    {
        $request = $this->findRequestById($requestId);

        // Business rule: can only assign if status is 'requested'
        if ($request->getStatus() !== 'requested') {
            throw new BusinessRuleException('Can only assign requests with status "requested"');
        }

        // Business rule: verify technician is available
        if (!$this->isTechnicianAvailable($technicianId)) {
            throw new BusinessRuleException('Technician not available');
        }

        $request->setAssignedToId($technicianId);
        $request->setStatus('assigned');

        // Create intervention
        $this->createIntervention($request, $technicianId);

        return $request;
    }

    /**
     * Update service request status with workflow validation
     */
    public function updateRequestStatus(int $requestId, string $newStatus): ServiceRequest
    {
        $request = $this->findRequestById($requestId);
        $currentStatus = $request->getStatus();

        // Validate status transition
        if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
            throw new BusinessRuleException(
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'"
            );
        }

        $request->setStatus($newStatus);

        // Business logic based on status
        if ($newStatus === 'completed') {
            $request->setCompletedAt(new \DateTime());
        }

        return $request;
    }

    /**
     * Rate completed service
     */
    public function rateService(int $requestId, int $rating, ?string $feedback): ServiceRequest
    {
        $request = $this->findRequestById($requestId);

        // Business rule: can only rate completed services
        if ($request->getStatus() !== 'completed') {
            throw new BusinessRuleException('Can only rate completed services');
        }

        // Validation
        if ($rating < 1 || $rating > 5) {
            throw new ValidationException('Rating must be between 1 and 5');
        }

        $request->setRating($rating);
        $request->setFeedback($feedback);
        $request->setStatus('rated');

        return $request;
    }

    /**
     * Validate status transition
     */
    private function isValidStatusTransition(string $from, string $to): bool
    {
        return isset(self::STATUS_TRANSITIONS[$from]) &&
               in_array($to, self::STATUS_TRANSITIONS[$from]);
    }

    /**
     * Create intervention for assigned request
     */
    private function createIntervention(ServiceRequest $request, int $technicianId): Intervention
    {
        $intervention = new Intervention();
        $intervention->setId(random_int(1, 10000));
        $intervention->setServiceRequestId($request->getId());
        $intervention->setTechnicianId($technicianId);
        $intervention->setStatus('scheduled');
        $intervention->setScheduledDate(new \DateTime('+1 day'));

        return $intervention;
    }

    /**
     * Convert request to array
     */
    private function toArray(ServiceRequest $request): array
    {
        return [
            'id' => $request->getId(),
            'senior_id' => $request->getSeniorId(),
            'category' => $request->getCategory(),
            'description' => $request->getDescription(),
            'urgency' => $request->getUrgency(),
            'status' => $request->getStatus(),
            'assigned_to' => $request->getAssignedToId(),
            'requested_at' => $request->getRequestedAt()?->format('Y-m-d H:i:s'),
            'completed_at' => $request->getCompletedAt()?->format('Y-m-d H:i:s'),
            'rating' => $request->getRating(),
        ];
    }

    // Mock methods
    private function findRequestById(int $id): ServiceRequest
    {
        $request = new ServiceRequest();
        $request->setId($id);
        $request->setSeniorId(1);
        $request->setStatus('requested');
        return $request;
    }

    private function isTechnicianAvailable(int $id): bool
    {
        return true; // Mock
    }
}
