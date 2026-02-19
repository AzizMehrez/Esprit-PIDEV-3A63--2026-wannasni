<?php

namespace App\Controller\Front;

use App\Entity\Treatment;
use App\Form\TreatmentType;
use App\Repository\TreatmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/treatment', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_USER')]
final class TreatmentController extends AbstractController
{
    #[Route(name: 'app_treatment_index', methods: ['GET'])]
    public function index(Request $request, TreatmentRepository $treatmentRepository): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $treatmentRepository->createQueryBuilder('t')
            ->andWhere('t.senior = :senior')
            ->setParameter('senior', $this->getUser());

        if ($q) {
            $qb->andWhere('t.medicaments LIKE :q OR t.instructions LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('t.datePrescription', $sort === 'asc' ? 'ASC' : 'DESC');

        $treatments = $qb->getQuery()->getResult();

        return $this->render('treatment/index.html.twig', [
            'treatments' => $treatments,
            'q' => $q,
            'sort' => $sort,
        ]);
    }

    #[Route('/new', name: 'app_treatment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $treatment = new Treatment();
        // set senior as current user by default
        $treatment->setSenior($this->getUser());

        $form = $this->createForm(TreatmentType::class, $treatment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($treatment);
            $entityManager->flush();

            return $this->redirectToRoute('app_treatment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('treatment/new.html.twig', [
            'treatment' => $treatment,
            'form' => $form,
        ]);
    }

    #[Route('/export', name: 'app_treatment_export')]
    public function export(Request $request, TreatmentRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $repo->createQueryBuilder('t')
            ->andWhere('t.senior = :senior')
            ->setParameter('senior', $this->getUser());

        if ($q) {
            $qb->andWhere('t.medicaments LIKE :q OR t.instructions LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('t.datePrescription', $sort === 'asc' ? 'ASC' : 'DESC');
        $records = $qb->getQuery()->getResult();

        $html = $this->renderView('treatment/pdf.html.twig', ['records' => $records, 'q' => $q, 'sort' => $sort]);

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        return new Response($pdfOutput, 200, ['Content-Type' => 'application/pdf']);
    }

    #[Route('/{id}', name: 'app_treatment_show', methods: ['GET'])]
    public function show(Treatment $treatment): Response
    {
        return $this->render('treatment/show.html.twig', [
            'treatment' => $treatment,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_treatment_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Treatment $treatment, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TreatmentType::class, $treatment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_treatment_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('treatment/edit.html.twig', [
            'treatment' => $treatment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_treatment_delete', methods: ['POST'])]
    public function delete(Request $request, Treatment $treatment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$treatment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($treatment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_treatment_index', [], Response::HTTP_SEE_OTHER);
    }
}
