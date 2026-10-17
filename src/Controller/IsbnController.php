<?php

namespace App\Controller;

use App\Service\RechercheIsbn;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class IsbnController extends AbstractController
{
    // Réserve la recherche aux comptes connectés et conserve les clés API sur le serveur.
    #[Route('/livre/recherche-isbn', name: 'app_livre_recherche_isbn', methods: ['GET'])]
    public function rechercher(Request $request, RechercheIsbn $recherche): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['message' => 'Connexion requise.'], 401);
        }
        $isbn = strtoupper(preg_replace('/[\s-]/', '', $request->query->getString('isbn')));
        if (!preg_match('/^(?:\d{13}|\d{9}[\dX])$/', $isbn)) {
            return $this->json(['message' => 'ISBN invalide.'], 400);
        }

        return $this->json($recherche->rechercher($isbn));
    }
}
