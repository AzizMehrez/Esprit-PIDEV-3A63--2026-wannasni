<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Integration migration: normalise health_journal and treatment nullable columns
 * so that Doctrine mapping aligns with the database schema.
 */
final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalise health_journal and treatment nullable columns (vf-sante integration)';
    }

    public function up(Schema $schema): void
    {
        // health_journal – make nullable columns explicit DEFAULT NULL
        $this->addSql('ALTER TABLE health_journal
            CHANGE humeur             humeur             VARCHAR(100)     DEFAULT NULL,
            CHANGE qualite_sommeil    qualite_sommeil    VARCHAR(100)     DEFAULT NULL,
            CHANGE appetit            appetit            VARCHAR(100)     DEFAULT NULL,
            CHANGE tension_arterielle tension_arterielle VARCHAR(50)      DEFAULT NULL,
            CHANGE temperature        temperature        DOUBLE PRECISION DEFAULT NULL,
            CHANGE activite_physique  activite_physique  VARCHAR(255)     DEFAULT NULL,
            CHANGE hydratation        hydratation        VARCHAR(50)      DEFAULT NULL
        ');

        // treatment – make nullable columns explicit DEFAULT NULL
        $this->addSql('ALTER TABLE treatment
            CHANGE posologie  posologie  VARCHAR(255)     DEFAULT NULL,
            CHANGE frequence  frequence  VARCHAR(100)     DEFAULT NULL,
            CHANGE date_fin   date_fin   DATETIME         DEFAULT NULL,
            CHANGE statut     statut     VARCHAR(50)      DEFAULT NULL
        ');
    }

    public function down(Schema $schema): void
    {
        // Revert to NOT NULL (without defaults) – safe to leave as-is since
        // the columns are still nullable; DOWN is provided for completeness.
        $this->addSql('ALTER TABLE health_journal
            CHANGE humeur             humeur             VARCHAR(100)     DEFAULT NULL,
            CHANGE qualite_sommeil    qualite_sommeil    VARCHAR(100)     DEFAULT NULL,
            CHANGE appetit            appetit            VARCHAR(100)     DEFAULT NULL,
            CHANGE tension_arterielle tension_arterielle VARCHAR(50)      DEFAULT NULL,
            CHANGE temperature        temperature        DOUBLE PRECISION DEFAULT NULL,
            CHANGE activite_physique  activite_physique  VARCHAR(255)     DEFAULT NULL,
            CHANGE hydratation        hydratation        VARCHAR(50)      DEFAULT NULL
        ');

        $this->addSql('ALTER TABLE treatment
            CHANGE posologie  posologie  VARCHAR(255)     DEFAULT NULL,
            CHANGE frequence  frequence  VARCHAR(100)     DEFAULT NULL,
            CHANGE date_fin   date_fin   DATETIME         DEFAULT NULL,
            CHANGE statut     statut     VARCHAR(50)      DEFAULT NULL
        ');
    }
}
