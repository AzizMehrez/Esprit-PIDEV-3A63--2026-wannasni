<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Social OAuth: create user_social_account table and make user.password nullable.
 */
final class Version20260218120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_social_account table for OAuth social login (Google, GitHub, X) and make user.password nullable for social-only accounts.';
    }

    public function up(Schema $schema): void
    {
        // Create social account linking table
        $this->addSql('CREATE TABLE user_social_account (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            provider VARCHAR(30) NOT NULL,
            provider_user_id VARCHAR(255) NOT NULL,
            provider_email VARCHAR(255) DEFAULT NULL,
            provider_display_name VARCHAR(255) DEFAULT NULL,
            avatar_url VARCHAR(500) DEFAULT NULL,
            linked_at DATETIME NOT NULL,
            last_used_at DATETIME DEFAULT NULL,
            INDEX IDX_99C85C60A76ED395 (user_id),
            UNIQUE INDEX uniq_provider_user (provider, provider_user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE user_social_account ADD CONSTRAINT FK_99C85C60A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // Make password nullable so social-only accounts can exist
        $this->addSql('ALTER TABLE `user` CHANGE password password VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_social_account');
        $this->addSql('ALTER TABLE `user` CHANGE password password VARCHAR(255) NOT NULL');
    }
}
