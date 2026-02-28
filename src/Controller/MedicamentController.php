<?php

namespace App\Controller;

use App\Service\MedicamentMLService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/medicaments', requirements: ['_locale' => 'fr|en|ar'])]
class MedicamentController extends AbstractController
{
    #[Route('/alternatives', name: 'app_medicament_alternatives', methods: ['GET', 'POST'])]
    public function alternatif(Request $request, MedicamentMLService $mlService): Response
    {
        $nom = $request->query->get('nom') ?? $request->request->get('nom');
        $imageFile = $request->files->get('medicament_image');
        $data = [];
        $imageBase64 = null;

        if ($imageFile) {
            $data = $mlService->analyzeImage($imageFile);
            if (isset($data['original'])) {
                $nom = $data['original'];
            }
            // Convertir l'image en base64 pour l'afficher
            $imageData = file_get_contents($imageFile->getRealPath());
            $imageBase64 = 'data:' . $imageFile->getMimeType() . ';base64,' . base64_encode($imageData);
        } elseif ($nom) {
            $data = $mlService->getAlternatives($nom);
        }

        return $this->render('medicament/alternatif.html.twig', [
            'nom' => $nom,
            'data' => $data,
            'image_preview' => $imageBase64
        ]);
    }
}
