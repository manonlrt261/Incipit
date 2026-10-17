<?php

namespace App\Controller;

use App\Service\RechercheCouvertures;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class CouvertureRechercheController extends AbstractController
{
    // Réserve les recherches aux comptes connectés ; le navigateur ne reçoit jamais la clé SerpApi.
    #[Route('/livre/recherche-couvertures', name: 'app_livre_recherche_couvertures', methods: ['GET'])]
    public function rechercher(Request $request, RechercheCouvertures $couvertures): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Connexion requise.'], 401);
        }
        $recherche = preg_replace('/\s+/u', ' ', trim($request->query->getString('q'))) ?? '';
        if (mb_strlen($recherche) < 2 || mb_strlen($recherche) > 255) {
            return $this->json(['success' => false, 'message' => 'Saisis un titre ou un auteur de 2 à 255 caractères.'], 422);
        }
        try {
            return $this->json(['success' => true, 'images' => $couvertures->rechercher($recherche)]);
        } catch (\RuntimeException $exception) {
            $statut = in_array($exception->getCode(), [429, 502, 503], true) ? $exception->getCode() : 502;

            return $this->json(['success' => false, 'message' => $exception->getMessage()], $statut);
        }
    }
}
