<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add all missing tables for the WANNASNI Senior Care Platform
 */
final class Version20260213120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add all application tables: activites, service_request, health_journal, notification, nutrition_journal, nutrition_plan, participations, intervention, demande_regime, regime_prescrit, treatment';
    }

    public function up(Schema $schema): void
    {
        // Table: activites (Activity)
        $this->addSql('CREATE TABLE IF NOT EXISTS activites (
            id INT AUTO_INCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            type VARCHAR(50) NOT NULL DEFAULT \'social\',
            start_time DATETIME NOT NULL,
            end_time DATETIME DEFAULT NULL,
            location VARCHAR(255) DEFAULT NULL,
            max_participants INT DEFAULT NULL,
            current_participants INT NOT NULL DEFAULT 0,
            coach_id INT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: service_request (ServiceRequest)
        $this->addSql('CREATE TABLE IF NOT EXISTS service_request (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            senior_telephone VARCHAR(20) DEFAULT NULL,
            senior_email VARCHAR(100) DEFAULT NULL,
            type_service VARCHAR(100) NOT NULL,
            description LONGTEXT NOT NULL,
            adresse VARCHAR(255) DEFAULT NULL,
            ville VARCHAR(100) DEFAULT NULL,
            code_postal VARCHAR(20) DEFAULT NULL,
            niveau_urgence VARCHAR(50) NOT NULL DEFAULT \'normale\',
            date_souhaitee DATETIME DEFAULT NULL,
            budget_minimum DECIMAL(10, 2) DEFAULT NULL,
            budget_maximum DECIMAL(10, 2) DEFAULT NULL,
            notifier_proches TINYINT(1) NOT NULL DEFAULT 0,
            statut VARCHAR(50) NOT NULL DEFAULT \'pending\',
            created_at DATETIME NOT NULL,
            technicien_id INT DEFAULT NULL,
            technicien_nom VARCHAR(100) DEFAULT NULL,
            notes_admin LONGTEXT DEFAULT NULL,
            date_assignation DATETIME DEFAULT NULL,
            date_debut DATETIME DEFAULT NULL,
            date_fin DATETIME DEFAULT NULL,
            INDEX IDX_SR_USER (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_SERVICE_REQUEST_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: health_journal (HealthJournal)
        $this->addSql('CREATE TABLE IF NOT EXISTS health_journal (
            id INT AUTO_INCREMENT NOT NULL,
            senior_id INT DEFAULT NULL,
            date DATETIME NOT NULL,
            humeur VARCHAR(100) DEFAULT NULL,
            qualite_sommeil VARCHAR(100) DEFAULT NULL,
            appetit VARCHAR(100) DEFAULT NULL,
            niveau_douleur INT DEFAULT NULL,
            symptomes LONGTEXT DEFAULT NULL,
            tension_arterielle VARCHAR(50) DEFAULT NULL,
            frequence_cardiaque INT DEFAULT NULL,
            temperature DOUBLE PRECISION DEFAULT NULL,
            medicaments_pris LONGTEXT DEFAULT NULL,
            activite_physique VARCHAR(255) DEFAULT NULL,
            hydratation VARCHAR(50) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            INDEX IDX_HJ_SENIOR (senior_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_HEALTH_JOURNAL_USER FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: notification (Notification)
        $this->addSql('CREATE TABLE IF NOT EXISTS notification (
            id INT AUTO_INCREMENT NOT NULL,
            type VARCHAR(100) NOT NULL,
            message LONGTEXT NOT NULL,
            related_id INT DEFAULT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: nutrition_journal (NutritionJournal)
        $this->addSql('CREATE TABLE IF NOT EXISTS nutrition_journal (
            id INT AUTO_INCREMENT NOT NULL,
            senior_id INT DEFAULT NULL,
            date DATETIME NOT NULL,
            meal_type VARCHAR(50) DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            calories INT DEFAULT NULL,
            water_intake_ml DOUBLE PRECISION DEFAULT NULL,
            INDEX IDX_NJ_SENIOR (senior_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_NUTRITION_JOURNAL_USER FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: nutrition_plan (NutritionPlan)
        $this->addSql('CREATE TABLE IF NOT EXISTS nutrition_plan (
            id INT AUTO_INCREMENT NOT NULL,
            senior_id INT DEFAULT NULL,
            daily_calorie_target INT DEFAULT NULL,
            dietary_restrictions JSON DEFAULT NULL,
            allergies JSON DEFAULT NULL,
            start_date DATETIME DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            INDEX IDX_NP_SENIOR (senior_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_NUTRITION_PLAN_USER FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: participations (Participation)
        $this->addSql('CREATE TABLE IF NOT EXISTS participations (
            id INT AUTO_INCREMENT NOT NULL,
            activity_id INT NOT NULL,
            senior_id INT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT \'registered\',
            registration_date DATETIME NOT NULL,
            rating INT DEFAULT NULL,
            feedback LONGTEXT DEFAULT NULL,
            title VARCHAR(255) DEFAULT NULL,
            participant_id INT DEFAULT NULL,
            registered_at DATETIME DEFAULT NULL,
            registration_method VARCHAR(50) DEFAULT NULL,
            feedback_rating INT DEFAULT NULL,
            feedback_comment LONGTEXT DEFAULT NULL,
            mood_before INT DEFAULT NULL,
            mood_after INT DEFAULT NULL,
            problems_encountered LONGTEXT DEFAULT NULL,
            recommend_to_friends TINYINT(1) DEFAULT NULL,
            photo_urls LONGTEXT DEFAULT NULL,
            presence_confirmation_date DATETIME DEFAULT NULL,
            has_certificate TINYINT(1) DEFAULT NULL,
            share_with_family VARCHAR(50) DEFAULT NULL,
            INDEX IDX_PART_ACTIVITY (activity_id),
            INDEX IDX_PART_SENIOR (senior_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: intervention (Intervention)
        $this->addSql('CREATE TABLE IF NOT EXISTS intervention (
            id INT AUTO_INCREMENT NOT NULL,
            service_request_id INT DEFAULT NULL,
            employe_id INT DEFAULT NULL,
            types_services VARCHAR(255) DEFAULT NULL,
            competences VARCHAR(255) DEFAULT NULL,
            tarif_horaire DECIMAL(10, 2) DEFAULT NULL,
            zone_intervention VARCHAR(255) DEFAULT NULL,
            heures_travail INT DEFAULT NULL,
            statut_actuel VARCHAR(20) NOT NULL DEFAULT \'en_attente\',
            technicien_nom VARCHAR(255) DEFAULT NULL,
            technicien_email VARCHAR(255) DEFAULT NULL,
            technicien_telephone VARCHAR(255) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            date_creation DATETIME DEFAULT NULL,
            date_debut DATETIME DEFAULT NULL,
            date_fin DATETIME DEFAULT NULL,
            payment_status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            payment_date DATETIME DEFAULT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            INDEX IDX_INT_SR (service_request_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_INTERVENTION_SR FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: demande_regime (DemandeRegime)
        $this->addSql('CREATE TABLE IF NOT EXISTS demande_regime (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            nutritionniste_id INT DEFAULT NULL,
            code_barres_photo VARCHAR(255) DEFAULT NULL,
            code_barres_numero VARCHAR(20) DEFAULT NULL,
            produit_analyse JSON DEFAULT NULL,
            senior_id INT NOT NULL,
            date_demande DATETIME NOT NULL,
            statut VARCHAR(20) NOT NULL DEFAULT \'en_attente\',
            type_regime_souhaite VARCHAR(30) NOT NULL,
            objectif_principal VARCHAR(30) NOT NULL,
            allergies LONGTEXT DEFAULT NULL,
            intolerances LONGTEXT DEFAULT NULL,
            habitudes_alimentaires LONGTEXT DEFAULT NULL,
            budget_mensuel INT NOT NULL,
            date_traitement DATETIME DEFAULT NULL,
            INDEX IDX_DR_USER (user_id),
            INDEX IDX_DR_NUTRITIONNISTE (nutritionniste_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_DEMANDE_REGIME_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL,
            CONSTRAINT FK_DEMANDE_REGIME_NUTRITIONNISTE FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: regime_prescrit (RegimePrescrit)
        $this->addSql('CREATE TABLE IF NOT EXISTS regime_prescrit (
            id INT AUTO_INCREMENT NOT NULL,
            demande_id INT NOT NULL,
            user_id INT DEFAULT NULL,
            nutritionniste_id INT DEFAULT NULL,
            senior_id INT NOT NULL,
            date_prescription DATETIME NOT NULL,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            type_regime VARCHAR(30) NOT NULL,
            calories_journalieres INT NOT NULL,
            repas_par_jour VARCHAR(10) NOT NULL DEFAULT \'3\',
            aliments_recommandes LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\',
            aliments_interdits LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\',
            hydratation_quotidienne INT NOT NULL,
            recommandations_speciales LONGTEXT DEFAULT NULL,
            suivi_requis VARCHAR(20) NOT NULL DEFAULT \'hebdomadaire\',
            INDEX IDX_RP_DEMANDE (demande_id),
            INDEX IDX_RP_USER (user_id),
            INDEX IDX_RP_NUTRITIONNISTE (nutritionniste_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_REGIME_PRESCRIT_DEMANDE FOREIGN KEY (demande_id) REFERENCES demande_regime (id) ON DELETE CASCADE,
            CONSTRAINT FK_REGIME_PRESCRIT_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL,
            CONSTRAINT FK_REGIME_PRESCRIT_NUTRITIONNISTE FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: treatment (Treatment)
        $this->addSql('CREATE TABLE IF NOT EXISTS treatment (
            id INT AUTO_INCREMENT NOT NULL,
            senior_id INT DEFAULT NULL,
            docteur_id INT DEFAULT NULL,
            date_prescription DATETIME NOT NULL,
            medicaments LONGTEXT NOT NULL,
            posologie VARCHAR(255) DEFAULT NULL,
            frequence VARCHAR(100) DEFAULT NULL,
            date_debut DATETIME NOT NULL,
            date_fin DATETIME DEFAULT NULL,
            instructions LONGTEXT DEFAULT NULL,
            renouvellements INT DEFAULT NULL,
            statut VARCHAR(50) DEFAULT NULL,
            effets_secondaires LONGTEXT DEFAULT NULL,
            INDEX IDX_TREAT_SENIOR (senior_id),
            INDEX IDX_TREAT_DOCTEUR (docteur_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_TREATMENT_SENIOR FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE SET NULL,
            CONSTRAINT FK_TREATMENT_DOCTEUR FOREIGN KEY (docteur_id) REFERENCES `user` (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS regime_prescrit');
        $this->addSql('DROP TABLE IF EXISTS demande_regime');
        $this->addSql('DROP TABLE IF EXISTS intervention');
        $this->addSql('DROP TABLE IF EXISTS participations');
        $this->addSql('DROP TABLE IF EXISTS nutrition_plan');
        $this->addSql('DROP TABLE IF EXISTS nutrition_journal');
        $this->addSql('DROP TABLE IF EXISTS notification');
        $this->addSql('DROP TABLE IF EXISTS health_journal');
        $this->addSql('DROP TABLE IF EXISTS service_request');
        $this->addSql('DROP TABLE IF EXISTS activites');
        $this->addSql('DROP TABLE IF EXISTS treatment');
    }
}
