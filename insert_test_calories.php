<?php
require_once __DIR__.'/vendor/autoload.php';
use App\Kernel;
use App\Entity\SuiviRepas;
use App\Entity\User;
use App\Entity\RegimePrescrit;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();
$em = $container->get('doctrine')->getManager();

$user = $em->getRepository(User::class)->find(1);
$regime = $em->getRepository(RegimePrescrit::class)->find(4);

if (!$user || !$regime) {
    echo "User or Regime not found\n";
    exit(1);
}

$suivi = new SuiviRepas();
$suivi->setSenior($user);
$suivi->setRegimePrescrit($regime);
$suivi->setAlimentsIdentifies(['Triple de 3500 kcal pour TEST']);
$suivi->setCaloriesCalculees(10500); // 3 * 3500
$suivi->setEstConforme(false);
$suivi->setDateRepas(new \DateTime());
$suivi->setCommentairesIA('Simulation manuelle de dépassement critique pour test Twilio');

$em->persist($suivi);
$em->flush();

echo "Success: Record inserted for user {$user->getEmail()} with 10500 kcal.\n";
