<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

final class ClassementAuteursAccueil
{
    public function __construct(private Connection $connexion, private OrdreAccueil $ordre)
    {
    }

    // Charge tous les ex æquo avant de limiter l’affichage aux cinq premiers auteurs.
    public function charger(): array
    {
        $auteurs = $this->connexion->fetchAllAssociative(
            "SELECT a.id, a.nom_affichage, COUNT(DISTINCT s.id) AS nombre_lectures
             FROM auteur a
             JOIN livre_auteur la ON la.auteur_id = a.id
             JOIN session_lecture s ON s.livre_id = la.livre_id
             WHERE s.statut = :statut AND s.date_fin >= :debut AND s.date_fin < :fin
             GROUP BY a.id, a.nom_affichage
             ORDER BY nombre_lectures DESC, a.nom_affichage ASC, a.id ASC",
            ['statut' => 'terminé', 'debut' => date('Y-01-01'), 'fin' => (date('Y') + 1) . '-01-01']
        );
        $auteurs = $this->ordre->appliquer($auteurs, 'auteurs');
        $positions = array_flip(array_column($auteurs, 'id'));
        // Le choix personnel départage seulement les égalités, jamais le nombre de lectures.
        usort($auteurs, static fn (array $a, array $b): int => (int) $b['nombre_lectures'] <=> (int) $a['nombre_lectures']
            ?: $positions[$a['id']] <=> $positions[$b['id']]);

        return $auteurs;
    }
}
