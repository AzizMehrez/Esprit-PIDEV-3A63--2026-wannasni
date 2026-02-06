<?php

namespace App\Controller\Admin;

use App\Entity\Participation;
use App\Repository\ParticipationRepository;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/participations')]
class ParticipationAdminController extends AbstractController
{
    public function __construct(
        private ParticipationRepository $participationRepository,
        private ActivityRepository $activityRepository,
        private EntityManagerInterface $em
    ) {
    }

    #[Route('/', name: 'admin_participations')]
    public function index(Request $request): Response
    {
        $activityId = $request->query->get('activity_id');
        $status = $request->query->get('status');
        $participantId = $request->query->get('participant_id');

        if ($activityId || $status || $participantId) {
            $participations = $this->participationRepository->search(
                $activityId ? (int)$activityId : null,
                $status,
                $participantId ? (int)$participantId : null
            );
        } else {
            $participations = $this->participationRepository->findAllWithActivity();
        }

        $activities = $this->activityRepository->findAll();

        return $this->render('admin/participations/index.html.twig', [
            'participations' => $participations,
            'activities' => $activities,
            'search_activity_id' => $activityId,
            'search_status' => $status,
            'search_participant_id' => $participantId,
        ]);
    }

    #[Route('/{id}', name: 'admin_participation_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $participation = $this->participationRepository->findWithActivity($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        return $this->render('admin/participations/show.html.twig', [
            'participation' => $participation,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_participation_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        $participation = $this->participationRepository->findWithActivity($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        if ($request->isMethod('POST')) {
            $participation->setStatus($request->request->get('status'));
            
            $rating = $request->request->get('feedback_rating');
            if ($rating) {
                $participation->setFeedbackRating((int)$rating);
            }

            $comment = $request->request->get('feedback_comment');
            if ($comment) {
                $participation->setFeedbackComment($comment);
            }

            $this->em->flush();

            $this->addFlash('success', 'Participation updated successfully!');

            return $this->redirectToRoute('admin_participations');
        }

        return $this->render('admin/participations/edit.html.twig', [
            'participation' => $participation,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_participation_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $participation = $this->participationRepository->find($id);

        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $this->em->remove($participation);
        $this->em->flush();

        $this->addFlash('success', 'Participation deleted successfully!');

        return $this->redirectToRoute('admin_participations');
    }
}
