<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260204213351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE participations DROP FOREIGN KEY participations_ibfk_1');
        $this->addSql('DROP TABLE participations');
        $this->addSql('ALTER TABLE activites ADD end_time DATETIME DEFAULT NULL, ADD location VARCHAR(255) DEFAULT NULL, ADD max_participants INT DEFAULT NULL, ADD coach_id INT DEFAULT NULL, ADD is_active TINYINT(1) NOT NULL, DROP categorie, DROP duree_estimee, DROP lieu, DROP mode, DROP places_max, DROP places_reservees, DROP niveau_difficulte, DROP materiel_requis, DROP prix_participation, DROP statut, DROP date_creation, CHANGE type type VARCHAR(50) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE id_activite id INT AUTO_INCREMENT NOT NULL, CHANGE titre title VARCHAR(255) NOT NULL, CHANGE date_activite start_time DATETIME NOT NULL, CHANGE organisateur_id current_participants INT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE participations (id INT AUTO_INCREMENT NOT NULL, activite_id INT DEFAULT NULL, participant_id INT DEFAULT NULL, statut ENUM(\'inscrit\', \'present\', \'absent_excuse\', \'absent_non_excuse\') CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, date_inscription DATETIME DEFAULT \'current_timestamp()\' NOT NULL, moyen_inscription ENUM(\'appli\', \'telephone\', \'en_personne\') CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, feedback_note INT DEFAULT NULL, feedback_commentaire TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, humeur_avant INT DEFAULT NULL, humeur_apres INT DEFAULT NULL, problemes_rencontres TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, recommandation_amis TINYINT(1) DEFAULT NULL, partage_photos TEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_general_ci`, certificat_participation TINYINT(1) DEFAULT NULL, partage_avec_proches ENUM(\'oui\', \'non\') CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_general_ci`, INDEX activite_id (activite_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE participations ADD CONSTRAINT participations_ibfk_1 FOREIGN KEY (activite_id) REFERENCES activites (id_activite)');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('ALTER TABLE activites ADD categorie ENUM(\'physique\', \'sociale\', \'loisir\', \'educative\') NOT NULL, ADD duree_estimee INT DEFAULT NULL, ADD lieu VARCHAR(255) DEFAULT \'NULL\', ADD mode ENUM(\'presentiel\', \'virtuel\', \'hybride\') NOT NULL, ADD places_max INT DEFAULT NULL, ADD places_reservees INT DEFAULT 0, ADD niveau_difficulte ENUM(\'facile\', \'moyen\', \'avance\') DEFAULT \'NULL\', ADD materiel_requis TEXT DEFAULT NULL, ADD prix_participation NUMERIC(8, 2) DEFAULT \'0.00\', ADD statut ENUM(\'planifie\', \'confirme\', \'termine\', \'annule\') DEFAULT \'\'\'planifie\'\'\', ADD date_creation DATETIME DEFAULT \'current_timestamp()\' NOT NULL, DROP end_time, DROP location, DROP max_participants, DROP coach_id, DROP is_active, CHANGE description description TEXT DEFAULT NULL, CHANGE type type ENUM(\'suggeree_par_coach\', \'evenement_reel\') NOT NULL, CHANGE id id_activite INT AUTO_INCREMENT NOT NULL, CHANGE title titre VARCHAR(255) NOT NULL, CHANGE current_participants organisateur_id INT NOT NULL, CHANGE start_time date_activite DATETIME NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id_activite)');
    }
}
