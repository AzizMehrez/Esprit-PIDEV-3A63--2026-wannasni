<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create a new admin user',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Create Admin User');
        
        // Get email
        $email = $io->ask('Email address', null, function ($value) {
            if (empty($value) || !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Please provide a valid email address.');
            }
            
            // Check if email already exists
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $value]);
            if ($existingUser) {
                throw new \RuntimeException('A user with this email already exists.');
            }
            
            return $value;
        });
        
        // Get first name
        $firstName = $io->ask('First name');
        
        // Get last name
        $lastName = $io->ask('Last name');
        
        // Get password
        $password = $io->askHidden('Password (min 6 characters)', function ($value) {
            if (empty($value) || strlen($value) < 6) {
                throw new \RuntimeException('Password must be at least 6 characters long.');
            }
            return $value;
        });
        
        // Create admin user
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        $io->success(sprintf('Admin user "%s" created successfully!', $email));
        $io->note('You can now login at /admin/login');
        
        return Command::SUCCESS;
    }
}
