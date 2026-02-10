<?php

namespace App\Controller\Front;

use App\Entity\HealthJournal;
use App\Form\HealthJournalType;
use App\Repository\HealthJournalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-health', requirements: ['_locale' => 'fr|en|ar'])]
class UserHealthController extends AbstractController
{
    #[Route('/', name: 'app_my_health')]
    public function index(Request $request, HealthJournalRepository $repo): Response
    {
        $qb = $repo->createQueryBuilder('h')
            ->andWhere('h.senior = :senior')
            ->setParameter('senior', $this->getUser())
            ->orderBy('h.date', 'DESC');

        $healthRecords = $qb->getQuery()->getResult();

        return $this->render('front/health/index.html.twig', [
            'health_records' => $healthRecords,
        ]);
    }

    #[Route('/add', name: 'app_my_health_add')]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $health = new HealthJournal();
        $health->setSenior($this->getUser());

        $form = $this->createForm(HealthJournalType::class, $health);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($health);
            $em->flush();

            $this->addFlash('success', 'Votre entrée de santé a été ajoutée avec succès !');
            return $this->redirectToRoute('app_my_health');
        }

        $response = $this->render('front/health/add.html.twig', [
            'form' => $form->createView(),
        ]);

        // Retourner un statut 422 si le formulaire est soumis avec des erreurs
        if ($form->isSubmitted() && !$form->isValid()) {
            $response->setStatusCode(422);
        }

        return $response;
    }

    #[Route('/{id}/edit', name: 'app_my_health_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HealthJournal $record, EntityManagerInterface $em): Response
    {
        // Vérifier que l'utilisateur est le propriétaire
        if ($record->getSenior() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette entrée.');
        }

        $form = $this->createForm(\App\Form\HealthJournalType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre entrée de santé a été modifiée avec succès !');
            return $this->redirectToRoute('app_my_health');
        }

        $response = $this->render('front/health/edit.html.twig', [
            'form' => $form->createView(),
            'record' => $record,
        ]);

        // Retourner un statut 422 si le formulaire est soumis avec des erreurs
        if ($form->isSubmitted() && !$form->isValid()) {
            $response->setStatusCode(422);
        }

        return $response;
    }

    #[Route('/{id}/delete', name: 'app_my_health_delete', methods: ['POST'])]
    public function delete(Request $request, HealthJournal $record, EntityManagerInterface $em): Response
    {
        // Vérifier que l'utilisateur est le propriétaire
        if ($record->getSenior() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette entrée.');
        }

        if ($this->isCsrfTokenValid('delete'.$record->getId(), $request->request->get('_token'))) {
            $em->remove($record);
            $em->flush();
            $this->addFlash('success', 'L\'entrée de santé a été supprimée avec succès !');
        } else {
            $this->addFlash('error', 'Token de sécurité invalide.');
        }

        return $this->redirectToRoute('app_my_health');
    }

    #[Route('/{id}', name: 'app_my_health_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(HealthJournal $record): Response
    {
        // Vérifier que l'utilisateur est le propriétaire
        if ($record->getSenior() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas voir cette entrée.');
        }

        return $this->render('front/health/show.html.twig', [
            'record' => $record,
        ]);
    }
}
