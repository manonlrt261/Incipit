<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_accueil', methods: ['GET'])]
    public function index(Connection $connection): Response
    {
        // Les 5 livres dont la dernière lecture terminée et notée
        // a obtenu la note maximale.
        $livres = $connection->fetchAllAssociative(
            "SELECT l.id, l.titre, l.image_couverture, s.note,
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
             ORDER BY s.date_fin DESC, l.id DESC
             LIMIT 5",
            ['statut' => 'terminé']
        );

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
             WHERE sh.statut = :statut
             GROUP BY sh.id, l.id, l.titre, l.image_couverture, sh.date_ajout
             ORDER BY sh.date_ajout DESC, sh.id DESC
             LIMIT 5",
            ['statut' => 'souhaité']
        );

        return $this->render('accueil/index.html.twig', [
            'livres' => $livres,
            'auteurs' => $auteurs,
            'wishlist' => $wishlist,
        ]);
    }

    // Routes provisoires pour les pages que nous coderons ensuite.

    #[Route('/connexion', name: 'app_connexion', methods: ['GET'])]
    public function connexion(): Response
    {
        return $this->render('accueil/a-venir.html.twig', [
            'titre' => 'Connexion',
        ]);
    }

    #[Route('/mes-livres-mieux-notes', name: 'app_livres_mieux_notes', methods: ['GET'])]
    public function livresMieuxNotes(): Response
    {
        return $this->render('accueil/a-venir.html.twig', [
            'titre' => 'Mes livres les mieux notés',
        ]);
    }

    #[Route('/ma-wishlist', name: 'app_wishlist', methods: ['GET'])]
    public function wishlist(): Response
    {
        return $this->render('accueil/a-venir.html.twig', [
            'titre' => 'Ma wishlist',
        ]);
    }
}
