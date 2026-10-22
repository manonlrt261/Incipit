<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class BibliothequePartagee
{
    public function __construct(private readonly Connection $connexion)
    {
    }

    // Conserve les réglages de la bibliothèque sur le compte initial, quel que soit l’administrateur connecté.
    public function proprietaireId(): int
    {
        $id = $this->connexion->fetchOne('SELECT MIN(id) FROM utilisateur');

        if (!$id) {
            throw new \LogicException('La bibliothèque ne possède aucun compte.');
        }

        return (int) $id;
    }
}
