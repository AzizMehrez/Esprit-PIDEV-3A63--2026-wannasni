<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-services', requirements: ['_locale' => 'fr|en|ar'])]
class UserServiceController extends AbstractController
{
    #[Route('/', name: 'app_my_services')]
    public function index(): Response
    {
        $myServices = [
            ['id' => 1, 'type' => 'Medical Transport', 'description' => 'Transport to hospital for check-up', 'status' => 'pending', 'requestedAt' => new \DateTime('-2 hours'), 'scheduledFor' => new \DateTime('+2 days')],
            ['id' => 2, 'type' => 'Home Care', 'description' => 'Daily assistance with medication', 'status' => 'in_progress', 'requestedAt' => new \DateTime('-1 day'), 'scheduledFor' => null],
            ['id' => 3, 'type' => 'Grocery Shopping', 'description' => 'Weekly grocery shopping assistance', 'status' => 'completed', 'requestedAt' => new \DateTime('-5 days'), 'scheduledFor' => new \DateTime('-3 days')],
        ];

        return $this->render('front/services/index.html.twig', [
            'my_services' => $myServices,
        ]);
    }

    #[Route('/request', name: 'app_services_request')]
    public function request(): Response
    {
        $serviceTypes = [
            ['id' => 'transport', 'name' => 'Medical Transport', 'icon' => '🚗', 'description' => 'Transportation to medical appointments'],
            ['id' => 'homecare', 'name' => 'Home Care', 'icon' => '🏠', 'description' => 'Daily assistance at home'],
            ['id' => 'grocery', 'name' => 'Grocery Shopping', 'icon' => '🛒', 'description' => 'Help with grocery shopping'],
            ['id' => 'companionship', 'name' => 'Companionship', 'icon' => '👋', 'description' => 'Friendly visits and company'],
        ];

        return $this->render('front/services/request.html.twig', [
            'service_types' => $serviceTypes,
        ]);
    }
}
