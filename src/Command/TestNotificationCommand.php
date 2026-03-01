<?php

namespace App\Command;

use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-notification')]
class TestNotificationCommand extends Command
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Creating test notification...');
        
        try {
            $n = $this->notificationService->create('test', 'This is a test notification', 999);
            $output->writeln('Notification created with ID: ' . $n->getId());
        } catch (\Exception $e) {
            $output->writeln('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
