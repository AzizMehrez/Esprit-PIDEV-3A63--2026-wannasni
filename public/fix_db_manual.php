<?php

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

return function (array $context) {
    $kernel = new Kernel($context['APP_ENV'], (bool)$context['APP_DEBUG']);
    $kernel->boot();

    $em = $kernel->getContainer()->get('doctrine')->getManager();
    $conn = $em->getConnection();

    $statements = [
        // SERVICE REQUEST
        "ALTER TABLE service_request ADD technicien_id INT DEFAULT NULL",
        "ALTER TABLE service_request ADD technicien_nom VARCHAR(100) DEFAULT NULL",
        "ALTER TABLE service_request ADD notes_admin LONGTEXT DEFAULT NULL",
        "ALTER TABLE service_request ADD date_assignation DATETIME DEFAULT NULL",
        "ALTER TABLE service_request ADD date_debut DATETIME DEFAULT NULL",
        "ALTER TABLE service_request ADD date_fin DATETIME DEFAULT NULL",

        // INTERVENTION
        "ALTER TABLE intervention ADD employe_id INT DEFAULT NULL",
        "ALTER TABLE intervention ADD technicien_nom VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE intervention ADD technicien_email VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE intervention ADD technicien_telephone VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE intervention ADD notes LONGTEXT DEFAULT NULL",

        // Check for date_debut/date_fin in INTERVENTION too (crucial)
        "ALTER TABLE intervention ADD date_debut DATETIME DEFAULT NULL",
        "ALTER TABLE intervention ADD date_fin DATETIME DEFAULT NULL",

        // Other fields that might be missing in Intervention
        "ALTER TABLE intervention ADD types_services VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE intervention ADD competences VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE intervention ADD tarif_horaire DECIMAL(10, 2) DEFAULT NULL",
        "ALTER TABLE intervention ADD zone_intervention VARCHAR(255) DEFAULT NULL",
        "ALTER TABLE intervention ADD heures_travail INT DEFAULT NULL",
        "ALTER TABLE intervention ADD statut_actuel VARCHAR(20) DEFAULT 'en_attente'"
    ];

    foreach ($statements as $sql) {
        try {
            echo "Executing: $sql\n";
            $conn->executeStatement($sql);
            echo "Success.\n";
        }
        catch (\Exception $e) {
            // Ignore "Duplicate column name" errors (Code 1060 in MySQL)
            if (strpos($e->getMessage(), '1060') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "Column already exists (Skipped).\n";
            }
            else {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }
};
