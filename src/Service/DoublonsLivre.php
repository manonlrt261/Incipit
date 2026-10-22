<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class DoublonsLivre
{
    public function __construct(private Connection $connection)
    {
    }

    // Compare le titre et les auteurs des livres encore possédés, sans tenir compte de leur édition ou ISBN.
    public function existe(string $titre, array $auteurs): bool
    {
        if (!$auteurs) {
            return false;
        }
        $slugger = new AsciiSlugger('fr');
        $normaliser = static fn (string $texte): string => $slugger->slug($texte)->lower()->toString();
        $titreNormalise = $normaliser($titre);
        $noms = array_map($normaliser, $auteurs);
        $livres = $this->connection->fetchAllAssociative("
            SELECT l.titre, COALESCE(NULLIF(a.nom_affichage, ''), CONCAT_WS(' ', a.prenom, a.nom)) AS auteur
            FROM livre l
            INNER JOIN livre_auteur la ON la.livre_id = l.id
            INNER JOIN auteur a ON a.id = la.auteur_id
            WHERE EXISTS (SELECT 1 FROM exemplaire e WHERE e.livre_id = l.id AND e.date_retrait IS NULL)
        ");

        // Un auteur commun suffit pour les livres ayant plusieurs auteurs ; un titre seul ne constitue pas un doublon.
        foreach ($livres as $livre) {
            if ($normaliser($livre['titre']) === $titreNormalise
                && in_array($normaliser($livre['auteur']), $noms, true)) {
                return true;
            }
        }

        return false;
    }
}
