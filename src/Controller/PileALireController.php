<?php

namespace App\Controller;

use App\Entity\Exemplaire;
use App\Entity\Livre;
use App\Entity\SessionLecture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class PileALireController extends AbstractController
{
    #[Route('/livre/{id}/pile/{action}', name: 'app_pile_action', requirements: ['id' => '\d+', 'action' => 'ajouter|retirer|commencer'], methods: ['POST'])]
    // Gère la sélection et le démarrage d’une lecture sans supprimer les livres ni leur historique.
    public function modifier(int $id, string $action, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Connexion requise.'], 401);
        }

        if (!$this->isCsrfTokenValid('pile_a_lire', $request->request->getString('_token'))) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualise la page.'], 403);
        }
        $livre = $em->find(Livre::class, $id);

        if (!$livre) {
            return $this->json(['success' => false, 'message' => 'Livre introuvable.'], 404);
        }

        // Refuse les souhaits et les titres retirés, même si la requête est fabriquée manuellement.
        if (!$em->getRepository(Exemplaire::class)->findOneBy(['livre' => $livre, 'dateRetrait' => null])) {
            return $this->json(['success' => false, 'message' => 'Choisis un livre que tu possèdes encore.'], 409);
        }

        if ($action === 'commencer' && !$livre->isPileALire()) {
            return $this->json(['success' => false, 'message' => 'Ce livre ne figure plus dans ta pile à lire.'], 409);
        }

        if ($action === 'commencer') {
            // Réutilise une session non terminée ; une relecture garde intactes les lectures précédentes.
            $session = $em->getRepository(SessionLecture::class)->findOneBy(['livre' => $livre], ['dateModification' => 'DESC', 'id' => 'DESC']);

            if (!$session || $session->getStatut() === 'Terminé') {
                $session = (new SessionLecture())->setLivre($livre)->setDateCreation(new \DateTime());
                $em->persist($session);
            }
            $session->setStatut('En cours')->setDateFin(null)->setDateModification(new \DateTime());

            if (!$session->getDateDebut()) {
                $session->setDateDebut(new \DateTime('today', new \DateTimeZone('Europe/Paris')));
            }
        } else {
            $livre->setPileALire($action === 'ajouter');
        }
        $em->flush();

        return $this->json(['success' => true]);
    }
}
