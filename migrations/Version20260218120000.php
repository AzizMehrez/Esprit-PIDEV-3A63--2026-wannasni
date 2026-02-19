<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for the Gamified Loyalty System
 * - loyalty_point: tracks points earned/spent per senior
 * - loyalty_reward: ML-generated personalized rewards
 */
final class Version20260218120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add gamified loyalty system tables: loyalty_point, loyalty_reward';
    }

    public function up(Schema $schema): void
    {
        // Table: loyalty_point
        $this->addSql('CREATE TABLE IF NOT EXISTS loyalty_point (
            id INT AUTO_INCREMENT NOT NULL,
            senior_id INT NOT NULL,
            points INT NOT NULL DEFAULT 0,
            source VARCHAR(50) NOT NULL,
            source_id INT DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            earned_at DATETIME NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            INDEX idx_loyalty_senior (senior_id),
            CONSTRAINT fk_loyalty_point_senior FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: loyalty_reward
        $this->addSql('CREATE TABLE IF NOT EXISTS loyalty_reward (
            id INT AUTO_INCREMENT NOT NULL,
            senior_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            points_cost INT NOT NULL DEFAULT 0,
            discount_percent INT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'available\',
            ml_confidence DOUBLE PRECISION DEFAULT NULL,
            ml_features JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            redeemed_at DATETIME DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            INDEX idx_reward_senior_status (senior_id, status),
            CONSTRAINT fk_loyalty_reward_senior FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS loyalty_reward');
        $this->addSql('DROP TABLE IF EXISTS loyalty_point');
    }
}
