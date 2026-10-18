<?php

namespace App\Controller;

use App\Service\AuteursLivre;
use App\Service\GenresLivre;
use App\Service\DoublonsLivre;
use App\Service\DonneesLecture;
use App\Service\CouvertureLivre;
use App\Entity\Exemplaire;
use App\Entity\Genre;
use App\Entity\Livre;
use App\Entity\SessionLecture;
use App\Entity\Souhait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class LivreModalController extends AbstractController
{
    #[Route('/wishlist/ajouter', name: 'app_wishlist_ajouter', methods: ['POST'])]
    #[Route('/wishlist/{id}/modifier', name: 'app_wishlist_modifier', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[Route(
        '/livre/modal/ajouter',
        name: 'app_livre_modal_ajouter',
        methods: ['POST']
    )]
    // Valide le formulaire partagé puis enregistre un livre possédé ou un souhait, selon la route appelée.
    public function ajouter(
        Request $request,
        EntityManagerInterface $entityManager,
        DonneesLecture $donneesLecture,
        AuteursLivre $gestionAuteurs,
        CouvertureLivre $couvertures,
        GenresLivre $gestionGenres,
        DoublonsLivre $doublons,
        ?int $id = null
    ): JsonResponse {
        // Identifie le parcours wishlist pour éviter de créer un exemplaire possédé lors d’un simple souhait.
        $modeWishlist = str_starts_with((string) $request->attributes->get('_route'), 'app_wishlist_');
        $souhait = null;
        $modificationWishlist = $modeWishlist && $id !== null;
        if (!$this->getUser()) {
            return $this->json(
                ['success' => false, 'message' => 'Connexion requise.'],
                401
            );
        }

        if (!$this->isCsrfTokenValid(
            'ajouter_livre',
            (string) $request->request->get('_token', '')
        )) {
            return $this->json(
                ['success' => false, 'message' => 'Session expirée. Actualise la page.'],
                403
            );
        }

        if ($modeWishlist && $id !== null) {
            $souhait = $entityManager->find(Souhait::class, $id);
            if (!$souhait || $souhait->getStatut() !== 'souhaité') {
                return $this->json(['success' => false, 'message' => 'Souhait introuvable.'], 404);
            }
        }

        $titre = trim((string) $request->request->get('titre', ''));

        if ($titre === '' || mb_strlen($titre) > 255) {
            return $this->json(
                ['success' => false, 'message' => 'Le titre du livre est obligatoire (255 caractères maximum).'],
                422
            );
        }

        // Nettoie les champs facultatifs et refuse les textes dépassant leur longueur autorisée.
        $valeur = static function (Request $request, string $champ, int $maximum): ?string {
            $texte = trim((string) $request->request->get($champ, ''));

            if ($texte === '') {
                return null;
            }

            if (mb_strlen($texte) > $maximum) {
                throw new \InvalidArgumentException(
                    sprintf('Le champ « %s » est trop long.', $champ)
                );
            }

            return $texte;
        };

        try {
            // Valide le prix facultatif et limite le lien d’achat aux adresses HTTP ou HTTPS avant toute écriture.
            $prixEstime = null;
            $lienAchat = null;
            if ($modeWishlist) {
                $prixEstime = $valeur($request, 'prixEstime', 20);
                if ($prixEstime !== null) {
                    $prixEstime = str_replace(',', '.', $prixEstime);
                    if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $prixEstime)) {
                        throw new \InvalidArgumentException('Le prix doit être positif, avec deux décimales maximum.');
                    }
                }
                $lienAchat = $valeur($request, 'lienAchat', 2048);
                if ($lienAchat !== null && (!filter_var($lienAchat, FILTER_VALIDATE_URL)
                    || !in_array(strtolower((string) parse_url($lienAchat, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                    throw new \InvalidArgumentException('Le lien doit être une adresse HTTP ou HTTPS valide.');
                }
            }

            $sousTitre = $valeur($request, 'sousTitre', 255);
            $saga = $valeur($request, 'saga', 255);
            $edition = $valeur($request, 'edition', 255);
            $langue = $valeur($request, 'langue', 50);
            $imageCouverture = $valeur($request, 'imageCouverture', 2048);
            $resume = $valeur($request, 'resume', 100000);

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

            // Accepte uniquement une couverture locale autorisée ou une URL HTTPS valide.
            if ($imageCouverture !== null) {
                $cheminLocal = str_starts_with(
                    $imageCouverture,
                    'uploads/couvertures/'
                ) && !str_contains($imageCouverture, '..');

                $urlHttps = filter_var(
                    $imageCouverture,
                    FILTER_VALIDATE_URL
                ) && parse_url($imageCouverture, PHP_URL_SCHEME) === 'https';

                if (!$cheminLocal && !$urlHttps) {
                    throw new \InvalidArgumentException(
                        'La couverture doit être une URL HTTPS valide.'
                    );
                }
            }

            $tome = $request->request->get('tome', '');
            $nombrePages = $request->request->get('nombrePages', '');

            foreach ([
                'tome' => $tome,
                'nombrePages' => $nombrePages,
            ] as $champ => $nombre) {
                if ($nombre !== '' && (
                    !ctype_digit((string) $nombre) ||
                    (int) $nombre < 1 ||
                    (int) $nombre > 100000
                )) {
                    throw new \InvalidArgumentException(
                        sprintf('Valeur invalide pour « %s ».', $champ)
                    );
                }
            }

            $datePublication = null;
            $dateTexte = trim((string) $request->request->get('datePublication', ''));

            if ($dateTexte !== '') {
                $datePublication = \DateTime::createFromFormat(
                    '!Y-m-d',
                    $dateTexte
                );

                if (!$datePublication ||
                    $datePublication->format('Y-m-d') !== $dateTexte) {
                    throw new \InvalidArgumentException(
                        'La date de publication est invalide.'
                    );
                }
            }

            $lecture = $donneesLecture->lire($request);
            $statut = $lecture['statut'];

            $auteurs = $request->request->all('auteurs');
            $genres = $request->request->all('genres');

            $auteursNettoyes = $gestionAuteurs->nettoyer($auteurs);

            if (count($genres) > 50) {
                throw new \InvalidArgumentException('Trop de genres sélectionnés.');
            }

            $idsGenres = [];

            foreach ($genres as $id) {
                if (!ctype_digit((string) $id) || (int) $id < 1) {
                    throw new \InvalidArgumentException(
                        'Un genre sélectionné est invalide.'
                    );
                }

                $idsGenres[(int) $id] = (int) $id;
            }

            // Vérifie que les genres transmis existent avant de les associer au livre.
            $genresSelectionnes = [];

            foreach ($idsGenres as $id) {
                $genre = $entityManager->find(Genre::class, $id);

                if (!$genre) {
                    throw new \InvalidArgumentException(
                        'Un genre sélectionné n’existe pas.'
                    );
                }

                $genresSelectionnes[] = $genre;
            }

        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        // Avertit avant toute écriture et permet de confirmer volontairement un autre exemplaire.
        if (!$modeWishlist && !$request->request->getBoolean('confirmerDoublon')
            && $doublons->existe($titre, $auteursNettoyes)) {
            return $this->json([
                'success' => false,
                'code' => 'livre_deja_present',
                'message' => 'Attention ce livre se trouve déjà dans votre bibliothèque',
            ], 409);
        }

        // Les fichiers de couverture sont acceptés pour les livres possédés comme pour les souhaits.
        $fichierCouverture = $request->files->get('fichierCouverture');
        try {
            $couvertures->valider($fichierCouverture);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }
        $nouvelleCouverture = null;
        $connexion = $entityManager->getConnection();
        // Enregistre ensemble le livre et ses associations pour éviter une création partielle.
        $connexion->beginTransaction();

        try {
            $livre = $souhait?->getLivre() ?? new Livre();
            // Remplace les associations du souhait modifié pour ne pas cumuler anciens auteurs et genres.
            if ($souhait) {
                $livre->getAuteurs()->clear();
                $livre->getGenres()->clear();
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
            // Enregistre la couverture sous un nom aléatoire ; son chemin est conservé pour le nettoyage en cas d’échec.
            if ($fichierCouverture) {
                $nouvelleCouverture = $couvertures->enregistrer($fichierCouverture);
                $imageCouverture = $nouvelleCouverture;
            }
            $livre->setImageCouverture($imageCouverture);
            $livre->setDatePublication($datePublication);
            $livre->setNombrePages(
                $nombrePages === '' ? null : (int) $nombrePages
            );

            // Applique le genre par défaut et retire Sans genre si un classement précis est choisi.
            $gestionGenres->associer($livre, $genresSelectionnes);

            $gestionAuteurs->associer($livre, $auteursNettoyes);

            $entityManager->persist($livre);

            // Enregistre visibilité, prix et lien sur le souhait ; un ajout en bibliothèque crée un exemplaire et une session.
            if ($modeWishlist) {
                if (!$souhait) {
                    $souhait = (new Souhait())->setLivre($livre)->setDateAjout(new \DateTime())->setStatut('souhaité');
                    $entityManager->persist($souhait);
                }
                $souhait->setPublique($request->request->getBoolean('publique'))
                    ->setPrixEstime($prixEstime)->setLienAchat($lienAchat);
            } else {
                $exemplaire = new Exemplaire();
                $exemplaire->setLivre($livre);
                $exemplaire->setDateCreation(new \DateTime());
                $entityManager->persist($exemplaire);

                // Enregistre la sélection avec le livre ; une lecture déjà terminée reste hors de la pile.
                $livre->setPileALire($request->request->getBoolean('pileALire') && $lecture['statut'] !== 'Terminé');

                $session = new SessionLecture();
                $session->setLivre($livre);
                $donneesLecture->appliquer($session, $lecture);
                $session->setDateCreation(new \DateTime());
                $session->setDateModification(new \DateTime());
                $entityManager->persist($session);

            }

            $entityManager->flush();
            $connexion->commit();

            return $this->json([
                'success' => true,
                'message' => $modeWishlist ? 'La wishlist a été enregistrée.' : 'Le livre a bien été ajouté à ta bibliothèque.',
                'livre' => [
                    'id' => $livre->getId(),
                    'titre' => $livre->getTitre(),
                    'sousTitre' => $livre->getSousTitre(),
                    'isbn13' => $livre->getIsbn13(),
                    'isbn10' => $livre->getIsbn10(),
                    'auteurs' => array_values($auteursNettoyes),
                    'genres' => array_map(
                        static fn (Genre $genre) => $genre->getNom(),
                        $livre->getGenres()->toArray()
                    ),
                    'statut' => $statut,
                    'imageCouverture' => $livre->getImageCouverture(),
                    'dateCreation' => $livre->getDateCreation()?->getTimestamp(),
                ],
            ], $modificationWishlist ? 200 : 201);

         } catch (\Throwable $exception) {
            // Supprime le fichier nouvellement envoyé si l’enregistrement du livre échoue.
            $couvertures->annuler($nouvelleCouverture);
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            error_log(sprintf(
                '[INCIPIT - AJOUT LIVRE] %s : %s dans %s ligne %d',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            ));

            return $this->json([
                'success' => false,
                'message' => 'Impossible d’enregistrer le livre. Vérifie les informations saisies.',
            ], 500);
        }
    }
}