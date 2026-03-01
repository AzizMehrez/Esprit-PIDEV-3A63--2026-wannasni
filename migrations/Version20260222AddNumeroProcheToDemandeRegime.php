<?php
// Migration pour ajouter le champ numeroProche à DemandeRegime
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222AddNumeroProcheToDemandeRegime extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le champ numeroProche à DemandeRegime';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_regime ADD numero_proche VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_regime DROP COLUMN numero_proche');
    }
}
