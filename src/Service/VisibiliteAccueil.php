<?php
namespace App\Service;

use Doctrine\DBAL\Connection;

final class VisibiliteAccueil
{
    public function __construct(private readonly Connection $connexion) {}

    public function pourUtilisateur(int $id): array
    {
        $ligne = $this->connexion->fetchAssociative('SELECT afficher_wishlist, afficher_auteurs_favoris, afficher_livres_favoris FROM parametre_confidentialite WHERE utilisateur_id = ?', [$id]);
        return [
            'wishlist' => (bool) ($ligne['afficher_wishlist'] ?? true),
            'auteurs' => (bool) ($ligne['afficher_auteurs_favoris'] ?? true),
            'favoris' => (bool) ($ligne['afficher_livres_favoris'] ?? true),
        ];
    }

    public function publique(): array
    {
        // L’accueil représente la bibliothèque unique du compte propriétaire initial.
        $id = $this->connexion->fetchOne('SELECT id FROM utilisateur ORDER BY id ASC LIMIT 1');
        return $this->pourUtilisateur((int) $id);
    }
}
