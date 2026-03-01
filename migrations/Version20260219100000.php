<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Subscription module: create subscription_plan and subscription tables.
 * Merge from koussay_serviceVF branch.
 */
final class Version20260219100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription_plan and subscription tables for abonnement/loyalty module';
    }

    public function up(Schema $schema): void
    {
        // Table: subscription_plan
        $this->addSql('CREATE TABLE IF NOT EXISTS subscription_plan (
            id INT AUTO_INCREMENT NOT NULL,
            slug VARCHAR(50) NOT NULL,
            name VARCHAR(100) NOT NULL,
            price_monthly DECIMAL(8,2) NOT NULL,
            discount_percent INT NOT NULL,
            maintenances_per_year INT NOT NULL,
            priorite_urgences TINYINT(1) NOT NULL DEFAULT 0,
            technicien_dedie TINYINT(1) NOT NULL DEFAULT 0,
            description LONGTEXT DEFAULT NULL,
            stripe_price_id VARCHAR(100) DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE INDEX uniq_plan_slug (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table: subscription
        $this->addSql('CREATE TABLE IF NOT EXISTS subscription (
            id INT AUTO_INCREMENT NOT NULL,
            senior_id INT NOT NULL,
            subscriber_id INT NOT NULL,
            plan_id INT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT \'pending\',
            stripe_subscription_id VARCHAR(255) DEFAULT NULL,
            stripe_customer_id VARCHAR(255) DEFAULT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME DEFAULT NULL,
            next_billing_date DATETIME DEFAULT NULL,
            failed_payment_attempts INT NOT NULL DEFAULT 0,
            total_saved DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            maintenances_used INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            INDEX idx_subscription_status (status),
            INDEX idx_subscription_senior_status (senior_id, status),
            INDEX IDX_A3C664D3B8B48327 (senior_id),
            INDEX IDX_A3C664D37808B1AD (subscriber_id),
            INDEX IDX_A3C664D3E899029B (plan_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE subscription
            ADD CONSTRAINT FK_A3C664D3B8B48327 FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_A3C664D37808B1AD FOREIGN KEY (subscriber_id) REFERENCES `user` (id) ON DELETE CASCADE,
            ADD CONSTRAINT FK_A3C664D3E899029B FOREIGN KEY (plan_id) REFERENCES subscription_plan (id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3B8B48327');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D37808B1AD');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3E899029B');
        $this->addSql('DROP TABLE IF EXISTS subscription');
        $this->addSql('DROP TABLE IF EXISTS subscription_plan');
    }
}
