<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use App\Service\OrdreAccueil;
use App\Service\ClassementAuteursAccueil;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_accueil', methods: ['GET'])]
    #[Route('/espace/accueil/edition', name: 'app_accueil_edition', methods: ['GET'])]
    // Prépare les sections de l’accueil public en tenant compte de leurs réglages de visibilité.
    public function index(Request $request, Connection $connection, \App\Service\VisibiliteAccueil $reglages, OrdreAccueil $ordre, ClassementAuteursAccueil $classement): Response
    {
        $edition = $request->attributes->get('_route') === 'app_accueil_edition';
        if ($edition) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            if ((int) $this->getUser()->getId() !== (int) $connection->fetchOne('SELECT MIN(id) FROM utilisateur')) {
                throw $this->createAccessDeniedException();
            }
        }
        // Charge les réglages qui déterminent quelles sections peuvent apparaître publiquement.
        $visibilite = $reglages->publique();
        // Les 5 livres dont la dernière lecture terminée et notée
        // a obtenu la note maximale.
        $livres = $this->chargerLivresFavoris($connection);

        // Prépare aussi les auteurs hors du top cinq pour pouvoir choisir parmi tous les ex æquo.
        $auteursAnnuels = $classement->charger();
        $auteurs = array_slice($auteursAnnuels, 0, 5);

        // Retient les cinq souhaits publics les plus récents, en excluant les achats et les souhaits privés.
        $wishlist = $connection->fetchAllAssociative(
            "SELECT l.id, l.titre, l.image_couverture,
                    GROUP_CONCAT(
                        DISTINCT a.nom_affichage
                        ORDER BY a.nom_affichage
                        SEPARATOR ', '
                    ) AS auteurs
             FROM souhait sh
             INNER JOIN livre l ON l.id = sh.livre_id
             LEFT JOIN livre_auteur la ON la.livre_id = l.id
             LEFT JOIN auteur a ON a.id = la.auteur_id
             WHERE sh.statut = :statut AND sh.publique = 1
             GROUP BY sh.id, l.id, l.titre, l.image_couverture, sh.date_ajout
             ORDER BY sh.date_ajout DESC, sh.id DESC
             ",
            ['statut' => 'souhaité']
        );

        return $this->render('accueil/index.html.twig', [
            'edition' => $edition,
            'livres' => array_slice($ordre->appliquer($livres, 'favoris'), 0, 5),
            'auteurs' => $auteurs,
            'auteursAnnuels' => $edition ? $auteursAnnuels : [],
            'wishlist' => array_slice($ordre->appliquer($wishlist, 'wishlist'), 0, 5),
            'visibilite' => $visibilite,
        ]);
    }

    #[Route('/auteurs/lectures', name: 'app_accueil_auteurs_lectures', methods: ['GET'])]
    // Regroupe sur une seule page les lectures de l’année, auteur par auteur, dans l’ordre du classement public.
    public function lecturesAuteurs(Request $request, Connection $connection, \App\Service\VisibiliteAccueil $reglages, ClassementAuteursAccueil $classement): Response
    {
        $edition = $this->modeEdition($request, $connection);
        if (!$reglages->publique()['auteurs']) throw $this->createNotFoundException();
        $auteurs = $classement->charger();
        $lectures = $connection->fetchAllAssociative(
            "SELECT la.auteur_id, l.id, l.titre, l.image_couverture, s.note, s.avis, s.date_fin
             FROM session_lecture s
             JOIN livre l ON l.id = s.livre_id
             JOIN livre_auteur la ON la.livre_id = l.id
             WHERE s.statut = :statut AND s.date_fin >= :debut AND s.date_fin < :fin
             ORDER BY s.date_fin ASC, s.id ASC",
            ['statut' => 'terminé', 'debut' => date('Y-01-01'), 'fin' => (date('Y') + 1).'-01-01']
        );
        $parAuteur = [];
        foreach ($lectures as $lecture) $parAuteur[$lecture['auteur_id']][] = $lecture;
        return $this->render('accueil/auteurs_lectures.html.twig', [
            'auteurs' => $auteurs, 'lecturesParAuteur' => $parAuteur, 'annee' => date('Y'), 'edition' => $edition,
        ]);
    }

    #[Route('/auteurs/{id}/lectures', name: 'app_accueil_auteur_lectures', requirements: ['id' => '\d+'], methods: ['GET'])]
    // Les anciens liens rejoignent désormais la page commune, directement à la section de l’auteur.
    public function lecturesAuteur(int $id, \App\Service\VisibiliteAccueil $reglages): Response
    {
        if (!$reglages->publique()['auteurs']) throw $this->createNotFoundException();
        return $this->redirectToRoute('app_accueil_auteurs_lectures', ['_fragment' => 'auteur-'.$id]);
    }

    #[Route('/espace/accueil/ordre', name: 'app_accueil_ordre', methods: ['POST'])]
    // Sauvegarde les deux sélections publiques sans modifier les notes, les souhaits ni la visibilité.
    public function enregistrerOrdre(Request $request, Connection $connection, ClassementAuteursAccueil $classement): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $id = (int) $this->getUser()->getId();
        if ($id !== (int) $connection->fetchOne('SELECT MIN(id) FROM utilisateur')) throw $this->createAccessDeniedException();
        if (!$this->isCsrfTokenValid('ordre_accueil', $request->request->getString('_token'))) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualise la page.'], 403);
        }
        $ordre = json_decode($request->request->getString('ordre'), true);
        if (!is_array($ordre) || array_diff(array_keys($ordre), ['favoris', 'wishlist', 'auteurs'])) {
            return $this->json(['success' => false, 'message' => 'Ordre invalide.'], 422);
        }
        $auteursAnnuels = $classement->charger();
        $autorises = [
            'auteurs' => array_column($auteursAnnuels, 'id'),
            'favoris' => array_column($this->chargerLivresFavoris($connection), 'id'),
            'wishlist' => $connection->fetchFirstColumn("SELECT l.id FROM souhait sh JOIN livre l ON l.id = sh.livre_id WHERE sh.statut = 'souhaité' AND sh.publique = 1 ORDER BY sh.date_ajout DESC, sh.id DESC"),
        ];
        // Une page n’envoie que sa section : préserve les autres positions déjà enregistrées.
        $jsonExistant = $connection->fetchOne('SELECT ordre_accueil FROM parametre_confidentialite WHERE utilisateur_id = ?', [$id]);
        $existant = is_string($jsonExistant) ? (json_decode($jsonExistant, true) ?: []) : [];
        foreach (array_keys($ordre) as $section) {
            $ids = $ordre[$section] ?? [];
            if (!is_array($ids) || count($ids) > 10000 || count($ids) !== count(array_unique($ids, SORT_REGULAR))) {
                return $this->json(['success' => false, 'message' => 'Sélection invalide.'], 422);
            }
            foreach ($ids as $livreId) {
                if (!is_int($livreId) || !in_array($livreId, array_map('intval', $autorises[$section]), true)) {
                    return $this->json(['success' => false, 'message' => 'Les livres visibles ont changé. Recharge le mode édition.'], 409);
                }
            }
            // Refuse les doublons et les choix qui feraient passer un auteur moins lu devant un autre.
            if ($section === 'auteurs') {
                $comptes = array_column($auteursAnnuels, 'nombre_lectures', 'id');
                $attendus = array_map('intval', array_column(array_slice($auteursAnnuels, 0, 5), 'nombre_lectures'));
                $choisis = array_map(static fn (int $auteurId): int => (int) $comptes[$auteurId], $ids);
                if ($choisis !== $attendus) {
                    return $this->json(['success' => false, 'message' => 'Le classement a changé. Recharge le mode édition pour choisir parmi les auteurs à égalité.'], 409);
                }
            }
            // Depuis l’accueil, les cinq cartes visibles passent devant sans effacer l’ordre du reste de la liste.
            $reste = array_values(array_diff($existant[$section] ?? [], $ids));
            $existant[$section] = [...$ids, ...$reste];
        }
        // Crée les réglages si nécessaire et préserve les interrupteurs de visibilité déjà enregistrés.
        $connection->executeStatement(
            'INSERT INTO parametre_confidentialite (utilisateur_id, afficher_wishlist, afficher_auteurs_favoris, afficher_livres_favoris, date_modification, ordre_accueil) VALUES (?, 1, 1, 1, NOW(), ?) ON DUPLICATE KEY UPDATE ordre_accueil = VALUES(ordre_accueil), date_modification = NOW()',
            [$id, json_encode($existant, JSON_THROW_ON_ERROR)]
        );
        return $this->json(['success' => true]);
    }

    #[Route('/mes-livres-mieux-notes', name: 'app_livres_mieux_notes', methods: ['GET'])]
    // Affiche les livres favoris lorsque leur section publique est visible.
    public function livresMieuxNotes(Request $request, Connection $connection, \App\Service\VisibiliteAccueil $reglages, OrdreAccueil $ordre): Response
    {
        $edition = $this->modeEdition($request, $connection);
        if (!$reglages->publique()['favoris']) throw $this->createNotFoundException();
        $livres = $this->chargerLivresFavoris($connection);

        return $this->render('accueil/livres-mieux-notes.html.twig', [
            'livres' => $ordre->appliquer($livres, 'favoris'),
            'edition' => $edition,
        ]);
    }

    #[Route('/ma-wishlist', name: 'app_wishlist', methods: ['GET'])]
    // Affiche la wishlist publique en respectant la visibilité de la section et de chaque souhait.
    public function wishlist(Request $request, Connection $connection, \App\Repository\SouhaitRepository $repository, \App\Service\VisibiliteAccueil $reglages, OrdreAccueil $ordre): Response
    {
        $edition = $this->modeEdition($request, $connection);
        if (!$reglages->publique()['wishlist']) throw $this->createNotFoundException();
        return $this->render('wishlist/public.html.twig', [
            'souhaits' => $ordre->appliquer($repository->findBy(['statut' => 'souhaité', 'publique' => true], ['dateAjout' => 'DESC', 'id' => 'DESC']), 'wishlist'),
            'edition' => $edition,
        ]);
    }

    // L’édition des pages publiques complètes reste réservée au propriétaire de l’accueil.
    private function modeEdition(Request $request, Connection $connection): bool
    {
        if (!$request->query->getBoolean('edition')) return false;
        $this->denyAccessUnlessGranted('ROLE_USER');
        if ((int) $this->getUser()->getId() !== (int) $connection->fetchOne('SELECT MIN(id) FROM utilisateur')) {
            throw $this->createAccessDeniedException();
        }
        return true;
    }

    // Partage la sélection des livres notés cinq étoiles entre l’accueil et la liste complète.
    private function chargerLivresFavoris(Connection $connection, ?int $limite = null): array
    {
        // La sous-requête choisit la dernière lecture terminée et notée avant de retenir la note maximale.
        $sql = "SELECT l.id, l.titre, l.image_couverture, s.note,
                GROUP_CONCAT(
                    DISTINCT a.nom_affichage
                    ORDER BY a.nom_affichage
                    SEPARATOR ', '
                ) AS auteurs
         FROM livre l
         INNER JOIN session_lecture s ON s.livre_id = l.id
         LEFT JOIN livre_auteur la ON la.livre_id = l.id
         LEFT JOIN auteur a ON a.id = la.auteur_id
         WHERE s.statut = :statut
           AND s.note = 5
           AND s.id = (
               SELECT s2.id
               FROM session_lecture s2
               WHERE s2.livre_id = l.id
                 AND s2.statut = :statut
                 AND s2.note IS NOT NULL
               ORDER BY s2.date_fin DESC, s2.id DESC
               LIMIT 1
           )
         GROUP BY l.id, l.titre, l.image_couverture, s.note, s.date_fin
         ORDER BY s.date_fin DESC, l.id DESC";

        if ($limite !== null) {
            $sql .= ' LIMIT '.$limite;
        }

        return $connection->fetchAllAssociative($sql, ['statut' => 'terminé']);
    }
}
