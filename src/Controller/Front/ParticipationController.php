<?php

namespace App\Controller\Front;

use App\Entity\Participation;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/participations', requirements: ['_locale' => 'fr|en|ar'])]
class ParticipationController extends AbstractController
{
    public function __construct(
        private ParticipationRepository $participationRepository,
        private ActivityRepository $activityRepository
    ) {
    }

    /**
     * View participation history for the logged-in user
     */
    #[Route('/history', name: 'app_participation_history')]
    public function history(): Response
    {
        $userId = 1; // Mock user ID - in production, get from authenticated user

        $participations = $this->participationRepository->findByParticipantId($userId);

        $enrichedParticipations = [];
        foreach ($participations as $participation) {
            $activity = $this->activityRepository->find($participation->getActivityId());
            $enrichedParticipations[] = [
                'participation' => $participation,
                'activity' => $activity,
            ];
        }

        return $this->render('front/participations/history.html.twig', [
            'participations' => $enrichedParticipations,
        ]);
    }

    /**
     * View participation details
     */
    #[Route('/{id}', name: 'app_participation_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found!');
        }

        $activity = $this->activityRepository->find($participation->getActivityId());

        return $this->render('front/participations/show.html.twig', [
            'participation' => $participation,
            'activity' => $activity,
        ]);
    }

    /**
     * Submit feedback for a participation
     */
    #[Route('/{id}/feedback', name: 'app_participation_feedback', methods: ['POST'])]
    public function submitFeedback(int $id, Request $request): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            $this->addFlash('error', 'Participation not found!');
            return $this->redirectToRoute('app_participation_history');
        }

        // Collect feedback data from form
        $participation->setFeedbackRating((int) $request->request->get('rating', 0) ?: null);
        $participation->setFeedbackComment($request->request->get('comment', ''));
        $participation->setMoodBefore((int) $request->request->get('mood_before', 0) ?: null);
        $participation->setMoodAfter((int) $request->request->get('mood_after', 0) ?: null);
        $participation->setProblemsEncountered($request->request->get('problems', ''));
        $participation->setRecommendToFriends($request->request->getBoolean('recommend', false));
        $participation->setShareWithFamily($request->request->get('share', 'non'));

        $this->participationRepository->save($participation, true);

        $this->addFlash('success', 'Thank you for your feedback!');
        return $this->redirectToRoute('app_participation_show', ['id' => $participation->getId()]);
    }

    /**
     * Mark attendance (admin or coach only in production)
     */
    #[Route('/{id}/attendance/{status}', name: 'app_participation_attendance', requirements: ['id' => '\d+', 'status' => 'present|absent_excused|absent_not_excused'])]
    public function markAttendance(int $id, string $status): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            $this->addFlash('error', 'Participation not found!');
            return $this->redirectToRoute('app_participation_history');
        }

        $statusMap = [
            'present' => 'présent',
            'absent_excused' => 'absent_excusé',
            'absent_not_excused' => 'absent_non_excusé',
        ];

        $participation->setStatus($statusMap[$status] ?? $status);
        $participation->setPresenceConfirmationDate(new \DateTime());

        $this->participationRepository->save($participation, true);

        $this->addFlash('success', 'Attendance marked!');
        return $this->redirectToRoute('app_participation_show', ['id' => $participation->getId()]);
    }

    /**
     * Get activity statistics (for admin/coaches)
     */
    #[Route('/activity/{activityId}/stats', name: 'app_participation_stats', requirements: ['activityId' => '\d+'])]
    public function activityStats(int $activityId): Response
    {
        $activity = $this->activityRepository->find($activityId);

        if (!$activity) {
            throw $this->createNotFoundException('Activity not found!');
        }

        $participations = $this->participationRepository->findByActivityId($activityId);
        $withFeedback = $this->participationRepository->findWithFeedback($activityId);
        $averageRating = $this->participationRepository->getAverageRating($activityId);
        $statusCounts = $this->participationRepository->countByStatus($activityId);

        return $this->render('front/participations/stats.html.twig', [
            'activity' => $activity,
            'participations' => $participations,
            'withFeedback' => $withFeedback,
            'averageRating' => $averageRating,
            'statusCounts' => $statusCounts,
        ]);
    }
}
