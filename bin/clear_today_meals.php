#!/usr/bin/env php
<?php
/**
 * Clear Today's Meal Data Script
 * Vide la base de données ML - supprime tous les repas/calories du jour
 * Réinitialise comme si aucun repas n'avait été pris aujourd'hui
 */

require_once __DIR__.'/config/bootstrap.php';

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

// Get the DI container
$container = require __DIR__.'/config/bootstrap.php';
$doctrine = $container->get('doctrine');
$entityManager = $doctrine->getManager();

$output = new class {
    public function write($msg) {
        echo $msg . "\n";
    }
};

try {
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $tomorrow = clone $today;
    $tomorrow->modify('+1 day');

    $output->write("\n<info>=== Nettoyage Base de Données ML ===</info>");
    $output->write("Date: " . $today->format('Y-m-d'));
    $output->write("");

    // 1. Supprimer SuiviRepas du jour
    $output->write("<comment>1. Suppression SuiviRepas (Meal tracking)...</comment>");
    $qb = $entityManager->createQueryBuilder();
    $query = $qb
        ->delete('App\Entity\SuiviRepas', 'sr')
        ->where('sr.dateRepas >= :today')
        ->andWhere('sr.dateRepas < :tomorrow')
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->getQuery();
    
    $count1 = $query->execute();
    $output->write("   ✓ Supprimé: $count1 entrées SuiviRepas");

    // 2. Supprimer BeverageLog du jour
    $output->write("<comment>2. Suppression BeverageLog (Drink tracking)...</comment>");
    $qb = $entityManager->createQueryBuilder();
    $query = $qb
        ->delete('App\Entity\BeverageLog', 'bl')
        ->where('bl.consumedAt >= :today')
        ->andWhere('bl.consumedAt < :tomorrow')
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->getQuery();
    
    $count2 = $query->execute();
    $output->write("   ✓ Supprimé: $count2 entrées BeverageLog");

    // 3. Réinitialiser NutritionJournal si utilisé
    $output->write("<comment>3. Nettoyage NutritionJournal (si existe)...</comment>");
    try {
        $qb = $entityManager->createQueryBuilder();
        $query = $qb
            ->delete('App\Entity\NutritionJournal', 'nj')
            ->where('nj.date >= :today')
            ->andWhere('nj.date < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery();
        
        $count3 = $query->execute();
        $output->write("   ✓ Supprimé: $count3 entrées NutritionJournal");
    } catch (\Exception $e) {
        $output->write("   ⚠ NutritionJournal non trouvé (normal si non utilisé)");
        $count3 = 0;
    }

    // 4. Réinitialiser HealthJournal (si utilisé)
    $output->write("<comment>4. Nettoyage HealthJournal (si existe)...</comment>");
    try {
        $qb = $entityManager->createQueryBuilder();
        $query = $qb
            ->delete('App\Entity\HealthJournal', 'hj')
            ->where('hj.date >= :today')
            ->andWhere('hj.date < :tomorrow')
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery();
        
        $count4 = $query->execute();
        $output->write("   ✓ Supprimé: $count4 entrées HealthJournal");
    } catch (\Exception $e) {
        $output->write("   ⚠ HealthJournal non trouvé");
        $count4 = 0;
    }

    $totalDeleted = $count1 + $count2 + $count3 + $count4;
    
    $output->write("");
    $output->write("<info>✅ Nettoyage terminé!</info>");
    $output->write("<info>Total supprimé: $totalDeleted entrées</info>");
    $output->write("");
    $output->write("<fg=green>Base de données ML réinitialisée comme si aucun repas n'avait été pris aujourd'hui</>");
    $output->write("<fg=green>Calories: 0</>");
    $output->write("<fg=green>Boissons: 0</>");
    $output->write("");

} catch (\Exception $e) {
    $output->write("<error>❌ Erreur: " . $e->getMessage() . "</error>");
    exit(1);
}

exit(0);
