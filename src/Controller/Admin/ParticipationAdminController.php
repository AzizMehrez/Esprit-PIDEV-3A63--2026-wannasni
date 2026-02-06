<?php

namespace App\Controller\Admin;

use App\Entity\Participation;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
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
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'admin_participations')]
    public function index(): Response
    {
        $participations = $this->participationRepository->findBy([], ['registeredAt' => 'DESC']);

        // Build activity map for names
        $activities = $this->activityRepository->findAll();
        $activityMap = [];
        foreach ($activities as $activity) {
            $activityMap[$activity->getId()] = $activity->getTitle();
        }

        // Build user map from DB
        $conn = $this->em->getConnection();
        $users = $conn->executeQuery('SELECT id, first_name, last_name FROM user')->fetchAllAssociative();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[(int)$u['id']] = $u['first_name'] . ' ' . $u['last_name'];
        }

        $participationsData = [];
        foreach ($participations as $p) {
            $participationsData[] = [
                'id' => $p->getId(),
                'activityId' => $p->getActivityId(),
                'activityName' => $activityMap[$p->getActivityId()] ?? ($p->getTitle() ?? 'N/A'),
                'seniorId' => $p->getSeniorId(),
                'seniorName' => $userMap[$p->getSeniorId()] ?? 'Utilisateur #' . $p->getSeniorId(),
                'status' => $p->getStatus(),
                'registeredAt' => $p->getRegisteredAt(),
                'rating' => $p->getRating() ?? $p->getFeedbackRating(),
                'feedback' => $p->getFeedback() ?? $p->getFeedbackComment(),
                'moodBefore' => $p->getMoodBefore(),
                'moodAfter' => $p->getMoodAfter(),
                'registrationMethod' => $p->getRegistrationMethod(),
            ];
        }

        // Stats
        $stats = [
            'total' => count($participationsData),
            'registered' => count(array_filter($participationsData, fn($p) => in_array($p['status'], ['registered', 'inscrit']))),
            'attended' => count(array_filter($participationsData, fn($p) => $p['status'] === 'attended')),
            'cancelled' => count(array_filter($participationsData, fn($p) => in_array($p['status'], ['cancelled', 'annulé']))),
            'withFeedback' => count(array_filter($participationsData, fn($p) => $p['rating'] !== null)),
        ];

        return $this->render('admin/participations/index.html.twig', [
            'participations' => $participationsData,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'admin_participations_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $participation = $this->participationRepository->find($id);
        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $activity = $this->activityRepository->find($participation->getActivityId());

        // Get user name
        $conn = $this->em->getConnection();
        $user = $conn->executeQuery('SELECT first_name, last_name FROM user WHERE id = ?', [$participation->getSeniorId()])->fetchAssociative();

        $data = [
            'id' => $participation->getId(),
            'activityName' => $activity ? $activity->getTitle() : ($participation->getTitle() ?? 'N/A'),
            'activityId' => $participation->getActivityId(),
            'seniorName' => $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Utilisateur #' . $participation->getSeniorId(),
            'seniorId' => $participation->getSeniorId(),
            'status' => $participation->getStatus(),
            'registeredAt' => $participation->getRegisteredAt(),
            'rating' => $participation->getRating() ?? $participation->getFeedbackRating(),
            'feedback' => $participation->getFeedback() ?? $participation->getFeedbackComment(),
            'moodBefore' => $participation->getMoodBefore(),
            'moodAfter' => $participation->getMoodAfter(),
            'problemsEncountered' => $participation->getProblemsEncountered(),
            'recommendToFriends' => $participation->getRecommendToFriends(),
            'shareWithFamily' => $participation->getShareWithFamily(),
            'registrationMethod' => $participation->getRegistrationMethod(),
            'hasCertificate' => $participation->getHasCertificate(),
            'presenceConfirmationDate' => $participation->getPresenceConfirmationDate(),
        ];

        return $this->render('admin/participations/show.html.twig', [
            'participation' => $data,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_participations_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $participation = $this->participationRepository->find($id);
        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        if ($request->isMethod('POST')) {
            $participation->setStatus($request->request->get('status', $participation->getStatus()));
            $participation->setRating($request->request->getInt('rating') ?: null);
            $participation->setFeedback($request->request->get('feedback'));
            $participation->setMoodBefore($request->request->getInt('mood_before') ?: null);
            $participation->setMoodAfter($request->request->getInt('mood_after') ?: null);

            $this->em->flush();

            $this->addFlash('success', 'Participation mise à jour avec succès !');
            return $this->redirectToRoute('admin_participations');
        }

        $activity = $this->activityRepository->find($participation->getActivityId());
        $conn = $this->em->getConnection();
        $user = $conn->executeQuery('SELECT first_name, last_name FROM user WHERE id = ?', [$participation->getSeniorId()])->fetchAssociative();

        $data = [
            'id' => $participation->getId(),
            'activityName' => $activity ? $activity->getTitle() : ($participation->getTitle() ?? 'N/A'),
            'seniorName' => $user ? $user['first_name'] . ' ' . $user['last_name'] : 'Utilisateur #' . $participation->getSeniorId(),
            'status' => $participation->getStatus(),
            'rating' => $participation->getRating() ?? $participation->getFeedbackRating(),
            'feedback' => $participation->getFeedback() ?? $participation->getFeedbackComment(),
            'moodBefore' => $participation->getMoodBefore(),
            'moodAfter' => $participation->getMoodAfter(),
        ];

        return $this->render('admin/participations/edit.html.twig', [
            'participation' => $data,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_participations_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id): Response
    {
        $participation = $this->participationRepository->find($id);
        if (!$participation) {
            throw $this->createNotFoundException('Participation not found');
        }

        $this->em->remove($participation);
        $this->em->flush();

        $this->addFlash('success', 'Participation supprimée avec succès !');
        return $this->redirectToRoute('admin_participations');
    }
}
