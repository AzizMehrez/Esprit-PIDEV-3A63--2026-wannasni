<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\FaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FaceService $faceService
    ) {}

    #[Route('/{_locale}/profile', name: 'app_profile', requirements: ['_locale' => 'fr|en|ar'])]
    public function index(): Response
    {
        // Get the currently logged-in user
        $user = $this->getUser();

        return $this->render('front/profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{_locale}/profile/validate-image', name: 'app_profile_validate_image', requirements: ['_locale' => 'fr|en|ar'], methods: ['POST'])]
    public function validateImage(Request $request): JsonResponse
    {
        if (!$this->getUser()) {
            return new JsonResponse(['valid' => false, 'message' => 'Non authentifié'], 401);
        }

        $imageData = $request->request->get('imageData');
        if (!$imageData) {
            return new JsonResponse(['valid' => false, 'message' => 'Aucune image reçue']);
        }

        try {
            $result = $this->faceService->detectFaces($imageData);

            if (!$result['success']) {
                return new JsonResponse([
                    'valid' => false,
                    'message' => 'Impossible d’analyser cette image. Veuillez réessayer avec une autre photo.'
                ]);
            }

            if (($result['faces_count'] ?? 0) === 0) {
                return new JsonResponse([
                    'valid' => false,
                    'message' => 'Aucun visage détecté. Veuillez utiliser une photo de votre visage.'
                ]);
            }

            if (($result['faces_count'] ?? 0) > 1) {
                return new JsonResponse([
                    'valid' => false,
                    'message' => 'Plusieurs visages détectés. Veuillez utiliser une photo avec un seul visage.'
                ]);
            }

            return new JsonResponse([
                'valid' => true,
                'message' => 'Visage validé avec succès !'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'valid' => false,
                'message' => 'Erreur lors de la validation. Vérifiez que Python et OpenCV sont installés.'
            ]);
        }
    }

    #[Route('/{_locale}/profile/edit', name: 'app_profile_edit', requirements: ['_locale' => 'fr|en|ar'], methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        // Get the currently logged-in user
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            // Update user data
            $user->setFirstName($request->request->get('firstName'));
            $user->setLastName($request->request->get('lastName'));
            $user->setEmail($request->request->get('email'));
            $user->setPhone($request->request->get('phone'));
            
            // Handle file upload for profile image
            $imageFile = $request->files->get('imageProfil');
            if ($imageFile) {
                // Validate file type and size
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxFileSize = 2 * 1024 * 1024; // 2MB

                if (!in_array($imageFile->getMimeType(), $allowedMimeTypes) || $imageFile->getSize() > $maxFileSize) {
                    $this->addFlash('error', 'Format d\'image non valide ou taille supérieure à 2MB.');
                } else {
                    // Server-side face validation using Python
                    $imageBase64 = 'data:' . $imageFile->getMimeType() . ';base64,' .
                        base64_encode(file_get_contents($imageFile->getPathname()));

                    try {
                        $faceResult = $this->faceService->detectFaces($imageBase64);

                        if (!$faceResult['success'] || ($faceResult['faces_count'] ?? 0) === 0) {
                            $this->addFlash('error', '❌ Aucun visage humain détecté. Veuillez télécharger une photo de votre visage.');
                        } elseif (($faceResult['faces_count'] ?? 0) > 1) {
                            $this->addFlash('error', '❌ Plusieurs visages détectés. Veuillez utiliser une photo avec un seul visage.');
                        } else {
                            // Face validated — save the image
                            $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/images/profiles';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            try {
                                $imageFile->move($uploadDir, $newFilename);
                                $user->setImageProfil('/images/profiles/' . $newFilename);
                            } catch (\Exception $e) {
                                $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                            }
                        }
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur de validation de l\'image. Veuillez réessayer.');
                    }
                }
            }
            
            // Handle date of birth
            $dateNaissance = $request->request->get('dateNaissance');
            if ($dateNaissance) {
                $user->setDateNaissance(new \DateTime($dateNaissance));
            }
            
            $user->setAdresse($request->request->get('adresse'));
            $user->setVille($request->request->get('ville'));
            $user->setCodePostal($request->request->get('codePostal'));
            $user->setPays($request->request->get('pays'));
            $user->setLocation($request->request->get('location'));

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            return $this->redirectToRoute('app_profile', ['_locale' => $request->getLocale()]);
        }

        return $this->render('front/profile/edit.html.twig', [
            'user' => $user,
        ]);
    }
}
