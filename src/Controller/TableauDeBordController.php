<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TableauDeBordController extends AbstractController
{
    #[Route('/tableau-de-bord', name: 'app_tableau_de_bord', methods: ['GET'])]
    // Rassemble les compteurs, les lectures en cours et les derniers ajouts du tableau de bord.
    public function index(Connection $connection): Response
    {
        // Compte les livres distincts plutôt que le nombre d’exemplaires.
        $livresPossedes = (int) $connection->fetchOne(
            'SELECT COUNT(DISTINCT livre_id) FROM exemplaire'
        );

        $livresTermines = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT livre_id)
             FROM session_lecture
             WHERE LOWER(statut) IN ('terminé', 'termine', 'terminés', 'termines')"
        );

        $livresALire = (int) $connection->fetchOne(
            'SELECT COUNT(*)
             FROM livre l
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM session_lecture s
                 WHERE s.livre_id = l.id
             )'
        );

        $lecturesEnCours = (int) $connection->fetchOne(
            "SELECT COUNT(DISTINCT livre_id)
             FROM session_lecture
             WHERE LOWER(statut) IN ('en cours', 'en pause')"
        );

        // Charge les sessions en cours ou en pause pour la liste du tableau de bord.
        $lectures = $connection->fetchAllAssociative(
            "SELECT
                l.id,
                l.titre,
                l.image_couverture,
                s.statut,
                (
                    SELECT GROUP_CONCAT(
                        COALESCE(a.nom_affichage, CONCAT_WS(' ', a.prenom, a.nom))
                        SEPARATOR ', '
                    )
                    FROM livre_auteur la
                    INNER JOIN auteur a ON a.id = la.auteur_id
                    WHERE la.livre_id = l.id
                ) AS auteurs
             FROM session_lecture s
             INNER JOIN livre l ON l.id = s.livre_id
             WHERE LOWER(s.statut) IN ('en cours', 'en pause')
             ORDER BY s.date_modification DESC, s.id DESC"
        );

        // Exclut les simples souhaits et les livres retirés en exigeant un exemplaire actif.
        $derniersAjouts = $connection->fetchAllAssociative(
            "SELECT
                l.id,
                l.titre,
                l.image_couverture,
                l.date_creation,
                (
                    SELECT GROUP_CONCAT(
                        COALESCE(a.nom_affichage, CONCAT_WS(' ', a.prenom, a.nom))
                        SEPARATOR ', '
                    )
                    FROM livre_auteur la
                    INNER JOIN auteur a ON a.id = la.auteur_id
                    WHERE la.livre_id = l.id
                ) AS auteurs
             FROM livre l
             WHERE EXISTS (
                 SELECT 1
                 FROM exemplaire e
                 WHERE e.livre_id = l.id
                   AND e.date_retrait IS NULL
             )
             ORDER BY l.date_creation DESC, l.id DESC
             LIMIT 4"
        );

        // Calcule la moyenne uniquement sur les sessions qui possèdent une note.
        $noteMoyenne = $connection->fetchOne(
            'SELECT ROUND(AVG(note), 1)
             FROM session_lecture
             WHERE note IS NOT NULL'
        );

        return $this->render('tableau_de_bord/index.html.twig', [
            'livresPossedes' => $livresPossedes,
            'livresTermines' => $livresTermines,
            'livresALire' => $livresALire,
            'lecturesEnCours' => $lecturesEnCours,
            'lectures' => $lectures,
            'derniersAjouts' => $derniersAjouts,
            'noteMoyenne' => $noteMoyenne,
            'genresFavoris' => [],
        ]);
    }
}
