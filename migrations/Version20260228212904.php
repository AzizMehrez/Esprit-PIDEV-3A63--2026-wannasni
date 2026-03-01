<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228212904 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_intervention_statut ON intervention (statut_actuel)');
        $this->addSql('CREATE INDEX idx_intervention_employe ON intervention (employe_id)');
        $this->addSql('CREATE INDEX idx_intervention_payment ON intervention (payment_status)');
        $this->addSql('CREATE INDEX idx_service_request_statut ON service_request (statut)');
        $this->addSql('CREATE INDEX idx_service_request_created_at ON service_request (created_at)');
        $this->addSql('CREATE INDEX idx_service_request_technicien ON service_request (technicien_id)');
        $this->addSql('CREATE INDEX idx_service_request_type_service ON service_request (type_service)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_intervention_statut ON intervention');
        $this->addSql('DROP INDEX idx_intervention_employe ON intervention');
        $this->addSql('DROP INDEX idx_intervention_payment ON intervention');
        $this->addSql('DROP INDEX idx_service_request_statut ON service_request');
        $this->addSql('DROP INDEX idx_service_request_created_at ON service_request');
        $this->addSql('DROP INDEX idx_service_request_technicien ON service_request');
        $this->addSql('DROP INDEX idx_service_request_type_service ON service_request');
    }
}
