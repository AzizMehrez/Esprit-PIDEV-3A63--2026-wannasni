<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260205214241 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_request ADD technicien_id INT DEFAULT NULL, ADD technicien_nom VARCHAR(100) DEFAULT NULL, ADD notes_admin LONGTEXT DEFAULT NULL, ADD date_assignation DATETIME DEFAULT NULL, ADD date_debut DATETIME DEFAULT NULL, ADD date_fin DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_request DROP technicien_id, DROP technicien_nom, DROP notes_admin, DROP date_assignation, DROP date_debut, DROP date_fin');
    }
}
