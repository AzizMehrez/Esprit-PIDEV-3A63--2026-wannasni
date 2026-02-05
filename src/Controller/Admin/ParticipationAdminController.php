<?php

namespace App\Controller\Admin;

use App\Entity\Participation;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/participations')]
class ParticipationAdminController extends AbstractController
{
    public function __construct(
        private ParticipationRepository $participationRepository,
        private ActivityRepository $activityRepository
    ) {
    }

    /**
     * List all participations with filters
     */
    #[Route('/', name: 'admin_participations', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $activityId = $request->query->get('activity_id');
        $status = $request->query->get('status', '');
        $participantId = $request->query->get('participant_id', '');

        $participations = [];
        $activity = null;

        // If filtering by activity
        if ($activityId) {
            $activity = $this->activityRepository->find($activityId);
            if ($activity) {
                $participations = $this->participationRepository->findByActivityId($activityId, $status ?: null);
            }
        } else {
            // Get all participations with optional status filter
            $qb = $this->participationRepository->createQueryBuilder('p');
            
            if ($status) {
                $qb->where('p.status = :status')
                   ->setParameter('status', $status);
            }
            
            if ($participantId) {
                $qb->andWhere('p.participantId = :participantId OR p.seniorId = :participantId')
                   ->setParameter('participantId', $participantId);
            }

            $participations = $qb->orderBy('p.registrationDate', 'DESC')
                                 ->getQuery()
                                 ->getResult();
        }

        // Get all activities for the filter dropdown
        $allActivities = $this->activityRepository->findAll();

        return $this->render('admin/participations/index.html.twig', [
            'participations' => $participations,
            'activity' => $activity,
            'activities' => $allActivities,
            'search_activity_id' => $activityId,
            'search_status' => $status,
            'search_participant_id' => $participantId,
        ]);
    }

    /**
     * Show participation details
     */
    #[Route('/{id}', name: 'admin_participation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $activity = $this->activityRepository->find($participation->getActivityId());

        return $this->render('admin/participations/show.html.twig', [
            'participation' => $participation,
            'activity' => $activity,
        ]);
    }

    /**
     * Edit participation
     */
    #[Route('/{id}/edit', name: 'admin_participation_edit', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function edit(int $id): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $activity = $this->activityRepository->find($participation->getActivityId());

        return $this->render('admin/participations/edit.html.twig', [
            'participation' => $participation,
            'activity' => $activity,
        ]);
    }

    /**
     * Update participation
     */
    #[Route('/{id}', name: 'admin_participation_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $participation->setStatus($request->request->get('status'));
        $participation->setRegistrationMethod($request->request->get('registration_method'));
        $participation->setFeedbackRating($request->request->get('feedback_rating') ? (int) $request->request->get('feedback_rating') : null);
        $participation->setFeedbackComment($request->request->get('feedback_comment'));
        $participation->setMoodBefore($request->request->get('mood_before') ? (int) $request->request->get('mood_before') : null);
        $participation->setMoodAfter($request->request->get('mood_after') ? (int) $request->request->get('mood_after') : null);
        $participation->setProblemsEncountered($request->request->get('problems_encountered'));
        $participation->setRecommendToFriends($request->request->getBoolean('recommend_to_friends', false));
        $participation->setShareWithFamily($request->request->get('share_with_family'));
        $participation->setHasCertificate($request->request->getBoolean('has_certificate', false));

        // Update presence confirmation if status changed to present
        if ($request->request->get('status') === 'présent' && !$participation->getPresenceConfirmationDate()) {
            $participation->setPresenceConfirmationDate(new \DateTime());
        }

        $this->participationRepository->save($participation, true);

        $this->addFlash('success', 'Participation updated successfully!');

        return $this->redirectToRoute('admin_participation_show', ['id' => $participation->getId()]);
    }

    /**
     * Mark attendance
     */
    #[Route('/{id}/attendance', name: 'admin_participation_attendance', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markAttendance(int $id, Request $request): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $status = $request->request->get('attendance_status');
        $statusMap = [
            'present' => 'présent',
            'absent_excused' => 'absent_excusé',
            'absent_not_excused' => 'absent_non_excusé',
        ];

        $participation->setStatus($statusMap[$status] ?? $status);
        $participation->setPresenceConfirmationDate(new \DateTime());

        $this->participationRepository->save($participation, true);

        $this->addFlash('success', 'Attendance marked as ' . ($statusMap[$status] ?? $status));

        return $this->redirectToRoute('admin_participation_show', ['id' => $participation->getId()]);
    }

    /**
     * Delete participation
     */
    #[Route('/{id}/delete', name: 'admin_participation_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $activityId = $participation->getActivityId();
        $activity = $this->activityRepository->find($activityId);

        // Decrement participant count
        if ($activity) {
            $activity->setCurrentParticipants(max(0, $activity->getCurrentParticipants() - 1));
            $this->activityRepository->save($activity, true);
        }

        $this->participationRepository->remove($participation, true);

        $this->addFlash('success', 'Participation deleted successfully!');

        return $this->redirectToRoute('admin_participations', ['activity_id' => $activityId]);
    }

    /**
     * Generate certificate for participation
     */
    #[Route('/{id}/certificate', name: 'admin_participation_certificate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function generateCertificate(int $id): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $participation->setHasCertificate(true);
        $this->participationRepository->save($participation, true);

        $this->addFlash('success', 'Certificate marked as issued!');

        return $this->redirectToRoute('admin_participation_show', ['id' => $participation->getId()]);
    }

    /**
     * Export participations as CSV
     */
    #[Route('/export/csv', name: 'admin_participations_export_csv', methods: ['GET'])]
    public function exportCsv(Request $request): Response
    {
        $activityId = $request->query->get('activity_id');
        $status = $request->query->get('status', '');

        $participations = [];
        if ($activityId) {
            $participations = $this->participationRepository->findByActivityId($activityId, $status ?: null);
        }

        $activity = $activityId ? $this->activityRepository->find($activityId) : null;

        $filename = $activity ? 'participations_' . $activity->getId() . '.csv' : 'participations_all.csv';
        $response = new Response();
        $response->setContent($this->generateCsv($participations));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    /**
     * Generate CSV content from participations
     */
    private function generateCsv(array $participations): string
    {
        $output = fopen('php://memory', 'r+');
        
        // Header row
        fputcsv($output, [
            'ID',
            'Activity ID',
            'Participant ID',
            'Status',
            'Registration Date',
            'Registration Method',
            'Feedback Rating',
            'Feedback Comment',
            'Mood Before',
            'Mood After',
            'Problems',
            'Recommend',
            'Share with Family',
            'Has Certificate',
        ]);

        // Data rows
        foreach ($participations as $p) {
            fputcsv($output, [
                $p->getId(),
                $p->getActivityId(),
                $p->getParticipantId() ?? $p->getSeniorId(),
                $p->getStatus(),
                $p->getRegistrationDate()?->format('Y-m-d H:i:s'),
                $p->getRegistrationMethod(),
                $p->getFeedbackRating(),
                $p->getFeedbackComment(),
                $p->getMoodBefore(),
                $p->getMoodAfter(),
                $p->getProblemsEncountered(),
                $p->getRecommendToFriends() ? 'Yes' : 'No',
                $p->getShareWithFamily(),
                $p->getHasCertificate() ? 'Yes' : 'No',
            ]);
        }

        rewind($output);
        return stream_get_contents($output);
    }
}
