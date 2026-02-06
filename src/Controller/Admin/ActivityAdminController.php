<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activities')]
class ActivityAdminController extends AbstractController
{
    public function __construct(
        private ActivityRepository $activityRepository,
        private ParticipationRepository $participationRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'admin_activities')]
    public function index(): Response
    {
        $activities = $this->activityRepository->findBy([], ['startTime' => 'DESC']);

        $activitiesData = [];
        foreach ($activities as $activity) {
            $participantCount = $this->participationRepository->countActiveByActivity($activity->getId());
            $activitiesData[] = [
                'id' => $activity->getId(),
                'name' => $activity->getTitle(),
                'description' => $activity->getDescription() ?? '',
                'type' => $activity->getType(),
                'duration' => $activity->getStartTime() && $activity->getEndTime()
                    ? (int) (($activity->getEndTime()->getTimestamp() - $activity->getStartTime()->getTimestamp()) / 60)
                    : 0,
                'participants' => $participantCount,
                'maxParticipants' => $activity->getMaxParticipants(),
                'schedule' => $activity->getStartTime()?->format('d/m/Y H:i') ?? '',
                'location' => $activity->getLocation() ?? '',
                'status' => $activity->isActive() ? 'active' : 'inactive',
            ];
        }

        return $this->render('admin/activities/index.html.twig', [
            'activities' => $activitiesData,
        ]);
    }

    #[Route('/new', name: 'admin_activities_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $activity = new Activity();
            $activity->setTitle($request->request->get('name', ''));
            $activity->setDescription($request->request->get('description', ''));
            $activity->setType($request->request->get('type', 'social'));
            $activity->setLocation($request->request->get('location', ''));
            $activity->setMaxParticipants($request->request->getInt('max_participants') ?: null);
            $activity->setIsActive($request->request->get('status', 'active') === 'active');

            $startTime = $request->request->get('start_time');
            $endTime = $request->request->get('end_time');
            $activity->setStartTime($startTime ? new \DateTime($startTime) : new \DateTime());
            $activity->setEndTime($endTime ? new \DateTime($endTime) : null);

            $this->em->persist($activity);
            $this->em->flush();

            $this->addFlash('success', 'Activité "' . $activity->getTitle() . '" créée avec succès !');
            return $this->redirectToRoute('admin_activities');
        }

        return $this->render('admin/activities/new.html.twig');
    }

    #[Route('/{id}', name: 'admin_activities_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        $participations = $this->participationRepository->findByActivity($id);
        $participantCount = $this->participationRepository->countActiveByActivity($id);

        $activityData = [
            'id' => $activity->getId(),
            'name' => $activity->getTitle(),
            'description' => $activity->getDescription() ?? '',
            'type' => $activity->getType(),
            'duration' => $activity->getStartTime() && $activity->getEndTime()
                ? (int) (($activity->getEndTime()->getTimestamp() - $activity->getStartTime()->getTimestamp()) / 60)
                : 0,
            'participants' => $participantCount,
            'maxParticipants' => $activity->getMaxParticipants(),
            'schedule' => $activity->getStartTime()?->format('d/m/Y H:i') ?? '',
            'location' => $activity->getLocation() ?? '',
            'status' => $activity->isActive() ? 'active' : 'inactive',
            'startTime' => $activity->getStartTime(),
            'endTime' => $activity->getEndTime(),
        ];

        return $this->render('admin/activities/show.html.twig', [
            'activity' => $activityData,
            'participations' => $participations,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_activities_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        if ($request->isMethod('POST')) {
            $activity->setTitle($request->request->get('name', $activity->getTitle()));
            $activity->setDescription($request->request->get('description', ''));
            $activity->setType($request->request->get('type', $activity->getType()));
            $activity->setLocation($request->request->get('location', ''));
            $activity->setMaxParticipants($request->request->getInt('max_participants') ?: null);
            $activity->setIsActive($request->request->get('status', 'active') === 'active');

            $startTime = $request->request->get('start_time');
            $endTime = $request->request->get('end_time');
            if ($startTime) {
                $activity->setStartTime(new \DateTime($startTime));
            }
            if ($endTime) {
                $activity->setEndTime(new \DateTime($endTime));
            }

            $this->em->flush();

            $this->addFlash('success', 'Activité "' . $activity->getTitle() . '" mise à jour avec succès !');
            return $this->redirectToRoute('admin_activities');
        }

        $activityData = [
            'id' => $activity->getId(),
            'name' => $activity->getTitle(),
            'description' => $activity->getDescription() ?? '',
            'type' => $activity->getType(),
            'duration' => $activity->getStartTime() && $activity->getEndTime()
                ? (int) (($activity->getEndTime()->getTimestamp() - $activity->getStartTime()->getTimestamp()) / 60)
                : 0,
            'participants' => $activity->getCurrentParticipants(),
            'maxParticipants' => $activity->getMaxParticipants(),
            'schedule' => $activity->getStartTime()?->format('d/m/Y H:i') ?? '',
            'location' => $activity->getLocation() ?? '',
            'status' => $activity->isActive() ? 'active' : 'inactive',
            'startTime' => $activity->getStartTime()?->format('Y-m-d\TH:i') ?? '',
            'endTime' => $activity->getEndTime()?->format('Y-m-d\TH:i') ?? '',
        ];

        return $this->render('admin/activities/edit.html.twig', [
            'activity' => $activityData,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_activities_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $activity = $this->activityRepository->find($id);
        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        $title = $activity->getTitle();
        $this->em->remove($activity);
        $this->em->flush();

        $this->addFlash('success', 'Activité "' . $title . '" supprimée avec succès !');
        return $this->redirectToRoute('admin_activities');
    }
}
