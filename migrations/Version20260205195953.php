<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205195953 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_request ADD senior_telephone VARCHAR(20) DEFAULT NULL, ADD senior_email VARCHAR(100) DEFAULT NULL, ADD adresse VARCHAR(255) DEFAULT NULL, ADD ville VARCHAR(100) DEFAULT NULL, ADD code_postal VARCHAR(20) DEFAULT NULL, ADD budget_minimum NUMERIC(10, 2) DEFAULT NULL, ADD budget_maximum NUMERIC(10, 2) DEFAULT NULL, ADD notifier_proches TINYINT(1) NOT NULL, CHANGE priorite niveau_urgence VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_request DROP senior_telephone, DROP senior_email, DROP adresse, DROP ville, DROP code_postal, DROP budget_minimum, DROP budget_maximum, DROP notifier_proches, CHANGE niveau_urgence priorite VARCHAR(50) NOT NULL');
    }
}
