<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/services')]
class ServiceAdminController extends AbstractController
{
    private function getMockServices(): array
    {
        return [
            ['id' => 1, 'requester' => 'Marie Dupont', 'type' => 'Medical Transport', 'description' => 'Transport to hospital for check-up', 'status' => 'pending', 'priority' => 'high', 'requestedAt' => new \DateTime('-2 hours')],
            ['id' => 2, 'requester' => 'Jean Martin', 'type' => 'Home Care', 'description' => 'Daily assistance with medication', 'status' => 'in_progress', 'priority' => 'medium', 'requestedAt' => new \DateTime('-1 day')],
            ['id' => 3, 'requester' => 'Sophie Bernard', 'type' => 'Grocery Shopping', 'description' => 'Weekly grocery shopping assistance', 'status' => 'completed', 'priority' => 'low', 'requestedAt' => new \DateTime('-3 days')],
            ['id' => 4, 'requester' => 'Pierre Durand', 'type' => 'Companionship', 'description' => 'Weekly visit for conversation', 'status' => 'pending', 'priority' => 'medium', 'requestedAt' => new \DateTime('-5 hours')],
            ['id' => 5, 'requester' => 'Françoise Petit', 'type' => 'Medical Transport', 'description' => 'Transport to physiotherapy', 'status' => 'cancelled', 'priority' => 'high', 'requestedAt' => new \DateTime('-2 days')],
        ];
    }

    #[Route('/', name: 'admin_services')]
    public function index(): Response
    {
        return $this->render('admin/services/index.html.twig', [
            'services' => $this->getMockServices(),
        ]);
    }

    #[Route('/{id}', name: 'admin_services_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $services = $this->getMockServices();
        $service = null;
        foreach ($services as $s) {
            if ($s['id'] === $id) {
                $service = $s;
                break;
            }
        }

        if (!$service) {
            throw $this->createNotFoundException('Service request not found');
        }

        return $this->render('admin/services/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_services_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id): Response
    {
        $services = $this->getMockServices();
        $service = null;
        foreach ($services as $s) {
            if ($s['id'] === $id) {
                $service = $s;
                break;
            }
        }

        if (!$service) {
            throw $this->createNotFoundException('Service request not found');
        }

        return $this->render('admin/services/edit.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/new', name: 'admin_services_new')]
    public function new(): Response
    {
        return $this->render('admin/services/new.html.twig');
    }
}
