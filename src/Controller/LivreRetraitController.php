<?php

namespace App\Controller;

use App\Entity\Exemplaire;
use App\Entity\Livre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LivreRetraitController extends AbstractController
{
    #[Route(
        '/livre/{id}/retirer',
        name: 'app_livre_retirer',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    // Retire les exemplaires de la collection sans supprimer le livre ni son historique de lecture.
    public function retirer(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (!$this->getUser()) {
            return $this->json([
                'success' => false,
                'message' => 'Connexion requise.'
            ], 401);
        }

        if (!$this->isCsrfTokenValid(
            'retirer_livre',
            (string) $request->request->get('_token', '')
        )) {
            return $this->json([
                'success' => false,
                'message' => 'Session expirée. Actualise la page.'
            ], 403);
        }

        $livre = $entityManager->find(Livre::class, $id);

        if (!$livre) {
            return $this->json([
                'success' => false,
                'message' => 'Livre introuvable.'
            ], 404);
        }

        $exemplaires = $entityManager
            ->getRepository(Exemplaire::class)
            ->findBy(['livre' => $livre, 'dateRetrait' => null]);

        if (!$exemplaires) {
            return $this->json([
                'success' => false,
                'message' => 'Ce livre ne fait plus partie de ta collection.'
            ], 409);
        }

        // Marque tous les exemplaires actifs comme retirés tout en conservant leurs données.
        $dateRetrait = new \DateTime();

        foreach ($exemplaires as $exemplaire) {
            $exemplaire->setDateRetrait($dateRetrait);
        }

        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Le livre a été déplacé dans les livres retirés de ta collection.'
        ]);
    }
}