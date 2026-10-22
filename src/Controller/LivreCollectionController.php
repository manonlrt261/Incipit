<?php

namespace App\Controller;

use App\Entity\Exemplaire;
use App\Entity\Livre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class LivreCollectionController extends AbstractController
{
    #[Route('/livre/{id}/rajouter', name: 'app_livre_rajouter', requirements: ['id' => '\d+'], methods: ['POST'])]
    // Réintègre un livre retiré dans la collection sans effacer ses anciens exemplaires.
    public function rajouter(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('rajouter_livre', $request->request->getString('_token'))) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualisez la page.'], 403);
        }
        // Verrouille la fiche pour que deux clics concurrents ne créent pas deux exemplaires actifs.
        $em->getConnection()->beginTransaction();

        try {
            if (!$em->getConnection()->fetchOne('SELECT id FROM livre WHERE id = ? FOR UPDATE', [$id])) {
                throw $this->createNotFoundException('Livre introuvable.');
            }
            $livre = $em->find(Livre::class, $id);
            $exemplaires = $em->getRepository(Exemplaire::class);

            if (!$exemplaires->findOneBy(['livre' => $livre, 'dateRetrait' => null])) {
                if (!$em->getConnection()->fetchOne('SELECT id FROM exemplaire WHERE livre_id = ? AND date_retrait IS NOT NULL LIMIT 1', [$id])) {
                    $em->getConnection()->rollBack();

                    return $this->json(['success' => false, 'message' => 'Ce livre ne fait pas partie des livres retirés.'], 409);
                }
                // Ajoute un nouvel exemplaire en conservant les anciens retraits, prêts et sessions de lecture.
                $em->persist((new Exemplaire())->setLivre($livre)->setDateCreation(new \DateTime()));
                $livre->setDateModification(new \DateTime());
                $em->flush();
            }
            $em->getConnection()->commit();

            return $this->json(['success' => true]);
        } catch (\Throwable $exception) {
            if ($em->getConnection()->isTransactionActive()) {
                $em->getConnection()->rollBack();
            }
            throw $exception;
        }
    }
}
