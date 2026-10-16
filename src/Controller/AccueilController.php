<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_accueil', methods: ['GET'])]
    // Prépare les sections de l’accueil public en tenant compte de leurs réglages de visibilité.
    public function index(Connection $connection, \App\Service\VisibiliteAccueil $reglages): Response
    {
        // Charge les réglages qui déterminent quelles sections peuvent apparaître publiquement.
        $visibilite = $reglages->publique();
        // Les 5 livres dont la dernière lecture terminée et notée
        // a obtenu la note maximale.
        $livres = $this->chargerLivresFavoris($connection, 5);

        // Les 5 auteurs ayant le plus de lectures terminées
        // pendant l'année civile en cours.
        $auteurs = $connection->fetchAllAssociative(
            "SELECT a.id, a.nom_affichage,
                    COUNT(DISTINCT s.id) AS nombre_lectures
             FROM auteur a
             INNER JOIN livre_auteur la ON la.auteur_id = a.id
             INNER JOIN session_lecture s ON s.livre_id = la.livre_id
             WHERE s.statut = :statut
               AND s.date_fin >= :debut
               AND s.date_fin < :fin
             GROUP BY a.id, a.nom_affichage
             ORDER BY nombre_lectures DESC, a.nom_affichage ASC
             LIMIT 5",
            [
                'statut' => 'terminé',
                'debut' => date('Y-01-01'),
                'fin' => (date('Y') + 1) . '-01-01',
            ]
        );

        // Les 5 derniers livres encore présents dans la wishlist.
        $wishlist = $connection->fetchAllAssociative(
            "SELECT l.id, l.titre, l.image_couverture,
                    GROUP_CONCAT(
                        DISTINCT a.nom_affichage
                        ORDER BY a.nom_affichage
                        SEPARATOR ', '
                    ) AS auteurs
             FROM souhait sh
             INNER JOIN livre l ON l.id = sh.livre_id
             LEFT JOIN livre_auteur la ON la.livre_id = l.id
             LEFT JOIN auteur a ON a.id = la.auteur_id
             WHERE sh.statut = :statut AND sh.publique = 1
             GROUP BY sh.id, l.id, l.titre, l.image_couverture, sh.date_ajout
             ORDER BY sh.date_ajout DESC, sh.id DESC
             LIMIT 5",
            ['statut' => 'souhaité']
        );

        return $this->render('accueil/index.html.twig', [
            'livres' => $livres,
            'auteurs' => $auteurs,
            'wishlist' => $wishlist,
            'visibilite' => $visibilite,
        ]);
    }

#[Route('/mes-livres-mieux-notes', name: 'app_livres_mieux_notes', methods: ['GET'])]
// Affiche les livres favoris lorsque leur section publique est visible.
public function livresMieuxNotes(Connection $connection, \App\Service\VisibiliteAccueil $reglages): Response
{
    if (!$reglages->publique()['favoris']) throw $this->createNotFoundException();
    $livres = $this->chargerLivresFavoris($connection);

    return $this->render('accueil/livres-mieux-notes.html.twig', [
        'livres' => $livres,
    ]);
}

    #[Route('/ma-wishlist', name: 'app_wishlist', methods: ['GET'])]
    // Affiche la wishlist publique en respectant la visibilité de la section et de chaque souhait.
    public function wishlist(\App\Repository\SouhaitRepository $repository, \App\Service\VisibiliteAccueil $reglages): Response
    {
        if (!$reglages->publique()['wishlist']) throw $this->createNotFoundException();
        return $this->render('wishlist/public.html.twig', [
            'souhaits' => $repository->findBy(['statut' => 'souhaité', 'publique' => true], ['dateAjout' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    // Partage la sélection des livres notés cinq étoiles entre l’accueil et la liste complète.
    private function chargerLivresFavoris(Connection $connection, ?int $limite = null): array
    {
        $sql = "SELECT l.id, l.titre, l.image_couverture, s.note,
                GROUP_CONCAT(
                    DISTINCT a.nom_affichage
                    ORDER BY a.nom_affichage
                    SEPARATOR ', '
                ) AS auteurs
         FROM livre l
         INNER JOIN session_lecture s ON s.livre_id = l.id
         LEFT JOIN livre_auteur la ON la.livre_id = l.id
         LEFT JOIN auteur a ON a.id = la.auteur_id
         WHERE s.statut = :statut
           AND s.note = 5
           AND s.id = (
               SELECT s2.id
               FROM session_lecture s2
               WHERE s2.livre_id = l.id
                 AND s2.statut = :statut
                 AND s2.note IS NOT NULL
               ORDER BY s2.date_fin DESC, s2.id DESC
               LIMIT 1
           )
         GROUP BY l.id, l.titre, l.image_couverture, s.note, s.date_fin
         ORDER BY s.date_fin DESC, l.id DESC";

        if ($limite !== null) {
            $sql .= ' LIMIT '.$limite;
        }

        return $connection->fetchAllAssociative($sql, ['statut' => 'terminé']);
    }
}
