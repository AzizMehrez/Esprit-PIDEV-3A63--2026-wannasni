<?php


namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/{_locale}/login', name: 'app_login', requirements: ['_locale' => 'fr|en|ar'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('front/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/{_locale}/logout', name: 'app_logout', requirements: ['_locale' => 'fr|en|ar'])]
    public function logout(): Response
    {
        // Simply redirect to home page
        return $this->redirectToRoute('app_home', ['_locale' => 'fr']);
    }

    #[Route(path: '/{_locale}/register', name: 'app_register', requirements: ['_locale' => 'fr|en|ar'])]
    public function register(): Response
    {
        return $this->render('front/register.html.twig');
    }
}
