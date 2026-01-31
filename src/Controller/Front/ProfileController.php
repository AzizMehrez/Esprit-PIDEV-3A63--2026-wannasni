<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/{_locale}/profile', name: 'app_profile', requirements: ['_locale' => 'fr|en|ar'])]
    public function index(): Response
    {
        // Mock user data
        $user = [
            'id' => 1,
            'firstName' => 'Marie',
            'lastName' => 'Dupont',
            'email' => 'marie.dupont@email.com',
            'phone' => '+33 6 12 34 56 78',
            'address' => '123 Rue de la Paix, 75001 Paris',
            'birthDate' => new \DateTime('1955-03-15'),
            'emergencyContact' => 'Jean Dupont (Fils) - +33 6 98 76 54 32',
        ];

        return $this->render('front/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{_locale}/profile/edit', name: 'app_profile_edit', requirements: ['_locale' => 'fr|en|ar'])]
    public function edit(): Response
    {
        // Mock user data
        $user = [
            'id' => 1,
            'firstName' => 'Marie',
            'lastName' => 'Dupont',
            'email' => 'marie.dupont@email.com',
            'phone' => '+33 6 12 34 56 78',
            'address' => '123 Rue de la Paix, 75001 Paris',
            'birthDate' => new \DateTime('1955-03-15'),
            'emergencyContact' => 'Jean Dupont (Fils) - +33 6 98 76 54 32',
        ];

        return $this->render('front/profile/edit.html.twig', [
            'user' => $user,
        ]);
    }
}
