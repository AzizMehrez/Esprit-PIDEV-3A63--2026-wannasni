<?php

namespace App\Controller\Front;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
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

    #[Route('/{_locale}/profile/edit', name: 'app_profile_edit', requirements: ['_locale' => 'fr|en|ar'], methods: ['GET', 'POST'])]
    public function edit(Request $request): Response
    {
        // Get the currently logged-in user
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
                // Validate file
                $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
                $maxFileSize = 2 * 1024 * 1024; // 2MB
                
                if (in_array($imageFile->getMimeType(), $allowedMimeTypes) && $imageFile->getSize() <= $maxFileSize) {
                    // Generate unique filename
                    $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                    
                    // Move file to public/images/profiles directory
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
                } else {
                    $this->addFlash('error', 'Format d\'image non valide ou taille supérieure à 2MB.');
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
