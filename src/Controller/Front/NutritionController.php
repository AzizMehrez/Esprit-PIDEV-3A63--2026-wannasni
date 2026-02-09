<?php

namespace App\Controller\Front;

use App\Entity\DemandeRegime;
use App\Entity\RegimePrescrit;
use App\Form\DemandeRegimeType;
use App\Repository\DemandeRegimeRepository;
use App\Repository\RegimePrescritRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/nutrition', requirements: ['_locale' => 'fr|en|ar'])]
class NutritionController extends AbstractController
{
    #[Route('/', name: 'app_my_nutrition')]
    public function index(DemandeRegimeRepository $demandeRegimeRepository): Response
    {
        $user = $this->getUser();
        
        if ($user) {
            // Fetch real DemandeRegime entities for the logged-in user
            $demandeRegimes = $demandeRegimeRepository->findBy(
                ['user' => $user],
                ['dateDemande' => 'DESC']
            );
        } else {
            $demandeRegimes = [];
        }

        return $this->render('front/nutrition/index.html.twig', [
            'demande_regimes' => $demandeRegimes,
        ]);
    }

    #[Route('/scan', name: 'app_nutrition_scan')]
    public function scan(): Response
    {
        return $this->render('front/nutrition/scan.html.twig');
    }

    #[Route('/request', name: 'app_nutrition_request', methods: ['GET', 'POST'])]
    public function request(Request $request, EntityManagerInterface $em): Response
    {
        $demandeRegime = new DemandeRegime();
        
        $form = $this->createForm(DemandeRegimeType::class, $demandeRegime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            
            // Associate with logged-in user
            $demandeRegime->setUser($user);
            
            // Set integer IDs for backward compatibility (if needed)
            $demandeRegime->setSeniorId($user->getId());
            
            // Assign nutritionist (hardcoded for now, can be dynamic later)
            $demandeRegime->setNutritionnisteId(2); // Default nutritionist
            
            // Date is auto-set in constructor
            // Statut is auto-set to 'en_attente' in entity

            $em->persist($demandeRegime);
            $em->flush();

            $this->addFlash('success', '🎉 Votre demande de régime a été créée avec succès !');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/request.html.twig', [
            'demande_regime' => $demandeRegime,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_nutrition_show', requirements: ['id' => '\d+'])]
    public function show(int $id, DemandeRegimeRepository $demandeRegimeRepository): Response
    {
        $demandeRegime = $demandeRegimeRepository->find($id);

        if (!$demandeRegime) {
            $this->addFlash('error', 'Demande de régime introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        // Security check
        $user = $this->getUser();
        if ($demandeRegime->getUser() !== $user) {
            $this->addFlash('error', 'Vous n\'avez pas accès à cette demande.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/show.html.twig', [
            'demande_regime' => $demandeRegime,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_nutrition_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, DemandeRegimeRepository $demandeRegimeRepository, EntityManagerInterface $em): Response
    {
        $demandeRegime = $demandeRegimeRepository->find($id);

        if (!$demandeRegime) {
            $this->addFlash('error', 'Demande de régime introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        // Security check
        $user = $this->getUser();
        if ($demandeRegime->getUser() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier cette demande.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        $form = $this->createForm(DemandeRegimeType::class, $demandeRegime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', '✅ Demande de régime mise à jour avec succès !');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/edit.html.twig', [
            'demande_regime' => $demandeRegime,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_nutrition_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, DemandeRegimeRepository $demandeRegimeRepository, EntityManagerInterface $em): Response
    {
        $demandeRegime = $demandeRegimeRepository->find($id);

        if (!$demandeRegime) {
            $this->addFlash('error', 'Demande de régime introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        // Security check
        $user = $this->getUser();
        if ($demandeRegime->getUser() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette demande.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        if ($this->isCsrfTokenValid('delete'.$demandeRegime->getId(), $request->request->get('_token'))) {
            $em->remove($demandeRegime);
            $em->flush();
            $this->addFlash('success', '🗑️ Demande de régime supprimée avec succès !');
        }

        return $this->redirectToRoute('app_my_nutrition');
    }

    #[Route('/regime/{id}', name: 'app_nutrition_regime', requirements: ['id' => '\d+'])]
    public function regime(int $id, RegimePrescritRepository $regimePrescritRepository): Response
    {
        $regimePrescrit = $regimePrescritRepository->find($id);

        if (!$regimePrescrit) {
            $this->addFlash('error', 'Régime prescrit introuvable.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        // Security check - user should only see their own prescribed regimes
        $user = $this->getUser();
        if ($regimePrescrit->getUser() !== $user) {
            $this->addFlash('error', 'Vous n\'avez pas accès à ce régime.');
            return $this->redirectToRoute('app_my_nutrition');
        }

        return $this->render('front/nutrition/regime.html.twig', [
            'regime_prescrit' => $regimePrescrit,
        ]);
    }
}
