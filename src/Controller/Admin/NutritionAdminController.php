<?php

namespace App\Controller\Admin;

use App\Entity\DemandeRegime;
use App\Entity\RegimePrescrit;
use App\Form\RegimePrescritType;
use App\Repository\DemandeRegimeRepository;
use App\Repository\RegimePrescritRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

class NutritionAdminController extends AbstractController
{
    #[Route('/admin/nutrition', name: 'admin_nutrition')]
    public function index(Request $request, RegimePrescritRepository $repository): Response
    {
        $sort = $request->query->get('sort', 'desc');
        $regimePrescrits = $repository->findBy([], ['dateDebut' => $sort === 'asc' ? 'ASC' : 'DESC']);

        return $this->render('admin/regime_prescrit/index.html.twig', [
            'regime_prescrits' => $regimePrescrits,
            'current_sort' => $sort
        ]);
    }

    #[Route('/admin/nutrition/demandes', name: 'admin_nutrition_demandes')]
    public function demandesATraiter(Request $request, DemandeRegimeRepository $repository): Response
    {
        $sort = $request->query->get('sort', 'date');
        $type = $request->query->get('type', '');
        $query = $request->query->get('q', '');
        
        $queryBuilder = $repository->createQueryBuilder('d')
            ->leftJoin('d.regimesPrescrits', 'r')
            ->addSelect('r');

        // Filtrage par type
        if ($type) {
            $queryBuilder->andWhere('d.typeRegimeSouhaite = :type')
                ->setParameter('type', $type);
        }

        // Recherche textuelle (Senior ID ou Objectif)
        if ($query) {
            $queryBuilder->andWhere('d.seniorId LIKE :q OR d.objectifPrincipal LIKE :q OR d.typeRegimeSouhaite LIKE :q')
                ->setParameter('q', '%' . $query . '%');
        }

        // Tri
        if ($sort === 'status') {
            // Traitées en premier
            $queryBuilder->addSelect('(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) AS HIDDEN status_sort')
                ->orderBy('status_sort', 'DESC')
                ->addOrderBy('d.dateDemande', 'DESC');
        } else {
            $queryBuilder->orderBy('d.dateDemande', 'DESC');
        }

        $demandes = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/regime_prescrit/demandesatraiter.html.twig', [
            'demandes' => $demandes,
            'current_type' => $type,
            'current_sort' => $sort,
            'current_query' => $query,
        ]);
    }

    #[Route('/admin/nutrition/demande/{id}/delete', name: 'admin_nutrition_demande_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteDemande(int $id, Request $request, DemandeRegimeRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $demande = $repository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée');
        }

        if ($this->isCsrfTokenValid('delete'.$demande->getId(), $request->request->get('_token'))) {
            $entityManager->remove($demande);
            $entityManager->flush();
            $this->addFlash('success', 'Demande supprimée avec succès.');
        }

        return $this->redirectToRoute('admin_nutrition_demandes');
    }

    #[Route('/admin/nutrition/regime/{id}/delete', name: 'admin_nutrition_regime_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteRegime(int $id, Request $request, RegimePrescritRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $regime = $repository->find($id);
        if (!$regime) {
            throw $this->createNotFoundException('Régime non trouvé');
        }

        if ($this->isCsrfTokenValid('delete'.$regime->getId(), $request->request->get('_token'))) {
            // Rétablir le statut de la demande associée à "En attente" si nécessaire
            $demande = $regime->getDemande();
            if ($demande) {
                $demande->setStatut(DemandeRegime::STATUT_EN_ATTENTE);
            }

            $entityManager->remove($regime);
            $entityManager->flush();
            $this->addFlash('success', 'Régime prescrit supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_nutrition');
    }

    #[Route('/admin/nutrition/new/demande/{id}', name: 'admin_nutrition_new', requirements: ['id' => '\d+'])]
    public function new(int $id, Request $request, DemandeRegimeRepository $demandeRepository, EntityManagerInterface $entityManager): Response
    {
        $demande = $demandeRepository->find($id);
        if (!$demande) {
            throw $this->createNotFoundException('Demande non trouvée');
        }

        $regime = new RegimePrescrit();
        $regime->setDemande($demande);
        $regime->setSeniorId($demande->getSeniorId());
        $regime->setNutritionnisteId(2); // Nutritionniste par défaut
        $regime->setTypeRegime($demande->getTypeRegimeSouhaite());
        
        // Set user relationship if available
        if ($demande->getUser()) {
            $regime->setUser($demande->getUser());
        }

        $form = $this->createForm(RegimePrescritType::class, $regime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $demande->setStatut(DemandeRegime::STATUT_TRAITE);
            $demande->setDateTraitement(new \DateTime());
            
            $entityManager->persist($regime);
            $entityManager->flush();

            $this->addFlash('success', 'Régime prescrit avec succès !');
            return $this->redirectToRoute('admin_nutrition_demandes');
        }

        return $this->render('admin/regime_prescrit/new.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/nutrition/{id}', name: 'admin_nutrition_show', requirements: ['id' => '\d+'])]
    public function show(int $id, RegimePrescritRepository $repository): Response
    {
        $regime = $repository->find($id);

        if (!$regime) {
            throw $this->createNotFoundException('Régime prescrit non trouvé');
        }

        return $this->render('admin/regime_prescrit/show.html.twig', [
            'regime_prescrit' => $regime,
        ]);
    }

    #[Route('/admin/nutrition/{id}/edit', name: 'admin_nutrition_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request, RegimePrescritRepository $repository, EntityManagerInterface $entityManager): Response
    {
        $regime = $repository->find($id);

        if (!$regime) {
            throw $this->createNotFoundException('Régime prescrit non trouvé');
        }

        $form = $this->createForm(RegimePrescritType::class, $regime);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Régime modifié avec succès !');
            return $this->redirectToRoute('admin_nutrition');
        }

        return $this->render('admin/regime_prescrit/edit.html.twig', [
            'regime_prescrit' => $regime,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/nutrition/export-pdf/{id}', name: 'admin_nutrition_export_pdf', requirements: ['id' => '\d+'])]
    public function exportPdf(int $id, DemandeRegimeRepository $repository): Response
    {
        $demande = $repository->find($id);
        if (!$demande || $demande->getRegimesPrescrits()->isEmpty()) {
            throw $this->createNotFoundException('Demande non trouvée ou non traitée');
        }

        $regime = $demande->getRegimesPrescrits()->last();

        if (class_exists(Dompdf::class)) {
            $pdfOptions = new Options();
            $pdfOptions->set('defaultFont', 'Arial');
            $pdfOptions->set('isRemoteEnabled', true);

            $dompdf = new Dompdf($pdfOptions);
            $html = $this->renderView('admin/regime_prescrit/pdf_template.html.twig', [
                'demande' => $demande,
                'regime' => $regime,
            ]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            
            return new Response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="regime_senior_'.$demande->getSeniorId().'.pdf"'
            ]);
        }

        $this->addFlash('warning', 'La librairie PDF n\'est pas installée sur le serveur. Voici la version imprimable.');
        return $this->render('admin/regime_prescrit/pdf_template.html.twig', [
            'demande' => $demande,
            'regime' => $regime,
        ]);
    }
}
