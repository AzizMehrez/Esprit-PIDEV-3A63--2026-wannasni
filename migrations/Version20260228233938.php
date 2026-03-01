<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228233938 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention CHANGE service_request_id service_request_id INT NOT NULL');
        $this->addSql('ALTER TABLE participations ADD CONSTRAINT FK_FDC6C6E881C06096 FOREIGN KEY (activity_id) REFERENCES activites (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_FDC6C6E881C06096 ON participations (activity_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention CHANGE service_request_id service_request_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE participations DROP FOREIGN KEY FK_FDC6C6E881C06096');
        $this->addSql('DROP INDEX IDX_FDC6C6E881C06096 ON participations');
    }
}
