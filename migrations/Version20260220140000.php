<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create beverage_product, beverage_order, and beverage_order_item tables for marketplace';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE beverage_product (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(50) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            short_description VARCHAR(500) DEFAULT NULL,
            price NUMERIC(10, 2) NOT NULL,
            sale_price NUMERIC(10, 2) DEFAULT NULL,
            calories_per100ml INT DEFAULT NULL,
            hydration_score INT DEFAULT NULL,
            is_sugar_free TINYINT(1) NOT NULL DEFAULT 0,
            is_caffeine_free TINYINT(1) NOT NULL DEFAULT 0,
            is_bio TINYINT(1) NOT NULL DEFAULT 0,
            health_benefits JSON DEFAULT NULL,
            nutritional_info JSON DEFAULT NULL,
            compatible_regimes LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\',
            ingredients LONGTEXT DEFAULT NULL COMMENT \'(DC2Type:simple_array)\',
            brand VARCHAR(255) DEFAULT NULL,
            origin VARCHAR(255) DEFAULT NULL,
            volume VARCHAR(100) DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            stock_quantity INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_featured TINYINT(1) NOT NULL DEFAULT 0,
            average_rating NUMERIC(3, 2) DEFAULT NULL,
            review_count INT NOT NULL DEFAULT 0,
            sales_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE beverage_order (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'cart\',
            order_number VARCHAR(20) DEFAULT NULL,
            total_amount NUMERIC(10, 2) NOT NULL DEFAULT 0,
            shipping_cost NUMERIC(10, 2) NOT NULL DEFAULT 0,
            shipping_address VARCHAR(500) DEFAULT NULL,
            shipping_city VARCHAR(255) DEFAULT NULL,
            shipping_postal_code VARCHAR(20) DEFAULT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            payment_method VARCHAR(50) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            confirmed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            shipped_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_beverage_order_number (order_number),
            INDEX IDX_beverage_order_user (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_beverage_order_user FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE beverage_order_item (
            id INT AUTO_INCREMENT NOT NULL,
            beverage_order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price NUMERIC(10, 2) NOT NULL,
            added_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_beverage_order_item_order (beverage_order_id),
            INDEX IDX_beverage_order_item_product (product_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_beverage_order_item_order FOREIGN KEY (beverage_order_id) REFERENCES beverage_order (id) ON DELETE CASCADE,
            CONSTRAINT FK_beverage_order_item_product FOREIGN KEY (product_id) REFERENCES beverage_product (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE beverage_order_item');
        $this->addSql('DROP TABLE beverage_order');
        $this->addSql('DROP TABLE beverage_product');
    }
}
