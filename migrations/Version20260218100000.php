<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add networking moderation strike tracking columns to user table.
 */
final class Version20260218100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add networking_strikes and last_strike_at columns to user table for content moderation tracking';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD networking_strikes INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE `user` ADD last_strike_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP COLUMN networking_strikes');
        $this->addSql('ALTER TABLE `user` DROP COLUMN last_strike_at');
    }
}
