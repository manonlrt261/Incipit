<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AuteurController extends AbstractController
{
    #[Route('/espace/mes-auteurs', name: 'app_auteurs', methods: ['GET'])]
    // Affiche les mêmes fiches d’auteurs que celles utilisées par les suggestions des formulaires de livres.
    public function index(Connection $connexion): Response
    {
        $auteurs = $connexion->fetchAllAssociative("SELECT a.id,
            COALESCE(NULLIF(a.nom_affichage, ''), CONCAT_WS(' ', a.prenom, a.nom)) AS nom,
            COUNT(DISTINCT la.livre_id) AS nombre
            FROM auteur a INNER JOIN livre_auteur la ON la.auteur_id = a.id
            GROUP BY a.id, a.nom_affichage, a.prenom, a.nom
            ORDER BY nom ASC, a.id ASC");
        return $this->render('auteur/index.html.twig', ['auteurs' => $auteurs]);
    }

    #[Route('/espace/mes-auteurs/{id}', name: 'app_auteur_fiche', requirements: ['id' => '\d+'], methods: ['GET'])]
    // Répartit les livres liés à cette fiche sans dupliquer les éditions ou les exemplaires dans un même onglet.
    public function fiche(int $id, Connection $connexion): Response
    {
        $auteur = $connexion->fetchAssociative("SELECT id, COALESCE(NULLIF(nom_affichage, ''), CONCAT_WS(' ', prenom, nom)) AS nom FROM auteur WHERE id = ?", [$id]);
        if (!$auteur) throw $this->createNotFoundException('Auteur introuvable.');
        $livres = $connexion->fetchAllAssociative("SELECT l.id, l.titre, l.image_couverture,
            EXISTS(SELECT 1 FROM exemplaire e WHERE e.livre_id = l.id AND e.date_retrait IS NULL) AS possede,
            EXISTS(SELECT 1 FROM souhait s WHERE s.livre_id = l.id AND s.statut = 'souhaité') AS souhaite,
            EXISTS(SELECT 1 FROM pret p JOIN exemplaire e ON e.id = p.exemplaire_id WHERE e.livre_id = l.id AND p.statut = 'En cours' AND p.date_retour_effective IS NULL) AS prete,
            EXISTS(SELECT 1 FROM exemplaire e WHERE e.livre_id = l.id AND e.date_retrait IS NOT NULL) AS retire,
            COALESCE((SELECT sl.statut FROM session_lecture sl WHERE sl.livre_id = l.id ORDER BY sl.date_modification DESC, sl.id DESC LIMIT 1), 'Pas commencé') AS statut
            FROM livre l JOIN livre_auteur la ON la.livre_id = l.id
            WHERE la.auteur_id = ? ORDER BY l.titre ASC, l.id ASC", [$id]);
        // Un livre prêté reste également possédé ; un titre retiré n’a plus aucun exemplaire actif.
        $onglets = [
            'possedes' => ['titre' => 'Livres possédés', 'livres' => []],
            'wishlist' => ['titre' => 'Wishlist', 'livres' => []],
            'pretes' => ['titre' => 'Livres prêtés', 'livres' => []],
            'retires' => ['titre' => 'Retirés de ma collection', 'livres' => []],
        ];
        foreach ($livres as $livre) {
            if ($livre['possede']) $onglets['possedes']['livres'][] = $livre;
            if ($livre['souhaite']) $onglets['wishlist']['livres'][] = $livre;
            if ($livre['prete']) $onglets['pretes']['livres'][] = $livre;
            if ($livre['retire'] && !$livre['possede']) $onglets['retires']['livres'][] = $livre;
        }
        return $this->render('auteur/fiche.html.twig', ['auteur' => $auteur, 'onglets' => $onglets]);
    }
}
