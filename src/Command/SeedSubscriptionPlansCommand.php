<?php

namespace App\Command;

use App\Service\SubscriptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-subscription-plans',
    description: 'Seed the 3 subscription plans (Essentiel, Confort, Premium)'
)]
class SeedSubscriptionPlansCommand extends Command
{
    public function __construct(
        private SubscriptionService $subscriptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seeding Subscription Plans');

        $this->subscriptionService->seedPlans();

        $io->success('Les 3 plans SilverAssist Premium ont été créés avec succès !');
        $io->listing([
            'Essentiel — 9,99€/mois (10% réduction, 1 maintenance/an)',
            'Confort   — 19,99€/mois (20% réduction, 2 maintenances/an, priorité urgences)',
            'Premium   — 34,99€/mois (30% réduction, maintenance mensuelle, technicien dédié)',
        ]);

        return Command::SUCCESS;
    }
}
