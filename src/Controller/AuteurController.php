<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use App\Entity\Auteur;
use App\Entity\Livre;
use App\Entity\Souhait;
use App\Service\NomsAuteur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AuteurController extends AbstractController
{
    #[Route('/espace/mes-auteurs', name: 'app_auteurs', methods: ['GET'])]
    // Affiche les mêmes fiches d’auteurs que celles utilisées par les suggestions des formulaires de livres.
    public function index(Connection $connexion): Response
    {
        $auteurs = $connexion->fetchAllAssociative("SELECT a.id, a.prenom, a.nom AS nom_famille, a.nom_affichage,
            COALESCE(NULLIF(a.nom_affichage, ''), CONCAT_WS(' ', a.prenom, a.nom)) AS nom,
            COUNT(DISTINCT la.livre_id) AS nombre, GROUP_CONCAT(DISTINCT l.saga SEPARATOR ' | ') AS sagas
            FROM auteur a INNER JOIN livre_auteur la ON la.auteur_id = a.id
            INNER JOIN livre l ON l.id = la.livre_id
            GROUP BY a.id, a.nom_affichage, a.prenom, a.nom
            ORDER BY nom ASC, a.id ASC");
        $auteurs = array_map(static fn (array $auteur) => NomsAuteur::presenter([...$auteur, 'nom' => $auteur['nom_famille']]), $auteurs);
        return $this->render('auteur/index.html.twig', ['auteurs' => $auteurs]);
    }

    #[Route('/espace/mes-auteurs/{id}', name: 'app_auteur_fiche', requirements: ['id' => '\d+'], methods: ['GET'])]
    // Répartit les livres liés à cette fiche sans dupliquer les éditions ou les exemplaires dans un même onglet.
    public function fiche(int $id, Connection $connexion, Request $request): Response
    {
        $auteur = $connexion->fetchAssociative("SELECT id, prenom, nom, nom_affichage FROM auteur WHERE id = ?", [$id]);
        if (!$auteur) throw $this->createNotFoundException('Auteur introuvable.');
        $auteur = NomsAuteur::presenter($auteur);
        $livres = $connexion->fetchAllAssociative("SELECT l.id, l.titre, l.image_couverture, l.saga, l.tome,
            (SELECT sl.note FROM session_lecture sl WHERE sl.livre_id = l.id AND sl.note IS NOT NULL ORDER BY sl.date_modification DESC, sl.id DESC LIMIT 1) AS note,
            EXISTS(SELECT 1 FROM exemplaire e WHERE e.livre_id = l.id AND e.date_retrait IS NULL) AS possede,
            EXISTS(SELECT 1 FROM souhait s WHERE s.livre_id = l.id AND s.statut = 'souhaité') AS souhaite,
            EXISTS(SELECT 1 FROM pret p JOIN exemplaire e ON e.id = p.exemplaire_id WHERE e.livre_id = l.id AND p.statut = 'En cours' AND p.date_retour_effective IS NULL) AS prete,
            EXISTS(SELECT 1 FROM exemplaire e WHERE e.livre_id = l.id AND e.date_retrait IS NOT NULL) AS retire,
            COALESCE((SELECT sl.statut FROM session_lecture sl WHERE sl.livre_id = l.id ORDER BY sl.date_modification DESC, sl.id DESC LIMIT 1), 'Pas commencé') AS statut
            FROM livre l JOIN livre_auteur la ON la.livre_id = l.id
            WHERE la.auteur_id = ? ORDER BY l.titre ASC, l.id ASC", [$id]);
        // Un livre prêté reste également possédé ; un titre retiré n’a plus aucun exemplaire actif.
        $onglets = [
            'possedes' => ['titre' => 'Livres possédés', 'livres' => []],
            'wishlist' => ['titre' => 'Wishlist', 'livres' => []],
            'pretes' => ['titre' => 'Livres prêtés', 'livres' => []],
            'retires' => ['titre' => 'Retirés de ma collection', 'livres' => []],
        ];
        foreach ($livres as $livre) {
            if ($livre['possede']) $onglets['possedes']['livres'][] = $livre;
            if ($livre['souhaite']) $onglets['wishlist']['livres'][] = $livre;
            if ($livre['prete']) $onglets['pretes']['livres'][] = $livre;
            if ($livre['retire'] && !$livre['possede']) $onglets['retires']['livres'][] = $livre;
        }
        // Les sagas réutilisent les champs enregistrés sur les livres et leurs liens d’auteur existants.
        $sagas = [];
        $livresSagas = [];
        foreach ($livres as $livre) {
            $nomSaga = preg_replace('/\s+/u', ' ', trim($livre['saga'] ?? ''));
            if ($nomSaga === '') continue;
            $cleSaga = mb_strtolower($nomSaga);
            $sagas[$cleSaga] ??= $nomSaga;
            $livre['cle_saga'] = $cleSaga;
            $livresSagas[] = $livre;
        }
        natcasesort($sagas);
        // Classe numériquement les tomes : 2 précède 10, et les tomes non renseignés viennent en dernier.
        usort($livresSagas, static fn (array $a, array $b): int => strnatcasecmp($a['cle_saga'], $b['cle_saga'])
            ?: ($a['tome'] === null ? PHP_INT_MAX : (int) $a['tome']) <=> ($b['tome'] === null ? PHP_INT_MAX : (int) $b['tome'])
            ?: strnatcasecmp($a['titre'], $b['titre']) ?: $a['id'] <=> $b['id']);
        $onglets['sagas'] = ['titre' => 'Saga', 'livres' => $livresSagas];
        return $this->render('auteur/fiche.html.twig', ['auteur' => $auteur, 'onglets' => $onglets, 'sagas' => $sagas, 'sagaSelectionnee' => $request->query->getString('saga')]);
    }
    #[Route('/espace/mes-auteurs/{id}/saga/modifier', name: 'app_auteur_saga_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    // Renomme tous les tomes de cette saga et rattache seulement un livre déjà lié à cet auteur.
    public function modifierSaga(int $id, Request $request, Connection $connexion, EntityManagerInterface $em): Response
    {
        $auteur = $connexion->fetchAssociative('SELECT id, prenom, nom, nom_affichage FROM auteur WHERE id = ?', [$id]);
        if (!$auteur) throw $this->createNotFoundException('Auteur introuvable.');
        $livres = $em->createQuery('SELECT l FROM App\Entity\Livre l JOIN l.auteurs a WHERE a.id = :id')->setParameter('id', $id)->getResult();
        $normaliser = static fn (?string $nom): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($nom ?? '')));
        $cle = $request->query->getString('saga');
        $tomes = array_values(array_filter($livres, static fn (Livre $livre): bool => $normaliser($livre->getSaga()) === $cle && $cle !== ''));
        if (!$tomes) throw $this->createNotFoundException('Saga introuvable pour cet auteur.');
        $nom = $tomes[0]->getSaga();
        $erreur = null;
        $livreId = '';
        $tome = '';
        if ($request->isMethod('POST')) {
            $nom = preg_replace('/\s+/u', ' ', trim($request->request->getString('nom')));
            $livreId = $request->request->getString('livre');
            $tome = $request->request->getString('tome');
            $ajout = null;
            foreach ($livres as $livre) {
                if ((string) $livre->getId() === $livreId) $ajout = $livre;
            }
            if (!$this->isCsrfTokenValid('modifier_saga_'.$id.'_'.$cle, $request->request->getString('_token'))) {
                $erreur = 'Session expirée. Actualise la page.';
            } elseif ($nom === '' || mb_strlen($nom) > 255) {
                $erreur = 'Renseignez un nom de saga de 255 caractères maximum.';
            } elseif ($livreId !== '' && !$ajout) {
                $erreur = 'Choisissez un livre de cet auteur.';
            } elseif ($tome !== '' && (!ctype_digit($tome) || (int) $tome < 1 || (int) $tome > 100000)) {
                $erreur = 'Le numéro du tome doit être compris entre 1 et 100000.';
            } else {
                foreach ($livres as $livre) {
                    if ($normaliser($livre->getSaga()) === $normaliser($nom) && $normaliser($nom) !== $cle) {
                        $erreur = 'Une saga porte déjà ce nom pour cet auteur.';
                        break;
                    }
                }
            }
            if ($erreur === null) {
                foreach ($tomes as $livre) $livre->setSaga($nom);
                if ($ajout) {
                    $ajout->setSaga($nom);
                    if ($tome !== '') $ajout->setTome((int) $tome);
                }
                $em->flush();
                return $this->redirectToRoute('app_auteur_fiche', ['id' => $id, 'saga' => $normaliser($nom), '_fragment' => 'auteur-panel-sagas']);
            }
        }
        return $this->render('auteur/saga_modifier.html.twig', [
            'auteur' => NomsAuteur::presenter($auteur), 'cleSaga' => $cle, 'nomSaga' => $nom,
            'livres' => $livres, 'livreId' => $livreId, 'tome' => $tome, 'erreur' => $erreur,
            'sagaEnregistree' => $tomes[0]->getSaga(),
            'genres' => $connexion->fetchAllAssociative('SELECT id, nom FROM genre ORDER BY nom ASC'),
        ]);
    }

    #[Route('/espace/mes-auteurs/{id}/modifier', name: 'app_auteur_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    // Renomme la fiche existante : tous ses livres et les suggestions conservent le même identifiant d’auteur.
    public function modifier(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $auteur = $em->find(Auteur::class, $id);
        if (!$auteur) throw $this->createNotFoundException('Auteur introuvable.');
        $valeurs = NomsAuteur::presenter(['id' => $id, 'prenom' => $auteur->getPrenom(), 'nom' => $auteur->getNom(), 'nom_affichage' => $auteur->getNomAffichage()]);
        $erreur = null;
        if ($request->isMethod('POST')) {
            $valeurs['prenom'] = preg_replace('/\s+/u', ' ', trim($request->request->getString('prenom')));
            $valeurs['nom'] = preg_replace('/\s+/u', ' ', trim($request->request->getString('nom')));
            $affichage = trim($valeurs['prenom'].' '.$valeurs['nom']);
            if (!$this->isCsrfTokenValid('modifier_auteur_'.$id, $request->request->getString('_token'))) {
                $erreur = 'Session expirée. Actualise la page.';
            } elseif ($valeurs['nom'] === '' || mb_strlen($affichage) > 255) {
                $erreur = 'Renseignez un nom ; le prénom et le nom doivent tenir sur 255 caractères.';
            } else {
                $existant = $em->getRepository(Auteur::class)->findOneBy(['nomAffichage' => $affichage]);
                if ($existant && $existant->getId() !== $id) $erreur = 'Une fiche existe déjà pour cet auteur.';
            }
            if ($erreur === null) {
                $auteur->setPrenom($valeurs['prenom'] ?: null)->setNom($valeurs['nom'])->setNomAffichage($affichage);
                $em->flush();
                return $this->redirectToRoute('app_auteur_fiche', ['id' => $id]);
            }
        }
        return $this->render('auteur/modifier.html.twig', ['auteur' => $valeurs, 'erreur' => $erreur]);
    }

    #[Route('/espace/mes-auteurs/{id}/livre/{livreId}', name: 'app_auteur_livre_details', requirements: ['id' => '\d+', 'livreId' => '\d+'], methods: ['GET'])]
    // Ouvre la fiche en consultation pour toutes les catégories, y compris les livres retirés et les souhaits privés.
    public function detailsLivre(int $id, int $livreId, Connection $connexion, EntityManagerInterface $em): Response
    {
        if (!$connexion->fetchOne('SELECT 1 FROM livre_auteur WHERE auteur_id = ? AND livre_id = ?', [$id, $livreId])) {
            throw $this->createNotFoundException('Livre introuvable pour cet auteur.');
        }
        $livre = $em->find(Livre::class, $livreId);
        $lectures = $connexion->fetchAllAssociative('SELECT statut, note, avis, date_debut, date_fin FROM session_lecture WHERE livre_id = ? ORDER BY date_creation DESC, id DESC', [$livreId]);
        $souhait = $em->getRepository(Souhait::class)->findOneBy(['livre' => $livre, 'statut' => 'souhaité']);
        return $this->render('auteur/_details_livre.html.twig', ['livre' => $livre, 'lectures' => $lectures, 'souhait' => $souhait]);
    }

}
