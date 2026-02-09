<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Participation;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use App\Exception\ValidationException;
use App\Exception\BusinessRuleException;
use App\Exception\UnauthorizedException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ActivityService - Business logic for activities and events
 */
class ActivityService
{
    private const VALID_TYPES = ['social', 'physical', 'cultural', 'educational'];

    public function __construct(
        private EntityManagerInterface $em,
        private ActivityRepository $activityRepository,
        private ParticipationRepository $participationRepository
    ) {
    }

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
        $activity->setTitle($data['title']);
        $activity->setDescription($data['description'] ?? '');
        $activity->setType($type);
        $activity->setStartTime(new \DateTime($data['start_time']));
        $activity->setEndTime(new \DateTime($data['end_time'] ?? $data['start_time']));
        $activity->setLocation($data['location'] ?? '');
        $activity->setMaxParticipants($data['max_participants'] ?? null);
        $activity->setCoachId($creatorId);
        $activity->setIsActive(true);

        $this->em->persist($activity);
        $this->em->flush();

        return $activity;
    }

    /**
     * Get all upcoming activities
     */
    public function getUpcomingActivities(): array
    {
        $activities = $this->activityRepository->findUpcoming();
        return array_map([$this, 'activityToArray'], $activities);
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
        $participation->setActivity($activity);
        $participation->setSeniorId($seniorId);
        $participation->setStatus('inscrit');
        $participation->setRegistrationDate(new \DateTime());

        $this->em->persist($participation);

        // Update participant count
        $activity->setCurrentParticipants($activity->getCurrentParticipants() + 1);

        $this->em->flush();

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

        // Business rule: Cannot cancel if activity already started
        if ($participation->getActivity() && $participation->getActivity()->getStartTime() <= new \DateTime()) {
            throw new BusinessRuleException('Cannot cancel after activity has started');
        }

        $participation->setStatus('annulé');

        // Update participant count on the activity
        $activity = $participation->getActivity();
        if ($activity) {
            $activity->setCurrentParticipants(max(0, $activity->getCurrentParticipants() - 1));
        }

        $this->em->flush();
    }

    /**
     * Submit feedback after activity
     */
    public function submitFeedback(int $participationId, int $rating, ?string $feedback): Participation
    {
        $participation = $this->findParticipationById($participationId);

        // Business rule: Can only rate attended activities
        if ($participation->getStatus() !== 'présent' && $participation->getStatus() !== 'attended') {
            throw new BusinessRuleException('Can only rate attended activities');
        }

        // Validation
        if ($rating < 1 || $rating > 5) {
            throw new ValidationException('Rating must be between 1 and 5');
        }

        $participation->setFeedbackRating($rating);
        $participation->setFeedbackComment($feedback);

        $this->em->flush();

        return $participation;
    }

    /**
     * Get user's participations
     */
    public function getUserParticipations(int $seniorId): array
    {
        $participations = $this->participationRepository->findBySeniorId($seniorId);

        return array_map(function(Participation $p) {
            return [
                'id' => $p->getId(),
                'activity_id' => $p->getId(),
                'status' => $p->getStatus(),
                'registered_at' => $p->getRegistrationDate()?->format('Y-m-d H:i:s'),
            ];
        }, $participations);
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

    private function findActivityById(int $id): Activity
    {
        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            throw new BusinessRuleException('Activity not found');
        }
        return $activity;
    }

    private function findParticipationById(int $id): Participation
    {
        $participation = $this->participationRepository->find($id);
        if (!$participation) {
            throw new BusinessRuleException('Participation not found');
        }
        return $participation;
    }

    private function isAlreadyRegistered(int $activityId, int $seniorId): bool
    {
        return $this->participationRepository->isRegistered($activityId, $seniorId);
    }
}
