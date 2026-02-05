<?php

namespace App\Controller\Admin;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activities')]
class ActivityAdminController extends AbstractController
{
    public function __construct(private ActivityRepository $activityRepository)
    {
    }

    #[Route('/', name: 'admin_activities', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $type = $request->query->get('type', '');
        $status = $request->query->get('status', '');

        $isActive = null;
        if ($status === 'active') {
            $isActive = true;
        } elseif ($status === 'inactive') {
            $isActive = false;
        }

        if ($query || $type || $status) {
            $activities = $this->activityRepository->searchActivities($query, $type ?: null, $isActive);
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

    #[Route('/new', name: 'admin_activities_new')]
    public function new(): Response
    {
        return $this->render('admin/activities/new.html.twig');
    }

    #[Route('/', name: 'admin_activities_store', methods: ['POST'])]
    public function store(Request $request): Response
    {
        $activity = new Activity();
        $activity->setTitle((string) $request->request->get('title'));
        $activity->setDescription((string) $request->request->get('description', ''));
        $activity->setType((string) $request->request->get('type', 'social'));
        $activity->setStartTime(new \DateTime($request->request->get('start_time')));
        $activity->setEndTime(new \DateTime($request->request->get('end_time') ?: $request->request->get('start_time')));
        $activity->setLocation((string) $request->request->get('location', ''));
        $activity->setMaxParticipants($request->request->get('max_participants') ? (int) $request->request->get('max_participants') : null);
        $activity->setIsActive($request->request->getBoolean('is_active', true));

        $this->activityRepository->save($activity, true);

        $this->addFlash('success', 'Activity created successfully!');

        return $this->redirectToRoute('admin_activities');
    }

    #[Route('/{id}', name: 'admin_activities_show', requirements: ['id' => '\d+'], methods: ['GET'])]
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

    #[Route('/{id}', name: 'admin_activities_update', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        $activity->setTitle((string) $request->request->get('title'));
        $activity->setDescription((string) $request->request->get('description', ''));
        $activity->setType((string) $request->request->get('type', 'social'));
        $activity->setStartTime(new \DateTime($request->request->get('start_time')));
        $activity->setEndTime(new \DateTime($request->request->get('end_time') ?: $request->request->get('start_time')));
        $activity->setLocation((string) $request->request->get('location', ''));
        $activity->setMaxParticipants($request->request->get('max_participants') ? (int) $request->request->get('max_participants') : null);
        $activity->setIsActive($request->request->getBoolean('is_active', true));

        $this->activityRepository->save($activity, true);

        $this->addFlash('success', 'Activity updated successfully!');

        return $this->redirectToRoute('admin_activities_show', ['id' => $activity->getId()]);
    }

    #[Route('/{id}/delete', name: 'admin_activities_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $activity = $this->activityRepository->find($id);

        if (!$activity) {
            throw $this->createNotFoundException('Activity not found');
        }

        $this->activityRepository->remove($activity, true);

        $this->addFlash('success', 'Activity deleted successfully!');

        return $this->redirectToRoute('admin_activities');
    }

    #[Route('/export/pdf', name: 'admin_activities_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        $query = $request->query->get('q', '');
        $type = $request->query->get('type', '');
        $status = $request->query->get('status', '');

        $isActive = null;
        if ($status === 'active') {
            $isActive = true;
        } elseif ($status === 'inactive') {
            $isActive = false;
        }

        if ($query || $type || $status) {
            $activities = $this->activityRepository->searchActivities($query, $type ?: null, $isActive);
        } else {
            $activities = $this->activityRepository->findAll();
        }

        return $this->render('admin/activities/export_pdf.html.twig', [
            'activities' => $activities,
        ]);
    }
}
