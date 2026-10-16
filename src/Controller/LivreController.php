<?php

namespace App\Controller;

use App\Entity\Livre;
use App\Form\LivreType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LivreController extends AbstractController
{
    #[Route('/livre', name: 'app_livre', methods: ['GET'])]
    // Prépare la bibliothèque et les livres retirés, avec les informations nécessaires aux filtres.
    public function index(Connection $connection): Response
    {
        $sql = "
            SELECT
                l.id,
                l.titre,
                l.sous_titre,
                l.isbn13,
                l.isbn10,
                l.image_couverture,
                l.date_creation,

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
                    SELECT GROUP_CONCAT(g.nom SEPARATOR '|')
                    FROM livre_genre lg
                    INNER JOIN genre g ON g.id = lg.genre_id
                    WHERE lg.livre_id = l.id
                ), '') AS genres,

                COALESCE((
                    SELECT sl.statut
                    FROM session_lecture sl
                    WHERE sl.livre_id = l.id
                    ORDER BY sl.date_modification DESC, sl.id DESC
                    LIMIT 1
                ), 'Pas commencé') AS statut

            FROM livre l
        ";

        // Sélectionne uniquement les livres ayant encore au moins un exemplaire dans la collection.
        $livres = $connection->fetchAllAssociative(
            $sql . "
                WHERE EXISTS (
                    SELECT 1
                    FROM exemplaire e
                    WHERE e.livre_id = l.id
                    AND e.date_retrait IS NULL
                )
                ORDER BY l.date_creation DESC, l.id DESC
            "
        );

        // Classe parmi les livres retirés ceux qui n’ont plus aucun exemplaire actif.
        $livresRetires = $connection->fetchAllAssociative(
            $sql . "
                WHERE EXISTS (
                    SELECT 1
                    FROM exemplaire e
                    WHERE e.livre_id = l.id
                    AND e.date_retrait IS NOT NULL
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM exemplaire e
                    WHERE e.livre_id = l.id
                    AND e.date_retrait IS NULL
                )
                ORDER BY l.date_creation DESC, l.id DESC
            "
        );

                $genres = $connection->fetchAllAssociative(
            'SELECT id, nom FROM genre ORDER BY id ASC'
        );

        return $this->render('livre/index.html.twig', [
            'livres' => $livres,
            'livresRetires' => $livresRetires,
            'genres' => $genres,
        ]);
    }

    #[Route(
        '/livre/ajouter',
        name: 'app_livre_ajouter',
        methods: ['GET', 'POST']
    )]
    // Traite le formulaire Symfony d’ajout de livre et affiche ses éventuelles erreurs.
    public function ajouter(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $livre = new Livre();

        $form = $this->createForm(LivreType::class, $livre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($livre);
            $entityManager->flush();

            $this->addFlash(
                'success',
                'Le livre a été ajouté à votre bibliothèque.'
            );

            return $this->redirectToRoute('app_livre_ajouter');
        }

        return $this->render('livre/ajouter.html.twig', [
            'form' => $form->createView(),
        ]);
    }


#[Route('/livre/{id}/details', name: 'app_livre_details', requirements: ['id' => '\d+'], methods: ['GET'])]
// Renvoie les informations et l’historique de lecture d’un livre encore présent dans la collection.
public function details(int $id, Connection $connection): Response
{
    $livre = $connection->fetchAssociative(
        '
        SELECT
            l.id,
            l.titre,
            l.sous_titre,
            l.isbn13,
            l.isbn10,
            l.resume,
            l.date_publication,
            l.nombre_pages,
            l.langue,
            l.image_couverture,
            l.saga,
            l.tome,
            l.edition,

            COALESCE((
                SELECT GROUP_CONCAT(
                    COALESCE(
                        NULLIF(a.nom_affichage, \'\'),
                        CONCAT_WS(\' \', a.prenom, a.nom)
                    )
                    SEPARATOR \', \'
                )
                FROM livre_auteur la
                INNER JOIN auteur a ON a.id = la.auteur_id
                WHERE la.livre_id = l.id
            ), \'\') AS auteurs,

            COALESCE((
                SELECT GROUP_CONCAT(g.nom SEPARATOR \', \')
                FROM livre_genre lg
                INNER JOIN genre g ON g.id = lg.genre_id
                WHERE lg.livre_id = l.id
            ), \'\') AS genres

        FROM livre l
        WHERE l.id = :id
        AND EXISTS (
            SELECT 1
            FROM exemplaire e
            WHERE e.livre_id = l.id
            AND e.date_retrait IS NULL
        )
        ',
        ['id' => $id]
    );

    if (!$livre) {
        return $this->json([
            'success' => false,
            'message' => 'Livre introuvable dans votre collection.'
        ], 404);
    }

    // Charge les sessions de ce livre de la plus récente à la plus ancienne.
    $lectures = $connection->fetchAllAssociative(
        '
        SELECT
            id,
            statut,
            note,
            avis,
            date_debut,
            date_fin
        FROM session_lecture
        WHERE livre_id = :id
        ORDER BY date_creation DESC, id DESC
        ',
        ['id' => $id]
    );

    return $this->json([
        'success' => true,
        'livre' => $livre,
        'lectures' => $lectures
    ]);
}

}
