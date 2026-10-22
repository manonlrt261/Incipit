<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class VisibiliteAccueil
{
    public function __construct(private readonly Connection $connexion)
    {
    }

    // Lit les réglages du compte ; chaque section reste visible si aucun réglage n’a encore été enregistré.
    public function pourUtilisateur(int $id): array
    {
        $ligne = $this->connexion->fetchAssociative('SELECT afficher_wishlist, afficher_auteurs_favoris, afficher_livres_favoris FROM parametre_confidentialite WHERE utilisateur_id = ?', [$id]);

        return [
            'wishlist' => (bool) ($ligne['afficher_wishlist'] ?? true),

            'auteurs' => (bool) ($ligne['afficher_auteurs_favoris'] ?? true),

            'favoris' => (bool) ($ligne['afficher_livres_favoris'] ?? true),
        ];
    }

    // Applique à l’accueil public les réglages du premier compte créé.
    public function publique(): array
    {
        $id = $this->connexion->fetchOne('SELECT id FROM utilisateur ORDER BY id ASC LIMIT 1');

        return $this->pourUtilisateur((int) $id);
    }
}
