<?php

use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);

    return new class($kernel) {
        public function __construct(private Kernel $kernel) {}

        public function run(): void
        {
            $this->kernel->boot();
            $container = $this->kernel->getContainer();
            /** @var EntityManagerInterface $em */
            $em = $container->get('doctrine')->getManager();
            /** @var UserPasswordHasherInterface $hasher */
            $hasher = $container->get('security.user_password_hasher');

            $repo = $em->getRepository(User::class);
            $existing = $repo->findOneBy(['email' => 'tech@wannasni.com']);

            if (!$existing) {
                $user = new User();
                $user->setEmail('tech@wannasni.com');
                $user->setFirstName('Jean');
                $user->setLastName('Technicien');
                $user->setRoles(['ROLE_TECHNICIAN']);
                $user->setPassword($hasher->hashPassword($user, 'password123'));
                $user->setSpecialite('Plomberie');
                $user->setTarifHoraire(45.00);
                $user->setDisponible(true);
                $user->setPhone('0600000001');

                $em->persist($user);
                $em->flush();
                echo "Created Technician User.\n";
            } else {
                echo "Technician User already exists.\n";
            }
        }
    }->run();
};
