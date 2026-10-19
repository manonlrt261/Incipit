<?php

namespace App\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SagaRechercheController extends AbstractController
{
    #[Route('/livre/recherche-sagas', name: 'app_livre_recherche_sagas', methods: ['GET'])]
    // Ne propose que les sagas déjà reliées aux auteurs explicitement saisis, jamais celles du catalogue global.
    public function rechercher(Request $request, Connection $connexion): JsonResponse
    {
        $texte = trim($request->query->getString('q'));
        $auteurs = $request->query->all('auteurs');
        if ($texte === '' || mb_strlen($texte) > 255 || count($auteurs) > 50) return $this->json(['sagas' => []]);
        $noms = [];
        foreach ($auteurs as $nom) {
            if (!is_string($nom) || mb_strlen($nom) > 255) return $this->json(['sagas' => []]);
            $nom = mb_strtolower(preg_replace('/\s+/u', ' ', trim($nom)));
            if ($nom !== '') $noms[] = $nom;
        }
        if (!$noms) return $this->json(['sagas' => []]);
        $motif = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $texte).'%';
        $sagas = $connexion->fetchFirstColumn("SELECT DISTINCT TRIM(l.saga) AS saga
            FROM livre l JOIN livre_auteur la ON la.livre_id = l.id JOIN auteur a ON a.id = la.auteur_id
            WHERE LOWER(TRIM(a.nom_affichage)) IN (:auteurs)
              AND l.saga IS NOT NULL AND TRIM(l.saga) <> '' AND TRIM(l.saga) LIKE :motif ESCAPE '!'
            ORDER BY saga ASC LIMIT 20", ['auteurs' => array_unique($noms), 'motif' => $motif], ['auteurs' => ArrayParameterType::STRING]);
        return $this->json(['sagas' => $sagas]);
    }
}
