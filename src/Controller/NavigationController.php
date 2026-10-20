<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use App\Service\NomsAuteur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/espace', name: 'app_espace_')]
final class NavigationController extends AbstractController
{
    #[Route('/mes-lectures', name: 'lectures', methods: ['GET'])]
    // Prépare les livres possédés et leurs compteurs à partir du statut de leur dernière session.
    public function lectures(Connection $connection): Response
    {
        // Retient les livres possédés avec le statut et la note de leur session la plus récente.
        $livres = $connection->fetchAllAssociative("
        SELECT
            l.pile_a_lire,
            l.id,
            l.titre,
            l.saga,
            l.image_couverture,
            l.date_creation,
            (SELECT a.nom FROM livre_auteur la JOIN auteur a ON a.id = la.auteur_id
             WHERE la.livre_id = l.id ORDER BY a.id ASC LIMIT 1) AS auteur_nom,
            (SELECT a.prenom FROM livre_auteur la JOIN auteur a ON a.id = la.auteur_id
             WHERE la.livre_id = l.id ORDER BY a.id ASC LIMIT 1) AS auteur_prenom,
            (SELECT a.nom_affichage FROM livre_auteur la JOIN auteur a ON a.id = la.auteur_id
             WHERE la.livre_id = l.id ORDER BY a.id ASC LIMIT 1) AS auteur_affichage,
            COALESCE((SELECT GROUP_CONCAT(g.nom SEPARATOR '|')
                FROM livre_genre lg INNER JOIN genre g ON g.id = lg.genre_id
                WHERE lg.livre_id = l.id), '') AS genres,

            COALESCE((
                SELECT GROUP_CONCAT(
                    COALESCE(
                        NULLIF(a.nom_affichage, ''),
                        CONCAT_WS(' ', a.prenom, a.nom)
                    )
                    SEPARATOR ', '
                )
                FROM livre_auteur la
                INNER JOIN auteur a ON a.id = la.auteur_id
                WHERE la.livre_id = l.id
            ), '') AS auteurs,

            COALESCE((
                SELECT sl.statut
                FROM session_lecture sl
                WHERE sl.livre_id = l.id
                ORDER BY sl.date_modification DESC, sl.id DESC
                LIMIT 1
            ), 'Pas commencé') AS statut,

            (
                SELECT sl.note
                FROM session_lecture sl
                WHERE sl.livre_id = l.id
                ORDER BY sl.date_modification DESC, sl.id DESC
                LIMIT 1
            ) AS note

        FROM livre l

        WHERE EXISTS (
            SELECT 1
            FROM exemplaire e
            WHERE e.livre_id = l.id
            AND e.date_retrait IS NULL
        )

        ORDER BY l.titre ASC, l.id ASC
    ");

        // Aligne le tri sur les fiches auteurs, y compris les anciennes fiches dont le prénom n’était pas séparé.
        foreach ($livres as &$livre) {
            $auteur = NomsAuteur::presenter([
                'nom' => $livre['auteur_nom'] ?? '',
                'prenom' => $livre['auteur_prenom'] ?? '',
                'nom_affichage' => $livre['auteur_affichage'] ?? '',
            ]);
            $livre['auteur_nom'] = $auteur['nom'];
            $livre['auteur_prenom'] = $auteur['prenom'];
        }
        unset($livre);

        // Conserve les mêmes livres retirés et leur historique, désormais présentés sous les lectures.
        $livresRetires = $connection->fetchAllAssociative("
            SELECT l.id, l.titre, l.image_couverture,
                COALESCE((
                    SELECT GROUP_CONCAT(
                        COALESCE(NULLIF(a.nom_affichage, ''), CONCAT_WS(' ', a.prenom, a.nom))
                        SEPARATOR ', '
                    )
                    FROM livre_auteur la
                    INNER JOIN auteur a ON a.id = la.auteur_id
                    WHERE la.livre_id = l.id
                ), '') AS auteurs
            FROM livre l
            WHERE EXISTS (
                SELECT 1 FROM exemplaire e
                WHERE e.livre_id = l.id AND e.date_retrait IS NOT NULL
            )
            AND NOT EXISTS (
                SELECT 1 FROM exemplaire e
                WHERE e.livre_id = l.id AND e.date_retrait IS NULL
            )
            ORDER BY l.date_creation DESC, l.id DESC
        ");

        $statistiques = [
            'tous' => count($livres),
            'en-cours' => 0,
            'termine' => 0,
            'en-pause' => 0,
            'abandonne' => 0,
            'pas-commence' => 0,
        ];

        // Répartit les livres par statut pour alimenter les compteurs et filtres de la page.
        foreach ($livres as $livre) {
            $cle = match ($livre['statut']) {
                'En cours' => 'en-cours',
                'Terminé' => 'termine',
                'En pause' => 'en-pause',
                'Abandonné' => 'abandonne',
                default => 'pas-commence',
            };

            $statistiques[$cle]++;
        }

        return $this->render('lecture/index.html.twig', [
            'livres' => $livres,
            'livresRetires' => $livresRetires,
            'statistiques' => $statistiques,
            'genres' => $connection->fetchAllAssociative(
                'SELECT id, nom FROM genre ORDER BY nom ASC, id ASC'
            ),
        ]);
    }

    #[Route('/mes-prets', name: 'prets', methods: ['GET'])]
    // Redirige l’ancienne adresse des prêts vers leur page actuelle.
    public function prets(): Response
    {
        return $this->redirectToRoute('app_prets_index');
    }

}
