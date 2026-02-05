<?php

namespace App\Controller\Front;

use App\Entity\Participation;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-activities', requirements: ['_locale' => 'fr|en|ar'])]
class UserActivityController extends AbstractController
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ParticipationRepository $participationRepository
    ) {
    }

    #[Route('/', name: 'app_my_activities')]
    public function index(): Response
    {
        // For demo: use a mock user ID (in production, get from logged-in user)
        $userId = 1;

        // Get all active activities from database
        $allActivities = $this->activityRepository->findUpcoming();

        // Get user's enrolled activities
        $enrolledParticipations = $this->participationRepository->findActiveByUser($userId);
        $enrolledActivityIds = array_map(fn($p) => $p->getActivityId(), $enrolledParticipations);

        $enrolledActivities = [];
        $availableActivities = [];

        foreach ($allActivities as $activity) {
            $activityData = [
                'id' => $activity->getId(),
                'name' => $activity->getTitle(),
                'title' => $activity->getTitle(),
                'type' => $activity->getType(),
                'location' => $activity->getLocation(),
                'startTime' => $activity->getStartTime(),
                'description' => $activity->getDescription(),
                'schedule' => $activity->getStartTime()?->format('D M d, Y H:i') ?? 'TBA',
                'nextSession' => $activity->getStartTime(),
                'participants' => $activity->getCurrentParticipants(),
                'maxParticipants' => $activity->getMaxParticipants(),
                'isActive' => $activity->isActive(),
            ];

            // Separate enrolled from available
            if (in_array($activity->getId(), $enrolledActivityIds)) {
                $enrolledActivities[] = $activityData;
            } else {
                $availableActivities[] = $activityData;
            }
        }

        return $this->render('front/activities/index.html.twig', [
            'enrolled_activities' => $enrolledActivities,
            'available_activities' => $availableActivities,
        ]);
    }

    #[Route('/enroll/{id}', name: 'app_enroll_activity', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function enrollActivity(int $id): Response
    {
        // For demo: use a mock user ID (in production, get from logged-in user)
        $userId = 1;

        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            $this->addFlash('error', 'Activity not found!');
            return $this->redirectToRoute('app_my_activities');
        }

        // Check if already enrolled
        $existing = $this->participationRepository->findByUserAndActivity($userId, $id);
        if ($existing) {
            $this->addFlash('error', 'You are already enrolled in this activity!');
            return $this->redirectToRoute('app_my_activities');
        }

        // Check if activity is full
        if ($activity->getMaxParticipants() && $activity->getCurrentParticipants() >= $activity->getMaxParticipants()) {
            $this->addFlash('error', 'This activity is at full capacity!');
            return $this->redirectToRoute('app_my_activities');
        }

        // Create participation record
        $participation = new Participation();
        $participation->setParticipantId($userId);
        $participation->setSeniorId($userId); // legacy support
        $participation->setActivityId($id);
        $participation->setStatus('inscrit');
        $participation->setRegistrationMethod('appli');
        $this->participationRepository->save($participation, true);

        // Increment participant count
        $activity->setCurrentParticipants($activity->getCurrentParticipants() + 1);
        $this->activityRepository->save($activity, true);

        $this->addFlash('success', 'Successfully enrolled in ' . $activity->getTitle() . '!');
        return $this->redirectToRoute('app_my_activities');
    }

    #[Route('/cancel/{id}', name: 'app_cancel_activity', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelActivity(int $id): Response
    {
        // For demo: use a mock user ID (in production, get from logged-in user)
        $userId = 1;

        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            $this->addFlash('error', 'Activity not found!');
            return $this->redirectToRoute('app_my_activities');
        }

        // Find participation record
        $participation = $this->participationRepository->findByUserAndActivity($userId, $id);
        if (!$participation) {
            $this->addFlash('error', 'You are not enrolled in this activity!');
            return $this->redirectToRoute('app_my_activities');
        }

        // Mark as cancelled instead of deleting - preserves feedback history
        $participation->setStatus('annulé');
        $this->participationRepository->save($participation, true);

        // Decrement participant count only if they were marked as present or registered
        if ($participation->getStatus() !== 'annulé') {
            $activity->setCurrentParticipants(max(0, $activity->getCurrentParticipants() - 1));
            $this->activityRepository->save($activity, true);
        }

        $this->addFlash('success', 'Your enrollment in ' . $activity->getTitle() . ' has been cancelled. Your feedback remains in your history.');
        return $this->redirectToRoute('app_my_activities');
    }
}
