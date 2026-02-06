<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205215140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activites (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(50) NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, max_participants INT DEFAULT NULL, current_participants INT NOT NULL, coach_id INT DEFAULT NULL, is_active TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE participations (id INT AUTO_INCREMENT NOT NULL, senior_id INT DEFAULT NULL, participant_id INT DEFAULT NULL, status VARCHAR(50) NOT NULL, registration_date DATETIME NOT NULL, registered_at DATETIME DEFAULT NULL, registration_method VARCHAR(50) DEFAULT NULL, rating INT DEFAULT NULL, feedback_rating INT DEFAULT NULL, feedback LONGTEXT DEFAULT NULL, feedback_comment LONGTEXT DEFAULT NULL, mood_before INT DEFAULT NULL, mood_after INT DEFAULT NULL, problems_encountered LONGTEXT DEFAULT NULL, recommend_to_friends TINYINT(1) DEFAULT NULL, photo_urls LONGTEXT DEFAULT NULL, presence_confirmation_date DATETIME DEFAULT NULL, has_certificate TINYINT(1) DEFAULT NULL, share_with_family VARCHAR(50) DEFAULT NULL, title VARCHAR(50) DEFAULT NULL, activity_id INT NOT NULL, INDEX IDX_FDC6C6E881C06096 (activity_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, image_profil VARCHAR(255) DEFAULT NULL, date_naissance DATE DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, ville VARCHAR(100) DEFAULT NULL, code_postal VARCHAR(20) DEFAULT NULL, pays VARCHAR(100) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, reset_token VARCHAR(100) DEFAULT NULL, reset_token_expires_at DATETIME DEFAULT NULL, verification_code VARCHAR(10) DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE participations ADD CONSTRAINT FK_FDC6C6E881C06096 FOREIGN KEY (activity_id) REFERENCES activites (id)');
        $this->addSql('DROP TABLE health_journal');
        $this->addSql('DROP TABLE treatment');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE health_journal (id INT AUTO_INCREMENT NOT NULL, senior_id INT NOT NULL, date DATETIME NOT NULL, temperature DOUBLE PRECISION DEFAULT \'NULL\', notes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, humeur VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, qualite_sommeil VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, appetit VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, niveau_douleur INT DEFAULT NULL, symptomes LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, tension_arterielle VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, frequence_cardiaque DOUBLE PRECISION DEFAULT \'NULL\', medicaments_pris LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, activite_physique VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, hydratation VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE treatment (id INT AUTO_INCREMENT NOT NULL, senior_id INT NOT NULL, doctor_id INT NOT NULL, instructions LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_prescription DATETIME NOT NULL, medicaments LONGTEXT CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, posologie VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, frequence VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, date_debut DATETIME NOT NULL, date_fin DATETIME DEFAULT \'NULL\', renouvellements INT DEFAULT NULL, statut VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, effets_secondaires LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE participations DROP FOREIGN KEY FK_FDC6C6E881C06096');
        $this->addSql('DROP TABLE activites');
        $this->addSql('DROP TABLE participations');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
    }
}
