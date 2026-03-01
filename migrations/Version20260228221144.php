<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260228221144 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814ABD42F8111');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814ABD42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03A76ED395');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE intervention DROP FOREIGN KEY FK_D11814ABD42F8111');
        $this->addSql('ALTER TABLE intervention ADD CONSTRAINT FK_D11814ABD42F8111 FOREIGN KEY (service_request_id) REFERENCES service_request (id)');
        $this->addSql('ALTER TABLE service_request DROP FOREIGN KEY FK_F413DD03A76ED395');
        $this->addSql('ALTER TABLE service_request ADD CONSTRAINT FK_F413DD03A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
    }
}
