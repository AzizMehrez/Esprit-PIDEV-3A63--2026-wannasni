<?php

namespace App\Controller;

use App\Entity\Treatment;
use App\Form\TreatmentType;
use App\Repository\TreatmentRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/treatments')]
class AdminHealthController extends AbstractController
{
    #[Route('/', name: 'app_admin_health_index', methods: ['GET'])]
    public function index(Request $request, TreatmentRepository $treatmentRepository): Response
    {
        $searchQuery = $request->query->get('q', '');
        $sort = $request->query->get('sort', 'id');
        $direction = $request->query->get('direction', 'asc');

        $treatments = $treatmentRepository->search($searchQuery, $sort, $direction);

        return $this->render('admin/treatment/index.html.twig', [
            'treatments' => $treatments,
            'searchQuery' => $searchQuery,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_health_show', methods: ['GET'])]
    public function show(Treatment $treatment): Response
    {
        return $this->render('admin/treatment/show.html.twig', [
            'treatment' => $treatment,
        ]);
    }

    #[Route('/pdf', name: 'app_admin_health_pdf', methods: ['GET'])]
    public function pdf(TreatmentRepository $treatmentRepository): Response
    {
        // Configure Dompdf according to your needs
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        
        // Instantiate Dompdf with our options
        $dompdf = new Dompdf($pdfOptions);
        
        // Retrieve the HTML generated in our twig file
        $html = $this->renderView('admin/treatment/pdf.html.twig', [
            'treatments' => $treatmentRepository->findAll(),
        ]);
        
        // Load HTML to Dompdf
        $dompdf->loadHtml($html);
        
        // (Optional) Setup the paper size and orientation 'portrait' or 'portrait'
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser (force download)
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="treatments.pdf"',
        ]);
    }

    #[Route('/new', name: 'app_admin_health_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TreatmentRepository $treatmentRepository): Response
    {
        $treatment = new Treatment();
        $form = $this->createForm(TreatmentType::class, $treatment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $treatmentRepository->save($treatment, true);

            return $this->redirectToRoute('app_admin_health_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/treatment/new.html.twig', [
            'treatment' => $treatment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_health_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Treatment $treatment, TreatmentRepository $treatmentRepository): Response
    {
        $form = $this->createForm(TreatmentType::class, $treatment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $treatmentRepository->save($treatment, true);

            return $this->redirectToRoute('app_admin_health_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/treatment/edit.html.twig', [
            'treatment' => $treatment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_health_delete', methods: ['POST'])]
    public function delete(Request $request, Treatment $treatment, TreatmentRepository $treatmentRepository): Response
    {
        if ($this->isCsrfTokenValid('delete'.$treatment->getId(), $request->request->get('_token'))) {
            $treatmentRepository->remove($treatment, true);
        }

        return $this->redirectToRoute('app_admin_health_index', [], Response::HTTP_SEE_OTHER);
    }
}
