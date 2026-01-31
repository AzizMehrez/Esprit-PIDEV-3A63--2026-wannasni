<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-activities', requirements: ['_locale' => 'fr|en|ar'])]
class UserActivityController extends AbstractController
{
    #[Route('/', name: 'app_my_activities')]
    public function index(): Response
    {
        $enrolledActivities = [
            ['id' => 1, 'name' => 'Morning Walk', 'type' => 'physical', 'schedule' => 'Daily 8:00 AM', 'nextSession' => new \DateTime('+1 day')],
            ['id' => 2, 'name' => 'Memory Games', 'type' => 'cognitive', 'schedule' => 'Mon/Wed/Fri 10:00 AM', 'nextSession' => new \DateTime('+2 days')],
            ['id' => 3, 'name' => 'Yoga Class', 'type' => 'physical', 'schedule' => 'Tue/Thu 9:00 AM', 'nextSession' => new \DateTime('+3 days')],
        ];

        $availableActivities = [
            ['id' => 4, 'name' => 'Art Therapy', 'type' => 'creative', 'schedule' => 'Saturday 2:00 PM', 'participants' => 6],
            ['id' => 5, 'name' => 'Social Hour', 'type' => 'social', 'schedule' => 'Daily 3:00 PM', 'participants' => 20],
        ];

        return $this->render('front/activities/index.html.twig', [
            'enrolled_activities' => $enrolledActivities,
            'available_activities' => $availableActivities,
        ]);
    }
}
