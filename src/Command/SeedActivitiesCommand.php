<?php

namespace App\Command;

use App\Entity\Activity;
use App\Entity\Participation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-activities',
    description: 'Seed database with sample activities and participations'
)]
class SeedActivitiesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Seeding Activities and Participations');

        // Create sample activities
        $activities = [
            [
                'title' => 'Yoga doux du matin',
                'description' => 'Séance de yoga adaptée aux seniors pour améliorer la souplesse',
                'type' => 'physical',
                'location' => 'Salle de sport - Centre WANNASNI',
                'startTime' => new \DateTime('+2 days 10:00'),
                'endTime' => new \DateTime('+2 days 11:00'),
                'maxParticipants' => 15,
            ],
            [
                'title' => 'Atelier mémoire',
                'description' => 'Exercices cognitifs pour stimuler la mémoire',
                'type' => 'educational',
                'location' => 'Salle de formation',
                'startTime' => new \DateTime('+3 days 14:00'),
                'endTime' => new \DateTime('+3 days 15:30'),
                'maxParticipants' => 12,
            ],
            [
                'title' => 'Promenade au parc',
                'description' => 'Marche douce dans le parc municipal',
                'type' => 'physical',
                'location' => 'Parc municipal',
                'startTime' => new \DateTime('+4 days 09:00'),
                'endTime' => new \DateTime('+4 days 10:30'),
                'maxParticipants' => 20,
            ],
            [
                'title' => 'Atelier peinture',
                'description' => 'Expression artistique à travers la peinture',
                'type' => 'cultural',
                'location' => 'Atelier d\'art',
                'startTime' => new \DateTime('+5 days 15:00'),
                'endTime' => new \DateTime('+5 days 17:00'),
                'maxParticipants' => 10,
            ],
            [
                'title' => 'Café social',
                'description' => 'Moment convivial autour d\'un café',
                'type' => 'social',
                'location' => 'Cafétéria WANNASNI',
                'startTime' => new \DateTime('+1 day 16:00'),
                'endTime' => new \DateTime('+1 day 17:00'),
                'maxParticipants' => 25,
            ],
        ];

        foreach ($activities as $activityData) {
            $activity = new Activity();
            $activity->setTitle($activityData['title']);
            $activity->setDescription($activityData['description']);
            $activity->setType($activityData['type']);
            $activity->setLocation($activityData['location']);
            $activity->setStartTime($activityData['startTime']);
            $activity->setEndTime($activityData['endTime']);
            $activity->setMaxParticipants($activityData['maxParticipants']);
            $activity->setCurrentParticipants(rand(0, $activityData['maxParticipants'] - 5));
            $activity->setIsActive(true);

            $this->em->persist($activity);
            $io->success('Created activity: ' . $activity->getTitle());
        }

        $this->em->flush();

        $io->success('Successfully seeded ' . count($activities) . ' activities!');
        $io->note('You can now view them in the admin panel or front-end.');

        return Command::SUCCESS;
    }
}
