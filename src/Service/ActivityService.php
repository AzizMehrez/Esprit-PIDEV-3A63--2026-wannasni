<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Participation;
use App\Exception\ValidationException;
use App\Exception\BusinessRuleException;
use App\Exception\UnauthorizedException;

/**
 * ActivityService - Business logic for activities and events
 */
class ActivityService
{
    private const VALID_TYPES = ['social', 'physical', 'cultural', 'educational'];

    /**
     * Create activity (coaches and admins only)
     */
    public function createActivity(array $data, int $creatorId, array $creatorRoles): Activity
    {
        // Security check
        if (!in_array('ROLE_COACH', $creatorRoles) && !in_array('ROLE_ADMIN', $creatorRoles)) {
            throw new UnauthorizedException('Only coaches can create activities');
        }

        // Validation
        if (empty($data['title'])) {
            throw new ValidationException('Title is required');
        }

        if (empty($data['start_time'])) {
            throw new ValidationException('Start time is required');
        }

        $type = $data['type'] ?? 'social';
        if (!in_array($type, self::VALID_TYPES)) {
            throw new ValidationException('Invalid activity type. Valid options: ' . implode(', ', self::VALID_TYPES));
        }

        if (isset($data['max_participants']) && $data['max_participants'] < 1) {
            throw new ValidationException('Max participants must be at least 1');
        }

        $activity = new Activity();
        $activity->setId(rand(1, 10000));
        $activity->setTitle($data['title']);
        $activity->setDescription($data['description'] ?? '');
        $activity->setType($type);
        $activity->setStartTime(new \DateTime($data['start_time']));
        $activity->setEndTime(new \DateTime($data['end_time'] ?? $data['start_time']));
        $activity->setLocation($data['location'] ?? '');
        $activity->setMaxParticipants($data['max_participants'] ?? null);
        $activity->setCoachId($creatorId);
        $activity->setIsActive(true);

        return $activity;
    }

    /**
     * Get all upcoming activities
     */
    public function getUpcomingActivities(): array
    {
        // Mock: Return sample activities
        $activities = [];

        $activity1 = new Activity();
        $activity1->setId(1);
        $activity1->setTitle('Yoga doux');
        $activity1->setType('physical');
        $activity1->setLocation('Salle de sport');
        $activity1->setStartTime(new \DateTime('+1 day 10:00'));
        $activity1->setMaxParticipants(15);
        $activity1->setCurrentParticipants(8);
        $activities[] = $this->activityToArray($activity1);

        $activity2 = new Activity();
        $activity2->setId(2);
        $activity2->setTitle('Atelier cuisine');
        $activity2->setType('social');
        $activity2->setLocation('Centre social');
        $activity2->setStartTime(new \DateTime('+3 days 14:00'));
        $activity2->setMaxParticipants(10);
        $activity2->setCurrentParticipants(6);
        $activities[] = $this->activityToArray($activity2);

        return $activities;
    }

    /**
     * Register senior for activity
     */
    public function registerForActivity(int $activityId, int $seniorId): Participation
    {
        $activity = $this->findActivityById($activityId);

        // Business rule: Check if activity is active
        if (!$activity->isActive()) {
            throw new BusinessRuleException('Activity is not active');
        }

        // Business rule: Check capacity
        if ($activity->isFull()) {
            throw new BusinessRuleException('Activity is full');
        }

        // Business rule: Check if already registered
        if ($this->isAlreadyRegistered($activityId, $seniorId)) {
            throw new BusinessRuleException('Already registered for this activity');
        }

        // Business rule: Cannot register for past activities
        if ($activity->getStartTime() <= new \DateTime()) {
            throw new BusinessRuleException('Cannot register for past activities');
        }

        $participation = new Participation();
        $participation->setId(rand(1, 10000));
        $participation->setActivityId($activityId);
        $participation->setSeniorId($seniorId);
        $participation->setStatus('registered');

        // Update participant count
        $activity->setCurrentParticipants($activity->getCurrentParticipants() + 1);

        return $participation;
    }

    /**
     * Cancel participation
     */
    public function cancelParticipation(int $participationId, int $seniorId): void
    {
        $participation = $this->findParticipationById($participationId);

        // Security: Only the registered senior can cancel
        if ($participation->getSeniorId() !== $seniorId) {
            throw new UnauthorizedException('Cannot cancel someone else\'s participation');
        }

        $activity = $this->findActivityById($participation->getActivityId());

        // Business rule: Cannot cancel if activity already started
        if ($activity->getStartTime() <= new \DateTime()) {
            throw new BusinessRuleException('Cannot cancel after activity has started');
        }

        $participation->setStatus('cancelled');

        // Update participant count
        $activity->setCurrentParticipants($activity->getCurrentParticipants() - 1);
    }

    /**
     * Submit feedback after activity
     */
    public function submitFeedback(int $participationId, int $rating, ?string $feedback): Participation
    {
        $participation = $this->findParticipationById($participationId);

        // Business rule: Can only rate attended activities
        if ($participation->getStatus() !== 'attended') {
            throw new BusinessRuleException('Can only rate attended activities');
        }

        // Validation
        if ($rating < 1 || $rating > 5) {
            throw new ValidationException('Rating must be between 1 and 5');
        }

        $participation->setRating($rating);
        $participation->setFeedback($feedback);

        return $participation;
    }

    /**
     * Get user's participations
     */
    public function getUserParticipations(int $seniorId): array
    {
        // Mock: Return sample participations
        $participation = new Participation();
        $participation->setId(1);
        $participation->setActivityId(1);
        $participation->setSeniorId($seniorId);
        $participation->setStatus('registered');

        return [[
            'id' => $participation->getId(),
            'activity_id' => $participation->getActivityId(),
            'status' => $participation->getStatus(),
            'registered_at' => $participation->getRegisteredAt()?->format('Y-m-d H:i:s'),
        ]];
    }

    /**
     * Convert activity to array
     */
    private function activityToArray(Activity $activity): array
    {
        return [
            'id' => $activity->getId(),
            'title' => $activity->getTitle(),
            'description' => $activity->getDescription(),
            'type' => $activity->getType(),
            'start_time' => $activity->getStartTime()?->format('Y-m-d H:i'),
            'end_time' => $activity->getEndTime()?->format('Y-m-d H:i'),
            'location' => $activity->getLocation(),
            'max_participants' => $activity->getMaxParticipants(),
            'current_participants' => $activity->getCurrentParticipants(),
            'is_full' => $activity->isFull(),
        ];
    }

    // Mock methods
    private function findActivityById(int $id): Activity
    {
        $activity = new Activity();
        $activity->setId($id);
        $activity->setTitle('Sample Activity');
        $activity->setStartTime(new \DateTime('+1 day'));
        $activity->setMaxParticipants(15);
        $activity->setCurrentParticipants(5);
        $activity->setIsActive(true);
        return $activity;
    }

    private function findParticipationById(int $id): Participation
    {
        $participation = new Participation();
        $participation->setId($id);
        $participation->setActivityId(1);
        $participation->setSeniorId(1);
        $participation->setStatus('attended');
        return $participation;
    }

    private function isAlreadyRegistered(int $activityId, int $seniorId): bool
    {
        return false; // Mock
    }
}
