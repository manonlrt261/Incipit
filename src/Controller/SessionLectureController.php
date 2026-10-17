<?php

namespace App\Controller;

use App\Entity\Livre;
use App\Entity\SessionLecture;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class SessionLectureController extends AbstractController
{
    #[Route(
        '/livre/{id}/lecture/ajouter',
        name: 'app_lecture_ajouter',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    // Valide les dates, la note et l’avis avant d’ajouter une session de lecture terminée au livre.
    public function ajouter(
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
            'ajouter_lecture',
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

        $dateDebutTexte = trim((string) $request->request->get('dateDebut', ''));
        $dateFinTexte = trim((string) $request->request->get('dateFin', ''));
        $noteTexte = trim((string) $request->request->get('note', ''));
        $avis = trim((string) $request->request->get('avis', ''));

        // Refuse les dates impossibles au lieu de laisser PHP les corriger automatiquement.
        $convertirDate = static function (string $valeur): ?\DateTime {
            if ($valeur === '') {
                return null;
            }

            $date = \DateTime::createFromFormat('!Y-m-d', $valeur);

            if (!$date || $date->format('Y-m-d') !== $valeur) {
                throw new \InvalidArgumentException('Date invalide.');
            }

            return $date;
        };

        try {
            $dateDebut = $convertirDate($dateDebutTexte);
            $dateFin = $convertirDate($dateFinTexte);

            // Vérifie la cohérence chronologique de la session.
            if ($dateDebut && $dateFin && $dateFin < $dateDebut) {
                throw new \InvalidArgumentException(
                    'La date de fin ne peut pas précéder la date de début.'
                );
            }

            // Accepte une note facultative de 0 à 5 par demi-étoile.
            if ($noteTexte !== '' && (
                !is_numeric($noteTexte)
                || (float) $noteTexte < 0
                || (float) $noteTexte > 5
                || (float) $noteTexte * 2 != floor((float) $noteTexte * 2)
            )) {
                throw new \InvalidArgumentException(
                    'La note doit être comprise entre 0 et 5, par pas de 0,5.'
                );
            }

            $lecture = new SessionLecture();

            $lecture->setLivre($livre);
            $lecture->setDateDebut($dateDebut);
            $lecture->setDateFin($dateFin);
            $lecture->setNote(
                $noteTexte === '' ? null : number_format((float) $noteTexte, 1, '.', '')
            );
            $lecture->setAvis($avis === '' ? null : $avis);

            // Une session ajoutée depuis cette fiche représente une lecture terminée.
            $lecture->setStatut('Terminé');

            $maintenant = new \DateTime();
            $lecture->setDateCreation($maintenant);
            $lecture->setDateModification($maintenant);

            $entityManager->persist($lecture);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'La session de lecture a été enregistrée.',
                'lecture' => [
                    'id' => $lecture->getId(),
                    'date_debut' => $dateDebut?->format('Y-m-d'),
                    'date_fin' => $dateFin?->format('Y-m-d'),
                    'note' => $lecture->getNote(),
                    'avis' => $lecture->getAvis()
                ]
            ]);

        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage()
            ], 422);
        }
    }
}