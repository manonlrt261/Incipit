<?php

namespace App\Controller;

use App\Entity\Livre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LivreModificationController extends AbstractController
{
    #[Route(
        '/livre/{id}/modifier',
        name: 'app_livre_modifier',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    // Valide et enregistre les informations bibliographiques modifiées depuis la fiche du livre.
    public function modifier(
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
            'ajouter_livre',
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

        $titre = trim((string) $request->request->get('titre', ''));

        if ($titre === '' || mb_strlen($titre) > 255) {
            return $this->json([
                'success' => false,
                'message' => 'Le titre est obligatoire (255 caractères maximum).'
            ], 422);
        }

        // Nettoie les champs facultatifs et vérifie leur longueur avant la mise à jour.
        $texte = static function (Request $request, string $nom, int $maximum): ?string {
            $valeur = trim((string) $request->request->get($nom, ''));

            if (mb_strlen($valeur) > $maximum) {
                throw new \InvalidArgumentException(
                    sprintf('Le champ « %s » est trop long.', $nom)
                );
            }

            return $valeur === '' ? null : $valeur;
        };

        try {
            $sousTitre = $texte($request, 'sousTitre', 255);
            $saga = $texte($request, 'saga', 255);
            $edition = $texte($request, 'edition', 255);
            $langue = $texte($request, 'langue', 50);
            $resume = $texte($request, 'resume', 100000);

            // Retire les espaces et les tirets des ISBN avant de vérifier leur format.
            $isbn13 = strtoupper(preg_replace(
                '/[\s-]+/',
                '',
                (string) $request->request->get('isbn13', '')
            ));

            $isbn10 = strtoupper(preg_replace(
                '/[\s-]+/',
                '',
                (string) $request->request->get('isbn10', '')
            ));

            if ($isbn13 !== '' && !preg_match('/^\d{13}$/', $isbn13)) {
                throw new \InvalidArgumentException('ISBN-13 invalide.');
            }

            if ($isbn10 !== '' && !preg_match('/^\d{9}[\dX]$/', $isbn10)) {
                throw new \InvalidArgumentException('ISBN-10 invalide.');
            }

            $tome = $request->request->get('tome', '');
            $nombrePages = $request->request->get('nombrePages', '');

            foreach (['tome' => $tome, 'nombrePages' => $nombrePages] as $nom => $nombre) {
                if ($nombre !== '' && (
                    !ctype_digit((string) $nombre)
                    || (int) $nombre < 1
                    || (int) $nombre > 100000
                )) {
                    throw new \InvalidArgumentException(
                        sprintf('Valeur invalide pour « %s ».', $nom)
                    );
                }
            }

            // Vérifie que la date saisie correspond réellement au format attendu.
            $dateTexte = trim((string) $request->request->get('datePublication', ''));
            $datePublication = null;

            if ($dateTexte !== '') {
                $datePublication = \DateTime::createFromFormat('!Y-m-d', $dateTexte);

                if (!$datePublication || $datePublication->format('Y-m-d') !== $dateTexte) {
                    throw new \InvalidArgumentException(
                        'La date de publication est invalide.'
                    );
                }
            }

            $livre->setTitre($titre);
            $livre->setSousTitre($sousTitre);
            $livre->setSaga($saga);
            $livre->setTome($tome === '' ? null : (int) $tome);
            $livre->setEdition($edition);
            $livre->setLangue($langue);
            $livre->setIsbn13($isbn13 ?: null);
            $livre->setIsbn10($isbn10 ?: null);
            $livre->setResume($resume);
            $livre->setDatePublication($datePublication);
            $livre->setNombrePages(
                $nombrePages === '' ? null : (int) $nombrePages
            );
            $livre->setDateModification(new \DateTime());

            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Le livre a bien été modifié.',
                'livre' => [
                    'id' => $livre->getId(),
                    'titre' => $livre->getTitre()
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