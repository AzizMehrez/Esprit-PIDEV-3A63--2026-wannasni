<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add Face ID fields to user table for Azure Face API integration
 */
final class Version20260206120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add face_encoding, face_image_path, and face_consent_at columns to user table for Face ID registration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD face_encoding JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD face_image_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD face_consent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP COLUMN face_encoding');
        $this->addSql('ALTER TABLE `user` DROP COLUMN face_image_path');
        $this->addSql('ALTER TABLE `user` DROP COLUMN face_consent_at');
    }
}
