<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Conserve l’ordre personnalisé des livres de l’accueil public'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre_confidentialite ADD ordre_accueil JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE parametre_confidentialite DROP ordre_accueil');
    }
}
