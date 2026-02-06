<?php

namespace App\Controller\Front;

use App\Entity\HealthJournal;
use App\Form\HealthJournalType;
use App\Repository\HealthJournalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
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
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $repo->createQueryBuilder('h')
            ->andWhere('h.senior = :senior')
            ->setParameter('senior', $this->getUser());

        if ($q) {
            $qb->andWhere('h.notes LIKE :q OR h.humeur LIKE :q OR h.tensionArterielle LIKE :q OR h.medicamentsPris LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('h.date', $sort === 'asc' ? 'ASC' : 'DESC');

        $healthRecords = $qb->getQuery()->getResult();

        return $this->render('front/health/index.html.twig', [
            'health_records' => $healthRecords,
            'q' => $q,
            'sort' => $sort,
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

            return $this->redirectToRoute('app_my_health');
        }

        return $this->render('front/health/add.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/export', name: 'app_my_health_export')]
    public function export(Request $request, HealthJournalRepository $repo): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'desc');

        $qb = $repo->createQueryBuilder('h')
            ->andWhere('h.senior = :senior')
            ->setParameter('senior', $this->getUser());

        if ($q) {
            $qb->andWhere('h.notes LIKE :q OR h.humeur LIKE :q OR h.tensionArterielle LIKE :q OR h.medicamentsPris LIKE :q')
               ->setParameter('q', '%'.trim($q).'%');
        }

        $qb->orderBy('h.date', $sort === 'asc' ? 'ASC' : 'DESC');
        $records = $qb->getQuery()->getResult();

        // Render HTML
        $html = $this->renderView('front/health/pdf.html.twig', ['records' => $records]);

        // Configure Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        return new Response($pdfOutput, 200, ['Content-Type' => 'application/pdf']);
    }
}
