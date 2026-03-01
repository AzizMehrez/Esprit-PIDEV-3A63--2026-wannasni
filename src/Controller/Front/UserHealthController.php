<?php

namespace App\Controller\Front;

use App\Entity\HealthJournal;
use App\Form\HealthJournalType;
use App\Repository\HealthJournalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 5;

        $qb = $repo->createQueryBuilder('h')
            ->andWhere('h.senior = :senior')
            ->setParameter('senior', $this->getUser())
            ->orderBy('h.date', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        $paginator = new Paginator($qb, fetchJoinCollection: false);
        $total = count($paginator);
        $totalPages = (int) ceil($total / $perPage);
        $page = min($page, max(1, $totalPages));

        return $this->render('front/health/index.html.twig', [
            'health_records' => $paginator,
            'current_page'   => $page,
            'total_pages'    => $totalPages,
        ]);
    }

    #[Route('/add', name: 'app_my_health_add')]
    public function add(Request $request, EntityManagerInterface $em): Response
    {
        $health = new HealthJournal();
        $health->setSenior($this->getUser());

        $form = $this->createForm(HealthJournalType::class, $health, [
            'hide_senior' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure senior is always the logged-in user
            $health->setSenior($this->getUser());
            $em->persist($health);
            $em->flush();

            $this->addFlash('success', 'Votre entrée de santé a été ajoutée avec succès !');
            return $this->redirectToRoute('app_my_health', ['_locale' => $request->getLocale()]);
        }

        $response = $this->render('front/health/add.html.twig', [
            'form' => $form->createView(),
        ]);

        // Retourner un statut 422 si le formulaire est soumis avec des erreurs
        if ($form->isSubmitted() && !$form->isValid()) {
            // DEBUG: log form errors
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getOrigin()->getName() . ': ' . $error->getMessage();
            }
            error_log('HealthJournal form errors: ' . implode(' | ', $errors));
            $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
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

        $form = $this->createForm(\App\Form\HealthJournalType::class, $record, [
            'hide_senior' => true,
        ]);
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
