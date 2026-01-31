<?php


namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home_redirect')]
    public function redirectToLocale(): Response
    {
        return $this->redirectToRoute('app_home', ['_locale' => 'fr'], 301);
    }

    #[Route('/{_locale}/', name: 'app_home', requirements: ['_locale' => 'fr|en|ar'])]
    public function index(): Response
    {
        return $this->render('front/home.html.twig');
    }
}
