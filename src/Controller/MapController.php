<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/map', requirements: ['_locale' => 'fr|en|ar'])]
class MapController extends AbstractController
{
    #[Route('/', name: 'app_map_index')]
    public function index(): Response
    {
        return $this->render('map/index.html.twig');
    }
}
