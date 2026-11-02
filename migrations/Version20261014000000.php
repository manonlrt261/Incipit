<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée le socle manquant pour installer Incipit dans une base vide';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Cette migration nécessite MySQL ou MariaDB.');

        // Une installation existante conserve son schéma ; aucun DROP ni ALTER n’y est exécuté.
        $tables = array_diff($this->connection->createSchemaManager()->listTableNames(), ['doctrine_migration_versions']);
        if ($tables !== []) {
            $socle = ['auteur', 'contact', 'evenement_outbox', 'exemplaire', 'genre', 'livre', 'livre_auteur', 'livre_genre', 'parametre_confidentialite', 'pret', 'reset_password_request', 'utilisateur', 'session_lecture', 'souhait'];
            $this->abortIf(array_diff($socle, $tables) !== [], 'Base non vide et socle incomplet : utiliser une base vide ou analyser son historique avant de poursuivre.');
            $this->skipIf(true, 'Socle déjà présent : aucune modification du schéma existant.');
        }

        // Instantané du socle : les migrations suivantes ajoutent objectifs, visibilité, pile et ordre public.
        $this->addSql('CREATE TABLE auteur (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) DEFAULT NULL, nom_affichage VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE contact (id INT AUTO_INCREMENT NOT NULL, prenom VARCHAR(255) NOT NULL, nom VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, telephone VARCHAR(30) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE evenement_outbox (id INT AUTO_INCREMENT NOT NULL, identifiant_evenement VARCHAR(36) NOT NULL, type_evenement VARCHAR(100) NOT NULL, donnees JSON NOT NULL, date_creation DATETIME NOT NULL, date_transmission DATETIME DEFAULT NULL, statut VARCHAR(20) NOT NULL, nombre_tentatives INT NOT NULL, UNIQUE INDEX UNIQ_D91205F21A084605 (identifiant_evenement), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE exemplaire (id INT AUTO_INCREMENT NOT NULL, date_acquisition DATE DEFAULT NULL, prix_achat NUMERIC(10, 2) DEFAULT NULL, provenance VARCHAR(255) DEFAULT NULL, etat VARCHAR(100) DEFAULT NULL, emplacement VARCHAR(255) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, date_retrait DATETIME DEFAULT NULL, livre_id INT NOT NULL, INDEX IDX_5EF83C9237D925CB (livre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE genre (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_835033F86C6E55B5 (nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE livre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, sous_titre VARCHAR(255) DEFAULT NULL, isbn13 VARCHAR(13) DEFAULT NULL, isbn10 VARCHAR(10) DEFAULT NULL, resume LONGTEXT DEFAULT NULL, date_publication DATE DEFAULT NULL, nombre_pages INT DEFAULT NULL, langue VARCHAR(50) DEFAULT NULL, image_couverture VARCHAR(2048) DEFAULT NULL, saga VARCHAR(255) DEFAULT NULL, tome INT DEFAULT NULL, edition VARCHAR(255) DEFAULT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE livre_auteur (livre_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_A11876B537D925CB (livre_id), INDEX IDX_A11876B560BB6FE6 (auteur_id), PRIMARY KEY (livre_id, auteur_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE livre_genre (livre_id INT NOT NULL, genre_id INT NOT NULL, INDEX IDX_1053AB9E37D925CB (livre_id), INDEX IDX_1053AB9E4296D31F (genre_id), PRIMARY KEY (livre_id, genre_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE parametre_confidentialite (id INT AUTO_INCREMENT NOT NULL, afficher_livres_favoris TINYINT NOT NULL, afficher_auteurs_favoris TINYINT NOT NULL, afficher_wishlist TINYINT NOT NULL, date_modification DATETIME NOT NULL, utilisateur_id INT NOT NULL, UNIQUE INDEX UNIQ_47D19DAEFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE pret (id INT AUTO_INCREMENT NOT NULL, date_pret DATE NOT NULL, date_retour_prevue DATE DEFAULT NULL, date_retour_effective DATE DEFAULT NULL, statut VARCHAR(20) NOT NULL, commentaire LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, exemplaire_id INT NOT NULL, contact_id INT NOT NULL, INDEX IDX_52ECE9795843AA21 (exemplaire_id), INDEX IDX_52ECE979E7A1254A (contact_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reset_password_request (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(20) NOT NULL, hashed_token VARCHAR(100) NOT NULL, requested_at DATETIME NOT NULL, expires_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_7CE748AA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE session_lecture (id INT AUTO_INCREMENT NOT NULL, date_debut DATE DEFAULT NULL, date_fin DATE DEFAULT NULL, statut VARCHAR(20) NOT NULL, note NUMERIC(2, 1) DEFAULT NULL, avis LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, date_modification DATETIME NOT NULL, livre_id INT NOT NULL, INDEX IDX_D3BFFB1237D925CB (livre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE souhait (id INT AUTO_INCREMENT NOT NULL, priorite VARCHAR(20) DEFAULT NULL, prix_estime NUMERIC(10, 2) DEFAULT NULL, lien_achat VARCHAR(2048) DEFAULT NULL, commentaire LONGTEXT DEFAULT NULL, date_ajout DATETIME NOT NULL, date_acquisition DATE DEFAULT NULL, statut VARCHAR(20) NOT NULL, livre_id INT NOT NULL, INDEX IDX_DCACDCC837D925CB (livre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE exemplaire ADD CONSTRAINT FK_5EF83C9237D925CB FOREIGN KEY (livre_id) REFERENCES livre (id)');
        $this->addSql('ALTER TABLE livre_auteur ADD CONSTRAINT FK_A11876B537D925CB FOREIGN KEY (livre_id) REFERENCES livre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE livre_auteur ADD CONSTRAINT FK_A11876B560BB6FE6 FOREIGN KEY (auteur_id) REFERENCES auteur (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE livre_genre ADD CONSTRAINT FK_1053AB9E37D925CB FOREIGN KEY (livre_id) REFERENCES livre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE livre_genre ADD CONSTRAINT FK_1053AB9E4296D31F FOREIGN KEY (genre_id) REFERENCES genre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE parametre_confidentialite ADD CONSTRAINT FK_47D19DAEFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE9795843AA21 FOREIGN KEY (exemplaire_id) REFERENCES exemplaire (id)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE979E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id)');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES utilisateur (id)');
        $this->addSql('ALTER TABLE session_lecture ADD CONSTRAINT FK_D3BFFB1237D925CB FOREIGN KEY (livre_id) REFERENCES livre (id)');
        $this->addSql('ALTER TABLE souhait ADD CONSTRAINT FK_DCACDCC837D925CB FOREIGN KEY (livre_id) REFERENCES livre (id)');
        $this->addSql('ALTER TABLE session_lecture ADD CONSTRAINT CHK_SESSION_LECTURE_STATUT CHECK (statut IN (\'à lire\', \'en cours\', \'terminé\', \'abandonné\'))');
        $this->addSql('ALTER TABLE session_lecture ADD CONSTRAINT CHK_SESSION_LECTURE_NOTE CHECK (note IS NULL OR (note BETWEEN 0 AND 5 AND MOD(note * 10, 5) = 0))');
        $this->addSql('ALTER TABLE souhait ADD CONSTRAINT CHK_SOUHAIT_STATUT CHECK (statut IN (\'souhaité\', \'acquis\'))');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT CHK_PRET_STATUT CHECK (statut IN (\'en cours\', \'terminé\'))');
        $this->addSql('ALTER TABLE evenement_outbox ADD CONSTRAINT CHK_OUTBOX_STATUT CHECK (statut IN (\'en attente\', \'transmis\', \'erreur\'))');
        $this->addSql('ALTER TABLE evenement_outbox ADD CONSTRAINT CHK_OUTBOX_TENTATIVES CHECK (nombre_tentatives >= 0)');
    }

    public function down(Schema $schema): void
    {
        // Une annulation automatique supprimerait l’ensemble de la bibliothèque.
        $this->throwIrreversibleMigrationException('Restaurer une sauvegarde dans une base séparée plutôt que supprimer le socle.');
    }
}
