<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
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
        private EntityManagerInterface $em
    ) {
    }

    #[Route('/', name: 'admin_activities')]
    public function index(Request $request): Response
    {
        $query = $request->query->get('q');
        $type = $request->query->get('type');
        $status = $request->query->get('status');

        if ($query || $type || $status) {
            $activities = $this->activityRepository->search($query, $type, $status);
        } else {
            $activities = $this->activityRepository->findAll();
        }

        return $this->render('admin/activities/index.html.twig', [
            'activities' => $activities,
            'search_query' => $query,
            'search_type' => $type,
            'search_status' => $status,
        ]);
    }

    #[Route('/{id}', name: 'admin_activities_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        return $this->render('admin/activities/show.html.twig', [
            'activity' => $activity,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_activities_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        return $this->render('admin/activities/edit.html.twig', [
            'activity' => $activity,
        ]);
    }

    #[Route('/new', name: 'admin_activities_new')]
    public function new(): Response
    {
        return $this->render('admin/activities/new.html.twig');
    }

    #[Route('/store', name: 'admin_activities_store', methods: ['POST'])]
    public function store(Request $request): Response
    {
        $activity = new Activity();
        $activity->setTitle($request->request->get('title'));
        $activity->setDescription($request->request->get('description'));
        $activity->setType($request->request->get('type', 'social'));
        $activity->setLocation($request->request->get('location'));
        $activity->setStartTime(new \DateTime($request->request->get('start_time')));
        
        $endTime = $request->request->get('end_time');
        if ($endTime) {
            $activity->setEndTime(new \DateTime($endTime));
        }

        $maxParticipants = $request->request->get('max_participants');
        if ($maxParticipants) {
            $activity->setMaxParticipants((int)$maxParticipants);
        }

        $activity->setIsActive(true);

        $this->em->persist($activity);
        $this->em->flush();

        $this->addFlash('success', 'Activity created successfully!');

        return $this->redirectToRoute('admin_activities');
    }

    #[Route('/{id}/update', name: 'admin_activities_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        $activity->setTitle($request->request->get('title'));
        $activity->setDescription($request->request->get('description'));
        $activity->setType($request->request->get('type', 'social'));
        $activity->setLocation($request->request->get('location'));
        $activity->setStartTime(new \DateTime($request->request->get('start_time')));
        
        $endTime = $request->request->get('end_time');
        if ($endTime) {
            $activity->setEndTime(new \DateTime($endTime));
        }

        $maxParticipants = $request->request->get('max_participants');
        if ($maxParticipants) {
            $activity->setMaxParticipants((int)$maxParticipants);
        }

        $activity->setIsActive($request->request->get('is_active') === '1');

        $this->em->flush();

        $this->addFlash('success', 'Activity updated successfully!');

        return $this->redirectToRoute('admin_activities_show', ['id' => $id]);
    }

    #[Route('/{id}/delete', name: 'admin_activities_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        $this->em->remove($activity);
        $this->em->flush();

        $this->addFlash('success', 'Activity deleted successfully!');

        return $this->redirectToRoute('admin_activities');
    }

    #[Route('/export-pdf', name: 'admin_activities_export_pdf')]
    public function exportPdf(Request $request): Response
    {
        $query = $request->query->get('q');
        $type = $request->query->get('type');
        $status = $request->query->get('status');

        if ($query || $type || $status) {
            $activities = $this->activityRepository->search($query, $type, $status);
        } else {
            $activities = $this->activityRepository->findAll();
        }

        return $this->render('admin/activities/export_pdf.html.twig', [
            'activities' => $activities,
        ]);
    }
}
