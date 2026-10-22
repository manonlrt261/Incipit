<?php

namespace App\Controller;

use App\Entity\Livre;
use App\Entity\SessionLecture;
use App\Service\DonneesLecture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SessionLectureController extends AbstractController
{
    #[Route('/livre/{id}/lecture/ajouter', name: 'app_lecture_ajouter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Route('/livre/{id}/lecture/{lectureId}/modifier', name: 'app_lecture_modifier', requirements: ['id' => '\d+', 'lectureId' => '\d+'], methods: ['POST'])]
    // Réutilise les validations pour créer une session ou modifier précisément celle sélectionnée.
    public function ajouter(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        DonneesLecture $donneesLecture,
        ?int $lectureId = null
    ): JsonResponse {
        if (!$this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Connexion requise.'], 401);
        }

        if (!$this->isCsrfTokenValid('ajouter_lecture', $request->request->getString('_token'))) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualise la page.'], 403);
        }
        $livre = $entityManager->find(Livre::class, $id);

        if (!$livre) {
            return $this->json(['success' => false, 'message' => 'Livre introuvable.'], 404);
        }

        // Le livre de l’URL doit correspondre à celui de la session, même si un identifiant est modifié dans la requête.
        $lecture = $lectureId === null ? new SessionLecture() : $entityManager->getRepository(SessionLecture::class)
            ->findOneBy(['id' => $lectureId, 'livre' => $livre]);

        if (!$lecture) {
            return $this->json(['success' => false, 'message' => 'Session de lecture introuvable.'], 404);
        }

        try {
            $donnees = $donneesLecture->lire($request, 'Terminé');
            $avis = trim($request->request->getString('avis'));

            if (mb_strlen($avis) > 100000) {
                throw new \InvalidArgumentException('Le commentaire est trop long.');
            }
            $lecture->setLivre($livre);
            $donneesLecture->appliquer($lecture, $donnees);
            $lecture->setAvis($avis === '' ? null : $avis);

            if ($lectureId === null) {
                $lecture->setDateCreation(new \DateTime());
                $entityManager->persist($lecture);
            }
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'La session de lecture a été enregistrée.',
                'lecture' => [
                    'id' => $lecture->getId(),
                    'statut' => $lecture->getStatut(),
                    'date_debut' => $lecture->getDateDebut()?->format('Y-m-d'),
                    'date_fin' => $lecture->getDateFin()?->format('Y-m-d'),
                    'note' => $lecture->getNote(),
                    'avis' => $lecture->getAvis(),
                ],
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }

    #[Route('/livre/{id}/lecture/{lectureId}/supprimer', name: 'app_lecture_supprimer', requirements: ['id' => '\d+', 'lectureId' => '\d+'], methods: ['POST'])]
    // Retire uniquement la session choisie ; le livre et les autres lectures restent conservés.
    public function supprimer(int $id, int $lectureId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['success' => false, 'message' => 'Connexion requise.'], 401);
        }

        if (!$this->isCsrfTokenValid('ajouter_lecture', $request->request->getString('_token'))) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualise la page.'], 403);
        }
        $lecture = $entityManager->getRepository(SessionLecture::class)->findOneBy(['id' => $lectureId, 'livre' => $id]);

        if (!$lecture) {
            return $this->json(['success' => false, 'message' => 'Session de lecture introuvable.'], 404);
        }
        $entityManager->remove($lecture);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'La session de lecture a été supprimée.']);
    }
}
