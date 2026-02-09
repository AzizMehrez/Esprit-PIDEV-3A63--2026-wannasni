<?php

namespace App\Controller;

use App\Entity\HealthJournal;
use App\Form\HealthJournalType;
use App\Repository\HealthJournalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/my-health', requirements: ['_locale' => 'fr|en|ar'])]
class HealthJournalController extends AbstractController
{
    #[Route('/', name: 'app_health_journal_index', methods: ['GET'])]
    public function index(HealthJournalRepository $healthJournalRepository): Response
    {
        // Ideally filter by logged in user (senior)
        // $user = $this->getUser();
        // $journals = $healthJournalRepository->findBySenior($user->getId());
        
        // For now, fetching all or dummy logic if user retrieval isn't fully set up for seniors specific ID
        $journals = $healthJournalRepository->findAll();

        return $this->render('front/health_journal/index.html.twig', [
            'health_journals' => $journals,
        ]);
    }

    #[Route('/add', name: 'app_health_journal_add_redirect', methods: ['GET', 'POST'])]
    public function addRedirect(): Response
    {
        return $this->redirectToRoute('app_health_journal_new');
    }

    #[Route('/new', name: 'app_health_journal_new', methods: ['GET', 'POST'])]
    public function new(Request $request, HealthJournalRepository $healthJournalRepository): Response
    {
        $healthJournal = new HealthJournal();
        $form = $this->createForm(HealthJournalType::class, $healthJournal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->getUser()) {
                $healthJournal->setSenior($this->getUser());
            }
            $healthJournalRepository->save($healthJournal, true);

            return $this->redirectToRoute('app_health_journal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('front/health_journal/new.html.twig', [
            'health_journal' => $healthJournal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_health_journal_show', methods: ['GET'])]
    public function show(HealthJournal $healthJournal): Response
    {
        return $this->render('front/health_journal/show.html.twig', [
            'health_journal' => $healthJournal,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_health_journal_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, HealthJournal $healthJournal, HealthJournalRepository $healthJournalRepository): Response
    {
        $form = $this->createForm(HealthJournalType::class, $healthJournal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $healthJournalRepository->save($healthJournal, true);

            return $this->redirectToRoute('app_health_journal_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('front/health_journal/edit.html.twig', [
            'health_journal' => $healthJournal,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_health_journal_delete', methods: ['POST'])]
    public function delete(Request $request, HealthJournal $healthJournal, HealthJournalRepository $healthJournalRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$healthJournal->getId(), $request->request->get('_token'))) {
            $healthJournalRepository->remove($healthJournal, true);
        }

        return $this->redirectToRoute('app_health_journal_index', [], Response::HTTP_SEE_OTHER);
    }
}
