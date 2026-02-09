<?php

namespace App\Command;

use App\Entity\Participation;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-test-participations',
    description: 'Create test participation data for testing feedback forms',
)]
class CreateTestParticipationsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get the first user from database
        $users = $this->userRepository->findAll();
        
        if (empty($users)) {
            $io->error('No users found in database. Please create a user first.');
            return Command::FAILURE;
        }

        $user = $users[0];
        $io->info('Creating participations for user: ' . $user->getEmail());

        // Create 3 test participations
        $participationsData = [
            [
                'activityId' => 1,
                'title' => 'Morning Walk',
                'status' => 'présent',
                'daysAgo' => 5
            ],
            [
                'activityId' => 2,
                'title' => 'Memory Games',
                'status' => 'inscrit',
                'daysAgo' => 3
            ],
            [
                'activityId' => 3,
                'title' => 'Yoga Class',
                'status' => 'présent',
                'daysAgo' => 10
            ],
        ];

        foreach ($participationsData as $data) {
            $participation = new Participation();
            $participation->setActivityId($data['activityId']);
            $participation->setSeniorId($user->getId());
            $participation->setStatus($data['status']);
            $participation->setTitle($data['title']);
            $participation->setRegistrationMethod('web');
            
            $registeredAt = new \DateTime();
            $registeredAt->modify('-' . $data['daysAgo'] . ' days');
            $participation->setRegisteredAt($registeredAt);

            $this->entityManager->persist($participation);
            
            $io->success('Created participation: ' . $data['title']);
        }

        $this->entityManager->flush();

        $io->success('All test participations created successfully!');
        $io->note('You can now visit: http://127.0.0.1:8000/fr/participations/history');

        return Command::SUCCESS;
    }
}
