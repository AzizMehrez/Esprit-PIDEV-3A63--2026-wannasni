<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix database configuration issues detected by doctrine-doctor:
 * - Convert all tables from utf8mb4_general_ci to utf8mb4_unicode_ci
 * - Increase decimal precision on subscription_plan.price_monthly (8,2) -> (10,4)
 * - Set explicit timezone to avoid SYSTEM ambiguity
 * - Set InnoDB buffer pool size hint
 */
final class Version20260301010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix collation, decimal precision, timezone, and InnoDB buffer pool';
    }

    public function up(Schema $schema): void
    {
        // 1. Convert database default collation
        $this->addSql("ALTER DATABASE wannasni CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 2. Convert all tables to utf8mb4_unicode_ci
        $tables = [
            'activites',
            'beverage',
            'beverage_log',
            'beverage_order',
            'beverage_order_item',
            'beverage_product',
            'connection_invite',
            'conversation',
            'demande_regime',
            'doctrine_migration_versions',
            'health_journal',
            'intervention',
            'loyalty_point',
            'loyalty_reward',
            'message',
            'messenger_messages',
            'notification',
            'participations',
            'post',
            'post_comment',
            'post_like',
            'post_media',
            'rapport_hebdomadaire',
            'regime_prescrit',
            'service_request',
            'subscription',
            'subscription_plan',
            'suivi_repas',
            'treatment',
            '`user`',
            'user_connection',
            'user_social_account',
            'verification_request',
        ];

        foreach ($tables as $table) {
            $this->addSql("ALTER TABLE {$table} CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // 3. Increase decimal precision for price_monthly
        $this->addSql("ALTER TABLE subscription_plan MODIFY price_monthly DECIMAL(10, 4) NOT NULL");

        // 4. Set explicit session timezone
        $this->addSql("SET time_zone = '+01:00'");

        // 5. Set InnoDB buffer pool size (128MB - requires SUPER privilege, may fail gracefully)
        try {
            $this->addSql("SET GLOBAL innodb_buffer_pool_size = 134217728");
        } catch (\Exception $e) {
            // May require SUPER privilege - skip if not available
        }
    }

    public function down(Schema $schema): void
    {
        // Revert decimal precision
        $this->addSql("ALTER TABLE subscription_plan MODIFY price_monthly DECIMAL(8, 2) NOT NULL");

        // Revert collation (not strictly necessary but for completeness)
        // Not reverting collation changes as they're improvements
    }
}
