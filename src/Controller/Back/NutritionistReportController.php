<?php

namespace App\Controller\Back;

use App\Entity\RapportHebdomadaire;
use App\Repository\RapportHebdomadaireRepository;
use App\Service\ReportGenerationService;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{_locale}/nutritionist', requirements: ['_locale' => 'fr|en|ar'])]
#[IsGranted('ROLE_NUTRITIONNISTE')] // Assuming this role exists
class NutritionistReportController extends AbstractController
{
    #[Route('/reports', name: 'app_nutritionist_reports')]
    public function index(RapportHebdomadaireRepository $reportRepo): Response
    {
        $user = $this->getUser();
        // In real app, filter by nutritionist's patients
        $reports = $reportRepo->findBy([], ['dateGeneration' => 'DESC']);

        return $this->render('back/nutritionist/reports.html.twig', [
            'reports' => $reports
        ]);
    }

    #[Route('/report/{id}', name: 'app_nutritionist_report_show')]
    public function show(RapportHebdomadaire $report): Response
    {
        return $this->render('back/nutritionist/report_show.html.twig', [
            'report' => $report
        ]);
    }

    #[Route('/generate-test-report', name: 'app_nutritionist_generate_test')]
    public function generateTest(
        UserRepository $userRepo, 
        ReportGenerationService $reportService
    ): Response
    {
        // Temporary endpoint for testing
        // Find a senior user (e.g., role ROLE_SENIOR or similar logic)
        // For now, grabbing the first user who isn't us
        $senior = $userRepo->findOneBy([]); 
        
        $startDate = new \DateTime('-7 days');
        $endDate = new \DateTime('now');
        
        $report = $reportService->generateWeeklyReport($senior, $startDate, $endDate);
        
        return $this->redirectToRoute('app_nutritionist_report_show', ['id' => $report->getId()]);
    }
}
