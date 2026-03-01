<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix onDelete behavior for all foreign keys to match entity annotations.
 * Doctrine-Doctor: ondelete_mismatch resolution.
 */
final class Version20260228222000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix onDelete CASCADE/SET NULL on all foreign keys to match ORM annotations';
    }

    public function up(Schema $schema): void
    {
        // beverage_log.user_id -> CASCADE
        $this->addSql('ALTER TABLE beverage_log DROP FOREIGN KEY FK_171B87F6A76ED395');
        $this->addSql('ALTER TABLE beverage_log ADD CONSTRAINT FK_171B87F6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // beverage_log.beverage_id -> SET NULL
        $this->addSql('ALTER TABLE beverage_log DROP FOREIGN KEY FK_171B87F649F6E812');
        $this->addSql('ALTER TABLE beverage_log ADD CONSTRAINT FK_171B87F649F6E812 FOREIGN KEY (beverage_id) REFERENCES beverage (id) ON DELETE SET NULL');

        // beverage_order.user_id -> CASCADE
        $this->addSql('ALTER TABLE beverage_order DROP FOREIGN KEY FK_31F79165A76ED395');
        $this->addSql('ALTER TABLE beverage_order ADD CONSTRAINT FK_31F79165A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // beverage_order_item.beverage_order_id -> CASCADE
        $this->addSql('ALTER TABLE beverage_order_item DROP FOREIGN KEY FK_60C6491E63DE102E');
        $this->addSql('ALTER TABLE beverage_order_item ADD CONSTRAINT FK_60C6491E63DE102E FOREIGN KEY (beverage_order_id) REFERENCES beverage_order (id) ON DELETE CASCADE');

        // beverage_order_item.product_id -> CASCADE
        $this->addSql('ALTER TABLE beverage_order_item DROP FOREIGN KEY FK_60C6491E4584665A');
        $this->addSql('ALTER TABLE beverage_order_item ADD CONSTRAINT FK_60C6491E4584665A FOREIGN KEY (product_id) REFERENCES beverage_product (id) ON DELETE CASCADE');

        // demande_regime.user_id -> SET NULL
        $this->addSql('ALTER TABLE demande_regime DROP FOREIGN KEY FK_E8C20C93A76ED395');
        $this->addSql('ALTER TABLE demande_regime ADD CONSTRAINT FK_E8C20C93A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');

        // demande_regime.nutritionniste_id -> SET NULL
        $this->addSql('ALTER TABLE demande_regime DROP FOREIGN KEY FK_E8C20C93279DA68A');
        $this->addSql('ALTER TABLE demande_regime ADD CONSTRAINT FK_E8C20C93279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id) ON DELETE SET NULL');

        // loyalty_point.senior_id -> CASCADE
        $this->addSql('ALTER TABLE loyalty_point DROP FOREIGN KEY FK_1D53135FAB8E2');
        $this->addSql('ALTER TABLE loyalty_point ADD CONSTRAINT FK_1D53135FAB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // loyalty_reward.senior_id -> CASCADE
        $this->addSql('ALTER TABLE loyalty_reward DROP FOREIGN KEY FK_89AC2C07AB8E2');
        $this->addSql('ALTER TABLE loyalty_reward ADD CONSTRAINT FK_89AC2C07AB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // rapport_hebdomadaire.nutritionniste_id -> SET NULL
        $this->addSql('ALTER TABLE rapport_hebdomadaire DROP FOREIGN KEY FK_AC9EF1BF279DA68A');
        $this->addSql('ALTER TABLE rapport_hebdomadaire ADD CONSTRAINT FK_AC9EF1BF279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id) ON DELETE SET NULL');

        // rapport_hebdomadaire.senior_id -> CASCADE
        $this->addSql('ALTER TABLE rapport_hebdomadaire DROP FOREIGN KEY FK_AC9EF1BFAB8E2');
        $this->addSql('ALTER TABLE rapport_hebdomadaire ADD CONSTRAINT FK_AC9EF1BFAB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // regime_prescrit.demande_id -> CASCADE
        $this->addSql('ALTER TABLE regime_prescrit DROP FOREIGN KEY FK_1A344CD180E95E18');
        $this->addSql('ALTER TABLE regime_prescrit ADD CONSTRAINT FK_1A344CD180E95E18 FOREIGN KEY (demande_id) REFERENCES demande_regime (id) ON DELETE CASCADE');

        // regime_prescrit.user_id -> SET NULL
        $this->addSql('ALTER TABLE regime_prescrit DROP FOREIGN KEY FK_1A344CD1A76ED395');
        $this->addSql('ALTER TABLE regime_prescrit ADD CONSTRAINT FK_1A344CD1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE SET NULL');

        // regime_prescrit.nutritionniste_id -> SET NULL
        $this->addSql('ALTER TABLE regime_prescrit DROP FOREIGN KEY FK_1A344CD1279DA68A');
        $this->addSql('ALTER TABLE regime_prescrit ADD CONSTRAINT FK_1A344CD1279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id) ON DELETE SET NULL');

        // subscription.senior_id -> CASCADE
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3AB8E2');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3AB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // subscription.subscriber_id -> CASCADE
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D37808B1AD');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D37808B1AD FOREIGN KEY (subscriber_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // subscription.plan_id -> CASCADE
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3E899029B');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3E899029B FOREIGN KEY (plan_id) REFERENCES subscription_plan (id) ON DELETE CASCADE');

        // suivi_repas.senior_id -> CASCADE
        $this->addSql('ALTER TABLE suivi_repas DROP FOREIGN KEY FK_BDF7190AB8E2');
        $this->addSql('ALTER TABLE suivi_repas ADD CONSTRAINT FK_BDF7190AB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // suivi_repas.regime_prescrit_id -> SET NULL
        $this->addSql('ALTER TABLE suivi_repas DROP FOREIGN KEY FK_BDF719097726E91');
        $this->addSql('ALTER TABLE suivi_repas ADD CONSTRAINT FK_BDF719097726E91 FOREIGN KEY (regime_prescrit_id) REFERENCES regime_prescrit (id) ON DELETE SET NULL');

        // verification_request.user_id -> CASCADE
        $this->addSql('ALTER TABLE verification_request DROP FOREIGN KEY FK_20FDDF4EA76ED395');
        $this->addSql('ALTER TABLE verification_request ADD CONSTRAINT FK_20FDDF4EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        // verification_request.reviewed_by_id -> SET NULL
        $this->addSql('ALTER TABLE verification_request DROP FOREIGN KEY FK_20FDDF4EFC6B21F1');
        $this->addSql('ALTER TABLE verification_request ADD CONSTRAINT FK_20FDDF4EFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert all to RESTRICT
        $this->addSql('ALTER TABLE beverage_log DROP FOREIGN KEY FK_171B87F6A76ED395');
        $this->addSql('ALTER TABLE beverage_log ADD CONSTRAINT FK_171B87F6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE beverage_log DROP FOREIGN KEY FK_171B87F649F6E812');
        $this->addSql('ALTER TABLE beverage_log ADD CONSTRAINT FK_171B87F649F6E812 FOREIGN KEY (beverage_id) REFERENCES beverage (id)');
        $this->addSql('ALTER TABLE beverage_order DROP FOREIGN KEY FK_31F79165A76ED395');
        $this->addSql('ALTER TABLE beverage_order ADD CONSTRAINT FK_31F79165A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE beverage_order_item DROP FOREIGN KEY FK_60C6491E63DE102E');
        $this->addSql('ALTER TABLE beverage_order_item ADD CONSTRAINT FK_60C6491E63DE102E FOREIGN KEY (beverage_order_id) REFERENCES beverage_order (id)');
        $this->addSql('ALTER TABLE beverage_order_item DROP FOREIGN KEY FK_60C6491E4584665A');
        $this->addSql('ALTER TABLE beverage_order_item ADD CONSTRAINT FK_60C6491E4584665A FOREIGN KEY (product_id) REFERENCES beverage_product (id)');
        $this->addSql('ALTER TABLE demande_regime DROP FOREIGN KEY FK_E8C20C93A76ED395');
        $this->addSql('ALTER TABLE demande_regime ADD CONSTRAINT FK_E8C20C93A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE demande_regime DROP FOREIGN KEY FK_E8C20C93279DA68A');
        $this->addSql('ALTER TABLE demande_regime ADD CONSTRAINT FK_E8C20C93279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE loyalty_point DROP FOREIGN KEY FK_1D53135FAB8E2');
        $this->addSql('ALTER TABLE loyalty_point ADD CONSTRAINT FK_1D53135FAB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE loyalty_reward DROP FOREIGN KEY FK_89AC2C07AB8E2');
        $this->addSql('ALTER TABLE loyalty_reward ADD CONSTRAINT FK_89AC2C07AB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE rapport_hebdomadaire DROP FOREIGN KEY FK_AC9EF1BF279DA68A');
        $this->addSql('ALTER TABLE rapport_hebdomadaire ADD CONSTRAINT FK_AC9EF1BF279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE rapport_hebdomadaire DROP FOREIGN KEY FK_AC9EF1BFAB8E2');
        $this->addSql('ALTER TABLE rapport_hebdomadaire ADD CONSTRAINT FK_AC9EF1BFAB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE regime_prescrit DROP FOREIGN KEY FK_1A344CD180E95E18');
        $this->addSql('ALTER TABLE regime_prescrit ADD CONSTRAINT FK_1A344CD180E95E18 FOREIGN KEY (demande_id) REFERENCES demande_regime (id)');
        $this->addSql('ALTER TABLE regime_prescrit DROP FOREIGN KEY FK_1A344CD1A76ED395');
        $this->addSql('ALTER TABLE regime_prescrit ADD CONSTRAINT FK_1A344CD1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE regime_prescrit DROP FOREIGN KEY FK_1A344CD1279DA68A');
        $this->addSql('ALTER TABLE regime_prescrit ADD CONSTRAINT FK_1A344CD1279DA68A FOREIGN KEY (nutritionniste_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3AB8E2');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3AB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D37808B1AD');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D37808B1AD FOREIGN KEY (subscriber_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE subscription DROP FOREIGN KEY FK_A3C664D3E899029B');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3E899029B FOREIGN KEY (plan_id) REFERENCES subscription_plan (id)');
        $this->addSql('ALTER TABLE suivi_repas DROP FOREIGN KEY FK_BDF7190AB8E2');
        $this->addSql('ALTER TABLE suivi_repas ADD CONSTRAINT FK_BDF7190AB8E2 FOREIGN KEY (senior_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE suivi_repas DROP FOREIGN KEY FK_BDF719097726E91');
        $this->addSql('ALTER TABLE suivi_repas ADD CONSTRAINT FK_BDF719097726E91 FOREIGN KEY (regime_prescrit_id) REFERENCES regime_prescrit (id)');
        $this->addSql('ALTER TABLE verification_request DROP FOREIGN KEY FK_20FDDF4EA76ED395');
        $this->addSql('ALTER TABLE verification_request ADD CONSTRAINT FK_20FDDF4EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE verification_request DROP FOREIGN KEY FK_20FDDF4EFC6B21F1');
        $this->addSql('ALTER TABLE verification_request ADD CONSTRAINT FK_20FDDF4EFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id)');
    }
}
