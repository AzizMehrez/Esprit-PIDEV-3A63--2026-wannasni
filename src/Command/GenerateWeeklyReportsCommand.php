<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\ReportGenerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-weekly-reports',
    description: 'Génère les rapports hebdomadaires pour les seniors',
)]
class GenerateWeeklyReportsCommand extends Command
{
    private $userRepository;
    private $reportService;

    public function __construct(UserRepository $userRepository, ReportGenerationService $reportService)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->reportService = $reportService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startDate = new \DateTime('-7 days');
        $endDate = new \DateTime('now');

        // Logic to find seniors (assuming ROLE_SENIOR or similar, or all users for now)
        // ideally we filter by those who have active regime
        $seniors = $this->userRepository->findAll(); // Improve filter in real app

        $count = 0;
        foreach ($seniors as $senior) {
            // Check if user is senior (simplified check)
            if (!in_array('ROLE_SENIOR', $senior->getRoles()) && !in_array('ROLE_USER', $senior->getRoles())) {
                continue;
            }

            try {
                $this->reportService->generateWeeklyReport($senior, $startDate, $endDate);
                $count++;
            } catch (\Exception $e) {
                $io->error("Erreur pour user {$senior->getId()}: " . $e->getMessage());
            }
        }

        $io->success("$count rapports hebdomadaires générés.");

        return Command::SUCCESS;
    }
}
