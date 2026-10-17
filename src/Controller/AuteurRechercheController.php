<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class AuteurRechercheController extends AbstractController
{
    // Propose les fiches existantes par prénom ou nom, sans confondre les auteurs qui commencent de la même façon.
    #[Route('/livre/recherche-auteurs', name: 'app_livre_recherche_auteurs', methods: ['GET'])]
    public function rechercher(Request $request, Connection $connexion): JsonResponse
    {
        if (!$this->getUser()) return $this->json(['message' => 'Connexion requise.'], 401);
        $texte = preg_replace('/\s+/u', ' ', trim($request->query->getString('q'))) ?? '';
        if ($texte === '' || mb_strlen($texte) > 255) return $this->json(['auteurs' => []]);
        // Échappe les caractères LIKE : un nom saisi reste un texte, jamais un motif SQL libre.
        $motif = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($texte)).'%';
        $auteurs = $connexion->fetchAllAssociative(
            "SELECT id, nom_affichage AS nom FROM auteur WHERE LOWER(nom_affichage) LIKE ? ESCAPE '!' OR LOWER(nom) LIKE ? ESCAPE '!' OR LOWER(prenom) LIKE ? ESCAPE '!' ORDER BY nom_affichage, id LIMIT 20",
            [$motif, $motif, $motif]
        );

        return $this->json(['auteurs' => $auteurs]);
    }
}
