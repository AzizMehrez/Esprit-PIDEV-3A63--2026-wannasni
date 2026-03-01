<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260301012931 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beverage CHANGE ideal_moments ideal_moments JSON DEFAULT NULL, CHANGE pairing_meals pairing_meals JSON DEFAULT NULL, CHANGE compatible_regimes compatible_regimes JSON DEFAULT NULL, CHANGE contraindications contraindications JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE beverage_product CHANGE compatible_regimes compatible_regimes JSON DEFAULT NULL, CHANGE ingredients ingredients JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE regime_prescrit CHANGE aliments_recommandes aliments_recommandes JSON DEFAULT NULL, CHANGE aliments_interdits aliments_interdits JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE beverage CHANGE ideal_moments ideal_moments LONGTEXT DEFAULT NULL, CHANGE pairing_meals pairing_meals LONGTEXT DEFAULT NULL, CHANGE compatible_regimes compatible_regimes LONGTEXT DEFAULT NULL, CHANGE contraindications contraindications LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE beverage_product CHANGE compatible_regimes compatible_regimes LONGTEXT DEFAULT NULL, CHANGE ingredients ingredients LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE regime_prescrit CHANGE aliments_recommandes aliments_recommandes LONGTEXT DEFAULT NULL, CHANGE aliments_interdits aliments_interdits LONGTEXT DEFAULT NULL');
    }
}
