<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corrige le statut souhaité mal encodé dans la contrainte de wishlist';
    }

    public function isTransactional(): bool
    {
        // MySQL et MariaDB valident automatiquement les modifications de contraintes.
        return false;
    }

    public function up(Schema $schema): void
    {
        $plateforme = $this->connection->getDatabasePlatform();
        $this->abortIf(!$plateforme instanceof AbstractMySQLPlatform, 'Cette migration nécessite MySQL ou MariaDB.');
        $suppression = $plateforme instanceof MariaDBPlatform ? 'DROP CONSTRAINT' : 'DROP CHECK';

        // Autorise provisoirement les deux écritures pour conserver les souhaits existants pendant la correction.
        $this->addSql(sprintf(
            "ALTER TABLE souhait %s CHK_SOUHAIT_STATUT, ADD CONSTRAINT CHK_SOUHAIT_STATUT CHECK (statut IN ('souhaité', 'souhaitxC3xA9', 'acquis'))",
            $suppression
        ));
        $this->addSql("UPDATE souhait SET statut = 'souhaité' WHERE statut = 'souhaitxC3xA9'");

        // Rétablit les deux seuls statuts utilisés par le site, sans retirer de souhait ni de livre.
        $this->addSql(sprintf(
            "ALTER TABLE souhait %s CHK_SOUHAIT_STATUT, ADD CONSTRAINT CHK_SOUHAIT_STATUT CHECK (statut IN ('souhaité', 'acquis'))",
            $suppression
        ));
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restaurer la règle mal encodée empêcherait à nouveau l’ajout de souhaits.');
    }
}
