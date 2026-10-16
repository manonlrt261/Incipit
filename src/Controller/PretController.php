<?php

namespace App\Controller;

use App\Entity\Pret;
use App\Entity\Livre;
use App\Entity\Auteur;
use App\Entity\Contact;
use App\Entity\Exemplaire;
use App\Repository\PretRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/mes-prets', name: 'app_prets_')]
class PretController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    // Prépare les prêts affichés et les livres possédés proposés dans le formulaire d’ajout.
    public function index(PretRepository $pretRepository, EntityManagerInterface $entityManager): Response
    {
        $prets = $pretRepository->findBy(
            [],
            ['dateCreation' => 'DESC']
        );

        $listePrets = [];

        // Prépare le titre, les auteurs et le nom de l’emprunteur pour chaque prêt.
        foreach ($prets as $pret) {
            $livre = $pret->getExemplaire()?->getLivre();
            $contact = $pret->getContact();

            $prenom = trim($contact?->getPrenom() ?? '');
            $nom = trim($contact?->getNom() ?? '');

            $emprunteur = trim($prenom . ' ' . $nom);

            $listePrets[] = [
                'id' => $pret->getId(),
                'titre' => $livre?->getTitre() ?? 'Titre inconnu',
                'auteur' => $this->obtenirAuteurs($livre),
                'emprunteur' => $emprunteur !== ''
                    ? $emprunteur
                    : 'Emprunteur inconnu',
            ];
        }

        return $this->render('pret/index.html.twig', [
            'prets' => $listePrets,
            'livresPossedes' => $entityManager->createQueryBuilder()
                ->select('DISTINCT l')->from(Livre::class, 'l')
                ->where('EXISTS (SELECT e.id FROM App\\Entity\\Exemplaire e WHERE e.livre = l AND e.dateRetrait IS NULL)')
                ->orderBy('l.titre', 'ASC')->getQuery()->getResult(),
        ]);
    }

    #[Route('/ajouter', name: 'ajouter', methods: ['POST'])]
    // Enregistre un prêt à partir d’un livre possédé ou d’une saisie libre, avec un auteur facultatif.
    public function ajouter(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $jeton = $request->request->get('_token');
        if (!is_string($jeton) || !$this->isCsrfTokenValid('ajouter_pret', $jeton)) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualisez la page.'], 403);
        }

        // Contrôle les longueurs et les champs obligatoires avant de créer les données du prêt.
        $champs = [];
        foreach (['titre', 'auteur', 'emprunteur'] as $champ) {
            $valeur = $request->request->all()[$champ] ?? '';
            if (!is_string($valeur) || mb_strlen(trim($valeur)) > 255) {
                return $this->json(['success' => false, 'message' => 'Chaque champ doit contenir au maximum 255 caractères.'], 422);
            }
            $champs[$champ] = trim($valeur);
        }
        if ($champs['titre'] === '' || $champs['emprunteur'] === '') {
            return $this->json(['success' => false, 'message' => 'Le titre et la personne à qui le livre est prêté sont obligatoires.'], 422);
        }

        // Une seule transaction pour éviter les fiches partielles en cas d'échec.
        $connexion = $entityManager->getConnection();
        $connexion->beginTransaction();
        try {
            $livre = null;
            $idLivre = $request->request->get('livre_id');
            // Vérifie qu’un livre sélectionné dans les suggestions est toujours possédé.
            if ($idLivre !== null && $idLivre !== '') {
                if (!is_string($idLivre) || !ctype_digit($idLivre)) {
                    throw new \InvalidArgumentException('Livre invalide.');
                }
                $livre = $entityManager->getRepository(Livre::class)->find($idLivre);
                $possede = $livre && $entityManager->getRepository(Exemplaire::class)->findOneBy(['livre' => $livre, 'dateRetrait' => null]);
                if (!$possede) {
                    $connexion->rollBack();
                    return $this->json(['success' => false, 'message' => 'Ce livre ne figure plus dans votre bibliothèque.'], 422);
                }
            }
            // Réutilise une fiche correspondant au titre et à l’auteur avant d’en créer une nouvelle.
            $candidats = $livre ? [] : $entityManager->getRepository(Livre::class)->findBy(['titre' => $champs['titre']]);
            foreach ($candidats as $candidat) {
                if ($champs['auteur'] === '' || mb_strtolower($this->obtenirAuteurs($candidat)) === mb_strtolower($champs['auteur'])) {
                    $livre = $candidat;
                    break;
                }
            }
            if ($livre === null) {
                $livre = (new Livre())->setTitre($champs['titre']);
                if ($champs['auteur'] !== '') {
                    $auteur = $entityManager->getRepository(Auteur::class)->findOneBy(['nomAffichage' => $champs['auteur']]);
                    if ($auteur === null) {
                        $auteur = (new Auteur())->setNom($champs['auteur'])->setNomAffichage($champs['auteur']);
                        $entityManager->persist($auteur);
                    }
                    $livre->addAuteur($auteur);
                }
                $entityManager->persist($livre);
            }

            // Recherche un exemplaire actif qui n’est pas déjà prêté, ou en crée un si nécessaire.
            $exemplaire = null;
            if ($livre->getId() !== null) {
                $exemplaire = $entityManager->createQueryBuilder()
                    ->select('e')->from(Exemplaire::class, 'e')
                    ->where('e.livre = :livre')->andWhere('e.dateRetrait IS NULL')
                    ->andWhere('NOT EXISTS (SELECT p.id FROM App\\Entity\\Pret p WHERE p.exemplaire = e AND p.dateRetourEffective IS NULL)')
                    ->setParameter('livre', $livre)->setMaxResults(1)
                    ->getQuery()->getOneOrNullResult();
            }
            if ($exemplaire === null) {
                $exemplaire = (new Exemplaire())->setLivre($livre)->setDateCreation(new \DateTime());
                $entityManager->persist($exemplaire);
            }

            // Réutilise l’emprunteur connu pour éviter de créer plusieurs contacts au même nom.
            $contact = null;
            foreach ($entityManager->getRepository(Contact::class)->findAll() as $candidat) {
                if (mb_strtolower(trim($candidat->getPrenom() . ' ' . $candidat->getNom())) === mb_strtolower($champs['emprunteur'])) {
                    $contact = $candidat;
                    break;
                }
            }
            if ($contact === null) {
                $contact = (new Contact())->setPrenom($champs['emprunteur'])->setDateCreation(new \DateTime());
                $entityManager->persist($contact);
            }
            $pret = (new Pret())->setExemplaire($exemplaire)->setContact($contact)
                ->setDatePret(new \DateTime())->setDateCreation(new \DateTime())->setStatut('En cours');
            $entityManager->persist($pret);
            $entityManager->flush();
            $connexion->commit();

            return $this->json(['success' => true], 201);
        } catch (\Throwable $erreur) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }
            return $this->json(['success' => false, 'message' => 'Impossible d’enregistrer ce prêt. Réessayez.'], 500);
        }
    }

    #[Route(
        '/{id}/supprimer',
        name: 'supprimer',
        requirements: ['id' => '\d+'],
        methods: ['POST']
    )]
    // Supprime le prêt demandé après vérification du jeton associé à ce prêt.
    public function supprimer(
        Pret $pret,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $jeton = $request->request->get('_token');

        if (
            !is_string($jeton) ||
            !$this->isCsrfTokenValid(
                'supprimer_pret_' . $pret->getId(),
                $jeton
            )
        ) {
            return $this->json([
                'success' => false,
                'message' => 'Jeton de sécurité invalide.',
            ], 403);
        }

        try {
            $entityManager->remove($pret);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Le prêt a été supprimé.',
            ]);
        } catch (\Throwable $erreur) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer ce prêt.',
            ], 500);
        }
    }

    // Construit le nom des auteurs quel que soit le format disponible, avec un texte de remplacement si nécessaire.
    private function obtenirAuteurs(?object $livre): string
    {
        if ($livre === null || !method_exists($livre, 'getAuteurs')) {
            return 'Auteur non renseigné';
        }

        $auteurs = $livre->getAuteurs();

        if (is_string($auteurs)) {
            return trim($auteurs) ?: 'Auteur non renseigné';
        }

        if (!is_iterable($auteurs)) {
            return 'Auteur non renseigné';
        }

        $noms = [];

        foreach ($auteurs as $auteur) {
            if (is_string($auteur)) {
                $nom = $auteur;
            } elseif (is_object($auteur)) {
                $prenom = method_exists($auteur, 'getPrenom')
                    ? $auteur->getPrenom()
                    : '';

                $nomFamille = method_exists($auteur, 'getNom')
                    ? $auteur->getNom()
                    : '';

                $nom = trim(
                    (string) $prenom . ' ' . (string) $nomFamille
                );

                if (method_exists($auteur, 'getNomAffichage')) {
                    $nom = trim((string) $auteur->getNomAffichage()) ?: $nom;
                }

                if ($nom === '' && method_exists($auteur, '__toString')) {
                    $nom = (string) $auteur;
                }
            } else {
                continue;
            }

            if (trim($nom) !== '') {
                $noms[] = trim($nom);
            }
        }

        return $noms
            ? implode(', ', $noms)
            : 'Auteur non renseigné';
    }
}
