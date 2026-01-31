<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/health')]
class HealthAdminController extends AbstractController
{
    private function getMockHealthRecords(): array
    {
        return [
            ['id' => 1, 'user' => 'Marie Dupont', 'date' => new \DateTime('-1 day'), 'bloodPressure' => '120/80', 'heartRate' => 72, 'weight' => 65.5, 'notes' => 'Feeling well, no concerns', 'mood' => 'good'],
            ['id' => 2, 'user' => 'Jean Martin', 'date' => new \DateTime('-1 day'), 'bloodPressure' => '135/85', 'heartRate' => 78, 'weight' => 82.0, 'notes' => 'Slight headache in the morning', 'mood' => 'okay'],
            ['id' => 3, 'user' => 'Sophie Bernard', 'date' => new \DateTime('-2 days'), 'bloodPressure' => '118/75', 'heartRate' => 68, 'weight' => 58.2, 'notes' => 'Good energy levels', 'mood' => 'excellent'],
            ['id' => 4, 'user' => 'Pierre Durand', 'date' => new \DateTime('-2 days'), 'bloodPressure' => '145/92', 'heartRate' => 85, 'weight' => 78.5, 'notes' => 'Need to monitor blood pressure', 'mood' => 'okay'],
            ['id' => 5, 'user' => 'Marie Dupont', 'date' => new \DateTime('-3 days'), 'bloodPressure' => '122/82', 'heartRate' => 70, 'weight' => 65.3, 'notes' => 'Regular check-up', 'mood' => 'good'],
        ];
    }

    #[Route('/', name: 'admin_health')]
    public function index(): Response
    {
        return $this->render('admin/health/index.html.twig', [
            'records' => $this->getMockHealthRecords(),
        ]);
    }

    #[Route('/{id}', name: 'admin_health_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $records = $this->getMockHealthRecords();
        $record = null;
        foreach ($records as $r) {
            if ($r['id'] === $id) {
                $record = $r;
                break;
            }
        }

        if (!$record) {
            throw $this->createNotFoundException('Health record not found');
        }

        return $this->render('admin/health/show.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/new', name: 'admin_health_new')]
    public function new(): Response
    {
        return $this->render('admin/health/new.html.twig');
    }
}
