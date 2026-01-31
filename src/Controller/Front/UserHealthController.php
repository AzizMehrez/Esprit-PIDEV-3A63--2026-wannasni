<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-health', requirements: ['_locale' => 'fr|en|ar'])]
class UserHealthController extends AbstractController
{
    #[Route('/', name: 'app_my_health')]
    public function index(): Response
    {
        $healthRecords = [
            ['date' => new \DateTime('-1 day'), 'bloodPressure' => '120/80', 'heartRate' => 72, 'weight' => 65.5, 'mood' => 'good'],
            ['date' => new \DateTime('-2 days'), 'bloodPressure' => '118/78', 'heartRate' => 70, 'weight' => 65.3, 'mood' => 'excellent'],
            ['date' => new \DateTime('-3 days'), 'bloodPressure' => '122/82', 'heartRate' => 74, 'weight' => 65.8, 'mood' => 'okay'],
            ['date' => new \DateTime('-4 days'), 'bloodPressure' => '119/79', 'heartRate' => 71, 'weight' => 65.6, 'mood' => 'good'],
        ];

        return $this->render('front/health/index.html.twig', [
            'health_records' => $healthRecords,
        ]);
    }

    #[Route('/add', name: 'app_my_health_add')]
    public function add(): Response
    {
        return $this->render('front/health/add.html.twig');
    }
}
