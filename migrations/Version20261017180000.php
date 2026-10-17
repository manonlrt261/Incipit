<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Autorise les statuts Pas commencé et En pause dans les sessions de lecture';
    }

    public function isTransactional(): bool
    {
        // MySQL et MariaDB valident automatiquement les modifications de structure.
        return false;
    }

    public function up(Schema $schema): void
    {
        $plateforme = $this->connection->getDatabasePlatform();
        $this->abortIf(!$plateforme instanceof AbstractMySQLPlatform, 'Cette migration nécessite MySQL ou MariaDB.');

        // Remplace uniquement la règle ; les anciens statuts restent autorisés et aucune ligne n’est modifiée.
        $suppression = $plateforme instanceof MariaDBPlatform ? 'DROP CONSTRAINT' : 'DROP CHECK';
        $this->addSql(sprintf(
            "ALTER TABLE session_lecture %s CHK_SESSION_LECTURE_STATUT, ADD CONSTRAINT CHK_SESSION_LECTURE_STATUT CHECK (statut IN ('à lire', 'en cours', 'terminé', 'abandonné', 'Pas commencé', 'En pause'))",
            $suppression
        ));
    }

    public function down(Schema $schema): void
    {
        // L’ancienne règle rejetterait les lectures créées avec les nouveaux statuts.
        $this->throwIrreversibleMigrationException('Les lectures Pas commencé ou En pause doivent rester valides.');
    }
}
