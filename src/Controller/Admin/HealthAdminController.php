<?php

namespace App\Controller\Admin;

use App\Entity\HealthJournal;
use App\Form\HealthJournalType;
use App\Repository\HealthJournalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/health')]
#[IsGranted('ROLE_ADMIN')]
class HealthAdminController extends AbstractController
{
    #[Route('/', name: 'admin_health')]
    public function index(Request $request, HealthJournalRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $repo->createQueryBuilder('h')
            ->leftJoin('h.senior', 'u')
            ->addSelect('u');

        if ($q) {
            $qb->andWhere('u.firstName LIKE :q OR u.lastName LIKE :q OR h.notes LIKE :q OR h.humeur LIKE :q OR h.tensionArterielle LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('h.date', $sort === 'asc' ? 'ASC' : 'DESC');
        $records = $qb->getQuery()->getResult();

        return $this->render('admin/health/index.html.twig', [
            'records' => $records,
            'q' => $q,
            'sort' => $sort,
        ]);
    }

    #[Route('/new', name: 'admin_health_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $record = new HealthJournal();
        $form = $this->createForm(HealthJournalType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($record);
            $em->flush();

            return $this->redirectToRoute('admin_health');
        }

        return $this->render('admin/health/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_health_show', requirements: ['id' => '\d+'])]
    public function show(HealthJournal $record): Response
    {
        return $this->render('admin/health/show.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_health_edit')]
    public function edit(Request $request, HealthJournal $record, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(HealthJournalType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_health');
        }

        return $this->render('admin/health/edit.html.twig', [
            'form' => $form->createView(),
            'record' => $record,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_health_delete', methods: ['POST'])]
    public function delete(Request $request, HealthJournal $record, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$record->getId(), $request->request->get('_token'))) {
            $em->remove($record);
            $em->flush();
        }

        return $this->redirectToRoute('admin_health');
    }

    #[Route('/export', name: 'admin_health_export')]
    public function export(Request $request, HealthJournalRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $repo->createQueryBuilder('h')
            ->leftJoin('h.senior', 'u')
            ->addSelect('u');

        if ($q) {
            $qb->andWhere('u.firstName LIKE :q OR u.lastName LIKE :q OR h.notes LIKE :q OR h.humeur LIKE :q OR h.tensionArterielle LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('h.date', $sort === 'asc' ? 'ASC' : 'DESC');
        $records = $qb->getQuery()->getResult();

        $html = $this->renderView('admin/health/pdf.html.twig', ['entries' => $records, 'q' => $q, 'sort' => $sort]);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        return new Response($pdfOutput, 200, ['Content-Type' => 'application/pdf']);
    }
}
