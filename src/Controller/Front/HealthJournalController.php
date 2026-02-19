<?php

namespace App\Controller\Front;

use App\Entity\HealthJournal;
use App\Form\HealthJournalType;
use App\Repository\HealthJournalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/health/journal', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_USER')]
final class HealthJournalController extends AbstractController
{
    #[Route(name: 'app_health_journal_index', methods: ['GET'])]
    public function index(HealthJournalRepository $healthJournalRepository): Response
    {
        return $this->render('health_journal/index.html.twig', [
            'health_journals' => $healthJournalRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_health_journal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $healthJournal = new HealthJournal();
        // assign the currently logged-in user as the senior by default
        $healthJournal->setSenior($this->getUser());

        $form = $this->createForm(HealthJournalType::class, $healthJournal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($healthJournal);
            $entityManager->flush();

            return $this->redirectToRoute('app_health_journal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('health_journal/new.html.twig', [
            'health_journal' => $healthJournal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_health_journal_show', methods: ['GET'])]
    public function show(HealthJournal $healthJournal): Response
    {
        return $this->render('health_journal/show.html.twig', [
            'health_journal' => $healthJournal,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_health_journal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HealthJournal $healthJournal, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HealthJournalType::class, $healthJournal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_health_journal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('health_journal/edit.html.twig', [
            'health_journal' => $healthJournal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_health_journal_delete', methods: ['POST'])]
    public function delete(Request $request, HealthJournal $healthJournal, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$healthJournal->getId(), $request->request->get('_token'))) {
            $entityManager->remove($healthJournal);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_health_journal_index', [], Response::HTTP_SEE_OTHER);
    }
}
