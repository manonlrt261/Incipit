<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017190000 extends AbstractMigration
{
    public const GENRES = [
        'Art',
        'Bien-être',
        'Classique',
        'Droit',
        'Fantastique',
        'Histoire',
        'Littérature japonaise',
        'Nouvelles',
        'Polars/Thriller',
        'Psychologie',
        'Biographies',
        'Romans contemporains',
        'Science-fiction',
        'Sciences',
        'Société',
        'Sport',
        'Théâtre',
    ];

    public function getDescription(): string
    {
        return 'Remplace les anciens genres par la liste des 17 genres retenus pour Incipit';
    }

    public function up(Schema $schema): void
    {
        // Conserve les identifiants des genres déjà présents pour préserver leurs liens avec les livres.
        foreach (self::GENRES as $nom) {
            $this->addSql('UPDATE genre SET nom = ? WHERE nom = ?', [$nom, $nom]);
            $this->addSql('INSERT INTO genre (nom) SELECT ? WHERE NOT EXISTS (SELECT 1 FROM genre WHERE nom = ?)', [$nom, $nom]);
        }

        // Retire les anciens classements avant leurs genres, sans supprimer de livre ni de lecture.
        $parametres = implode(', ', array_fill(0, count(self::GENRES), '?'));
        $this->addSql('DELETE FROM livre_genre WHERE genre_id IN (SELECT id FROM genre WHERE nom NOT IN ('.$parametres.'))', self::GENRES);
        $this->addSql('DELETE FROM genre WHERE nom NOT IN ('.$parametres.')', self::GENRES);
    }

    public function down(Schema $schema): void
    {
        // Un retour arrière ne pourrait pas reconstituer les anciens classements des livres.
        $this->throwIrreversibleMigrationException('Les anciens genres et leurs associations ne peuvent pas être reconstitués automatiquement.');
    }
}
