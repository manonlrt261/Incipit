<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class OrdreAccueil
{
    public function __construct(private Connection $connexion) {}

    // Réordonne uniquement la sélection déjà publique : aucun titre masqué ou acheté ne peut réapparaître.
    public function appliquer(array $livres, string $section): array
    {
        $json = $this->connexion->fetchOne('SELECT p.ordre_accueil FROM utilisateur u LEFT JOIN parametre_confidentialite p ON p.utilisateur_id = u.id ORDER BY u.id ASC LIMIT 1');
        $ordre = is_string($json) ? json_decode($json, true) : [];
        $positions = array_flip($ordre[$section] ?? []);
        // Les favoris sont des lignes SQL ; la wishlist complète utilise les entités Souhait.
        $id = static fn ($livre): int => is_array($livre) ? (int) $livre['id'] : $livre->getLivre()->getId();
        // Les livres sans position personnalisée gardent leur ordre relatif après ceux déjà placés.
        $initial = array_flip(array_map($id, $livres));
        usort($livres, static fn ($a, $b): int => ($positions[$id($a)] ?? PHP_INT_MAX) <=> ($positions[$id($b)] ?? PHP_INT_MAX) ?: $initial[$id($a)] <=> $initial[$id($b)]);
        return $livres;
    }
}
