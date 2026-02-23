<?php

namespace App\Command;

use App\Entity\SuiviRepas;
use App\Entity\BeverageLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTime;

#[AsCommand(
    name: 'app:clear-today-meals',
    description: 'Vide la base de données ML - supprime tous les repas/calories du jour'
)]
class ClearTodayMealsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force sans confirmation')
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'User ID (optionnel - nettoie tous si non spécifié)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $today = new DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        $io->section('🧹 Nettoyage Base de Données ML');
        $io->info('Date: ' . $today->format('Y-m-d'));

        if (!$input->getOption('force')) {
            $io->warning('⚠️  Ceci supprimera tous les repas/boissons du jour!');
            if (!$io->confirm('Continuer?')) {
                $io->info('Annulé.');
                return Command::SUCCESS;
            }
        }

        try {
            $userId = $input->getOption('user');

            // 1. Supprimer SuiviRepas
            $io->writeln('<comment>1️⃣  Suppression SuiviRepas (Repas)...</comment>');
            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(SuiviRepas::class, 'sr')
                ->where('sr.dateRepas >= :today')
                ->andWhere('sr.dateRepas < :tomorrow')
                ->setParameter('today', $today)
                ->setParameter('tomorrow', $tomorrow);

            if ($userId) {
                $qb->andWhere('sr.senior = :userId')
                   ->setParameter('userId', $userId);
            }

            $count1 = $qb->getQuery()->execute();
            $io->success("   ✓ Supprimé: $count1 repas");

            // 2. Supprimer BeverageLog
            $io->writeln('<comment>2️⃣  Suppression BeverageLog (Boissons)...</comment>');
            $qb = $this->entityManager->createQueryBuilder();
            $qb->delete(BeverageLog::class, 'bl')
                ->where('bl.consumedAt >= :today')
                ->andWhere('bl.consumedAt < :tomorrow')
                ->setParameter('today', $today)
                ->setParameter('tomorrow', $tomorrow);

            if ($userId) {
                $qb->andWhere('bl.user = :userId')
                   ->setParameter('userId', $userId);
            }

            $count2 = $qb->getQuery()->execute();
            $io->success("   ✓ Supprimé: $count2 boissons");

            $totalDeleted = $count1 + $count2;

            $io->section('✅ Nettoyage Terminé');
            $io->success('Total supprimé: ' . $totalDeleted . ' entrées');
            
            $io->block([
                '🍽️  Calories: 0 kcal',
                '💧 Boissons: 0 ml',
                '📊 État: Réinitialisé comme neuf'
            ], null, 'fg=green;bg=default');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
