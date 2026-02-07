<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify it to fill null dateCreation values!
 */
final class Version20260206175000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fill null dateCreation with current timestamp';
    }

    public function up(Schema $schema): void
    {
        // Fill null dateCreation with current timestamp
        $this->addSql('UPDATE intervention SET date_creation = NOW() WHERE date_creation IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
    }
}
