<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206162346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        $this->addSql('DROP TABLE IF EXISTS utilisateur');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY IF EXISTS intervention_ibfk_1');
        $this->addSql('DROP INDEX IF EXISTS employe_id ON intervention');
        $this->addSql('ALTER TABLE intervention CHANGE employe_id employe_id INT DEFAULT NULL, CHANGE statut_actuel statut_actuel VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814ABD42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id)');
        $this->addSql('CREATE INDEX IDX_D11814ABD42F8111 ON intervention (service_request_id)');
        $this->addSql('ALTER TABLE service_request DROP date_debut, DROP date_fin');
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, mot_de_passe_hache VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, role ENUM(\'senior\', \'family\', \'doctor\', \'technician\', \'coach\', \'admin\') CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_general_ci`, telephone VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, image_profil VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, statut ENUM(\'actif\', \'inactif\', \'suspendu\') CHARACTER SET utf8mb4 DEFAULT \'actif\' COLLATE `utf8mb4_general_ci`, prenom VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, nom VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, date_naissance DATE DEFAULT NULL, adresse VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, ville VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, code_postal VARCHAR(20) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, pays VARCHAR(100) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, localisations VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, UNIQUE INDEX email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814ABD42F8111');
        $this->addSql('DROP INDEX IDX_D11814ABD42F8111 ON intervention');
        $this->addSql('ALTER TABLE intervention CHANGE employe_id employe_id INT NOT NULL, CHANGE statut_actuel statut_actuel ENUM(\'en cours\', \'termine\', \'annule\') DEFAULT \'en cours\'');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT intervention_ibfk_1 FOREIGN KEY (employe_id) REFERENCES utilisateur (id)');
        $this->addSql('CREATE INDEX employe_id ON intervention (employe_id)');
        $this->addSql('ALTER TABLE service_request ADD date_debut DATETIME DEFAULT NULL, ADD date_fin DATETIME DEFAULT NULL');
    }
}
