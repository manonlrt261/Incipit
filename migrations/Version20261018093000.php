<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261018093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Crée Sans genre et classe les livres sans genre renseigné';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO genre (nom) SELECT 'Sans genre' WHERE NOT EXISTS (SELECT 1 FROM genre WHERE nom = 'Sans genre')");
        // Corrige aussi une éventuelle sélection mêlant Sans genre et un genre précis.
        $this->addSql("DELETE lg FROM livre_genre lg INNER JOIN genre g ON g.id = lg.genre_id WHERE g.nom = 'Sans genre' AND lg.livre_id IN (SELECT livre_id FROM (SELECT lg2.livre_id FROM livre_genre lg2 INNER JOIN genre g2 ON g2.id = lg2.genre_id WHERE g2.nom <> 'Sans genre') autres)");
        // Conserve les autres classements, les livres et les sessions sans aucune suppression.
        $this->addSql("INSERT INTO livre_genre (livre_id, genre_id) SELECT l.id, g.id FROM livre l CROSS JOIN genre g WHERE g.nom = 'Sans genre' AND NOT EXISTS (SELECT 1 FROM livre_genre lg WHERE lg.livre_id = l.id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM livre_genre WHERE genre_id IN (SELECT id FROM genre WHERE nom = 'Sans genre')");
        $this->addSql("DELETE FROM genre WHERE nom = 'Sans genre'");
    }
}
