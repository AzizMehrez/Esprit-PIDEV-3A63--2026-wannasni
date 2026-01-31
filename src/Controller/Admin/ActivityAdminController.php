<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/activities')]
class ActivityAdminController extends AbstractController
{
    private function getMockActivities(): array
    {
        return [
            ['id' => 1, 'name' => 'Morning Walk', 'description' => 'Gentle walking exercise for seniors', 'type' => 'physical', 'duration' => 30, 'participants' => 12, 'schedule' => 'Daily 8:00 AM', 'status' => 'active'],
            ['id' => 2, 'name' => 'Memory Games', 'description' => 'Cognitive exercises to keep mind sharp', 'type' => 'cognitive', 'duration' => 45, 'participants' => 8, 'schedule' => 'Mon/Wed/Fri 10:00 AM', 'status' => 'active'],
            ['id' => 3, 'name' => 'Yoga Class', 'description' => 'Gentle yoga for flexibility and relaxation', 'type' => 'physical', 'duration' => 60, 'participants' => 15, 'schedule' => 'Tue/Thu 9:00 AM', 'status' => 'active'],
            ['id' => 4, 'name' => 'Art Therapy', 'description' => 'Creative expression through painting', 'type' => 'creative', 'duration' => 90, 'participants' => 6, 'schedule' => 'Saturday 2:00 PM', 'status' => 'inactive'],
            ['id' => 5, 'name' => 'Social Hour', 'description' => 'Group conversation and tea time', 'type' => 'social', 'duration' => 60, 'participants' => 20, 'schedule' => 'Daily 3:00 PM', 'status' => 'active'],
        ];
    }

    #[Route('/', name: 'admin_activities')]
    public function index(): Response
    {
        return $this->render('admin/activities/index.html.twig', [
            'activities' => $this->getMockActivities(),
        ]);
    }

    #[Route('/{id}', name: 'admin_activities_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $activities = $this->getMockActivities();
        $activity = null;
        foreach ($activities as $a) {
            if ($a['id'] === $id) {
                $activity = $a;
                break;
            }
        }

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
        $activities = $this->getMockActivities();
        $activity = null;
        foreach ($activities as $a) {
            if ($a['id'] === $id) {
                $activity = $a;
                break;
            }
        }

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
}
