<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/front', name: 'app_front_')]
class FrontOfficeController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(): Response
    {
        return $this->render('front/login.html.twig');
    }

    #[Route('/register', name: 'register')]
    public function register(): Response
    {
        return $this->render('front/register.html.twig');
    }

    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('front/dashboard.html.twig');
    }

    #[Route('/health', name: 'health')]
    public function health(): Response
    {
        return $this->render('front/health.html.twig');
    }

    #[Route('/alerts', name: 'alerts')]
    public function alerts(): Response
    {
        return $this->render('front/alerts.html.twig');
    }

    #[Route('/habits', name: 'habits')]
    public function habits(): Response
    {
        return $this->render('front/habits.html.twig');
    }

    #[Route('/challenges', name: 'challenges')]
    public function challenges(): Response
    {
        return $this->render('front/challenges.html.twig');
    }

    #[Route('/family', name: 'family')]
    public function family(): Response
    {
        return $this->render('front/family.html.twig');
    }

    #[Route('/recommendations', name: 'recommendations')]
    public function recommendations(): Response
    {
        return $this->render('front/recommendations.html.twig');
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return $this->render('front/profile.html.twig');
    }
}
