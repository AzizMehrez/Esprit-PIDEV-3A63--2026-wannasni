<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add verification workflow fields: user verification columns + verification_request table
 */
final class Version20260217100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account verification fields to user table and create verification_request table';
    }

    public function up(Schema $schema): void
    {
        // User verification & networking ban columns
        $this->addSql('ALTER TABLE `user` ADD is_account_verified TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE `user` ADD verified_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD verification_badge_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD is_networking_banned TINYINT(1) NOT NULL DEFAULT 0');

        // Auto-set admins / @wannasni.com as verified with purple badge
        $this->addSql("UPDATE `user` SET is_account_verified = 1, verification_badge_type = 'purple', verified_at = NOW() WHERE roles LIKE '%ROLE_ADMIN%' OR email LIKE '%@wannasni.com'");

        // Verification request table
        $this->addSql('CREATE TABLE verification_request (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            reason LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            ai_report JSON DEFAULT NULL,
            ai_score DOUBLE PRECISION DEFAULT NULL,
            reviewed_by_id INT DEFAULT NULL,
            review_note LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            reviewed_at DATETIME DEFAULT NULL,
            INDEX idx_vr_status (status),
            INDEX IDX_VR_USER (user_id),
            INDEX IDX_VR_REVIEWER (reviewed_by_id),
            CONSTRAINT FK_VR_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
            CONSTRAINT FK_VR_REVIEWER FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE verification_request');
        $this->addSql('ALTER TABLE `user` DROP COLUMN is_account_verified');
        $this->addSql('ALTER TABLE `user` DROP COLUMN verified_at');
        $this->addSql('ALTER TABLE `user` DROP COLUMN verification_badge_type');
        $this->addSql('ALTER TABLE `user` DROP COLUMN is_networking_banned');
    }
}
