<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create beverage and beverage_log tables for Sommelier feature';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE beverage (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(150) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            calories_per100ml INT DEFAULT NULL,
            nutritional_info JSON DEFAULT NULL,
            health_benefits JSON DEFAULT NULL,
            ideal_moments LONGTEXT DEFAULT NULL,
            pairing_meals LONGTEXT DEFAULT NULL,
            compatible_regimes LONGTEXT DEFAULT NULL,
            contraindications LONGTEXT DEFAULT NULL,
            hydration_score INT DEFAULT NULL,
            is_sugar_free TINYINT(1) NOT NULL,
            is_caffeine_free TINYINT(1) NOT NULL,
            image_url VARCHAR(255) DEFAULT NULL,
            origin VARCHAR(100) DEFAULT NULL,
            brand VARCHAR(100) DEFAULT NULL,
            temperature_min INT DEFAULT NULL,
            temperature_max INT DEFAULT NULL,
            preparation_instructions LONGTEXT DEFAULT NULL,
            is_active TINYINT(1) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('CREATE TABLE beverage_log (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            beverage_id INT DEFAULT NULL,
            custom_beverage_name VARCHAR(150) DEFAULT NULL,
            category VARCHAR(50) DEFAULT NULL,
            quantity_ml INT NOT NULL,
            consumed_at DATETIME NOT NULL,
            moment VARCHAR(30) DEFAULT NULL,
            satisfaction_rating INT DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            was_recommended TINYINT(1) NOT NULL,
            meal_context JSON DEFAULT NULL,
            INDEX IDX_171B87F6A76ED395 (user_id),
            INDEX IDX_171B87F649F6E812 (beverage_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql('ALTER TABLE beverage_log ADD CONSTRAINT FK_171B87F6A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE beverage_log ADD CONSTRAINT FK_171B87F649F6E812 FOREIGN KEY (beverage_id) REFERENCES beverage (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE beverage_log DROP FOREIGN KEY FK_171B87F6A76ED395');
        $this->addSql('ALTER TABLE beverage_log DROP FOREIGN KEY FK_171B87F649F6E812');
        $this->addSql('DROP TABLE beverage_log');
        $this->addSql('DROP TABLE beverage');
    }
}
