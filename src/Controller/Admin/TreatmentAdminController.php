<?php

namespace App\Controller\Admin;

use App\Entity\Treatment;
use App\Form\TreatmentType;
use App\Repository\TreatmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/treatment')]
#[IsGranted('ROLE_ADMIN')]
class TreatmentAdminController extends AbstractController
{
    #[Route('/', name: 'admin_treatment')]
    public function index(Request $request, TreatmentRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $repo->createQueryBuilder('t')
            ->leftJoin('t.senior', 's')
            ->leftJoin('t.docteur', 'd')
            ->addSelect('s')
            ->addSelect('d');

        if ($q) {
            $qb->andWhere('s.firstName LIKE :q OR s.lastName LIKE :q OR d.firstName LIKE :q OR d.lastName LIKE :q OR t.medicaments LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('t.datePrescription', $sort === 'asc' ? 'ASC' : 'DESC');
        $records = $qb->getQuery()->getResult();

        return $this->render('admin/treatment/index.html.twig', [
            'records' => $records,
            'q' => $q,
            'sort' => $sort,
        ]);
    }

    #[Route('/export', name: 'admin_treatment_export')]
    public function export(Request $request, TreatmentRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $repo->createQueryBuilder('t')
            ->leftJoin('t.senior', 's')
            ->leftJoin('t.docteur', 'd')
            ->addSelect('s')
            ->addSelect('d');

        if ($q) {
            $qb->andWhere('s.firstName LIKE :q OR s.lastName LIKE :q OR d.firstName LIKE :q OR d.lastName LIKE :q OR t.medicaments LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('t.datePrescription', $sort === 'asc' ? 'ASC' : 'DESC');
        $records = $qb->getQuery()->getResult();

        $html = $this->renderView('admin/treatment/pdf.html.twig', ['entries' => $records, 'q' => $q, 'sort' => $sort]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        return new Response($pdfOutput, 200, ['Content-Type' => 'application/pdf']);
    }

    #[Route('/new', name: 'admin_treatment_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $record = new Treatment();
        $form = $this->createForm(TreatmentType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($record);
            $em->flush();

            return $this->redirectToRoute('admin_treatment');
        }

        return $this->render('admin/treatment/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_treatment_show', requirements: ['id' => '\\d+'])]
    public function show(Treatment $record): Response
    {
        return $this->render('admin/treatment/show.html.twig', [
            'record' => $record,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_treatment_edit')]
    public function edit(Request $request, Treatment $record, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(TreatmentType::class, $record);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            return $this->redirectToRoute('admin_treatment');
        }

        return $this->render('admin/treatment/edit.html.twig', [
            'form' => $form->createView(),
            'record' => $record,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_treatment_delete', methods: ['POST'])]
    public function delete(Request $request, Treatment $record, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$record->getId(), $request->request->get('_token'))) {
            $em->remove($record);
            $em->flush();
        }

        return $this->redirectToRoute('admin_treatment');
    }
}
