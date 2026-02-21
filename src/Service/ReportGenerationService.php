<?php

namespace App\Service;

use App\Entity\RapportHebdomadaire;
use App\Entity\User;
use App\Repository\SuiviRepasRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\AIPrompts;

class ReportGenerationService
{
    private $suiviRepasRepository;
    private $em;
    private $geminiService;

    public function __construct(
        SuiviRepasRepository $suiviRepasRepository,
        EntityManagerInterface $em,
        GeminiService $geminiService
    ) {
        $this->suiviRepasRepository = $suiviRepasRepository;
        $this->em = $em;
        $this->geminiService = $geminiService;
    }

    public function generateWeeklyReport(User $senior, \DateTime $startDate, \DateTime $endDate): RapportHebdomadaire
    {
        $repasList = $this->suiviRepasRepository->createQueryBuilder('s')
            ->where('s.senior = :senior')
            ->andWhere('s.dateRepas BETWEEN :start AND :end')
            ->setParameter('senior', $senior)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        $totalRepas = count($repasList);
        $conformeCount = 0;
        $problemItems = [];

        foreach ($repasList as $repas) {
            if ($repas->isEstConforme()) {
                $conformeCount++;
            }
            // Simple logic to aggregate problematic items (placeholder)
            // In reality, we'd parse the non-conformity reason
        }

        $taux = $totalRepas > 0 ? ($conformeCount / $totalRepas) * 100 : 0;

        $rapport = new RapportHebdomadaire();
        $rapport->setSenior($senior);
        $rapport->setPeriodeDebut($startDate);
        $rapport->setPeriodeFin($endDate);
        $rapport->setTauxConformite($taux);
        $rapport->setAlimentsProblematiques($problemItems);

        // Generative AI for suggestions
        $context = [
            'age' => $senior->getAge() ?? '70', // Fallback age
            'regime' => 'Standard', // Should get from valid regime
            'taux' => round($taux, 1),
            'alimentsProbleme' => implode(', ', $problemItems)
        ];
        
        $suggestions = $this->geminiService->generateText(AIPrompts::SUGGESTIONS_AJUSTEMENT, $context);
        $rapport->setSuggestionsIA($suggestions);
        
        // Find nutritionist (Assuming specific relationship or logic)
        // $rapport->setNutritionniste($nutritionist);

        $this->em->persist($rapport);
        $this->em->flush();

        return $rapport;
    }
}
