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
        $initial = array_flip(array_column($livres, 'id'));
        usort($livres, static fn (array $a, array $b): int => ($positions[$a['id']] ?? PHP_INT_MAX) <=> ($positions[$b['id']] ?? PHP_INT_MAX) ?: $initial[$a['id']] <=> $initial[$b['id']]);
        return $livres;
    }
}
