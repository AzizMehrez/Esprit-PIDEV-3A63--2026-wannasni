<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:send-meal-reminders',
    description: 'Envoie des rappels aux seniors pour scanner leurs repas',
)]
class SendMealRemindersCommand extends Command
{
    private $userRepository;
    private $mailer;

    public function __construct(UserRepository $userRepository, MailerInterface $mailer)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Find seniors who haven't logged a meal today (Simplification: just send to all seniors)
        // In real app: SQL query to check absence of SuiviRepas for today
        $seniors = $this->userRepository->findAll(); 

        $count = 0;
        foreach ($seniors as $senior) {
            if (!$senior->getEmail()) continue;
             // Check role
             // if (!in_array('ROLE_SENIOR', $senior->getRoles())) continue;

            $email = (new Email())
                ->from('no-reply@wannasni.com')
                ->to($senior->getEmail())
                ->subject('N\'oubliez pas votre repas !')
                ->html('<p>Bonjour ' . $senior->getFirstName() . ',<br>Avez-vous bien mangé ce midi ? N\'oubliez pas de scanner votre repas pour votre suivi nutritionnel !</p>');

            try {
                $this->mailer->send($email);
                $count++;
            } catch (\Exception $e) {
                $io->warning("Echec envoi pour {$senior->getEmail()}");
            }
        }

        $io->success("$count rappels envoyés.");

        return Command::SUCCESS;
    }
}
