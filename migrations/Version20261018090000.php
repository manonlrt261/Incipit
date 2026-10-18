<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une sélection de pile à lire indépendante des livres possédés';
    }

    public function up(Schema $schema): void
    {
        // Les livres existants restent possédés ; la nouvelle pile démarre vide.
        $this->addSql('ALTER TABLE livre ADD pile_a_lire TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE livre DROP pile_a_lire');
    }
}
