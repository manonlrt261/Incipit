<?php
namespace App\Controller;

use App\Entity\{Souhait, Genre, Exemplaire, SessionLecture};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class WishlistController extends AbstractController
{
    #[Route('/espace/ma-wishlist', name: 'app_espace_wishlist', methods: ['GET'])]
    // Affiche les livres encore souhaités et les genres disponibles pour les filtrer.
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('wishlist/wishlist.html.twig', [
            'souhaits' => $em->getRepository(Souhait::class)->findBy(['statut' => 'souhaité'], ['dateAjout' => 'DESC', 'id' => 'DESC']),
            'genres' => $em->getRepository(Genre::class)->findBy([], ['nom' => 'ASC']),
            'wishlist_mode' => true,
        ]);
    }

    #[Route('/wishlist/{id}/details', name: 'app_wishlist_details', requirements: ['id' => '\d+'], methods: ['GET'])]
    // Renvoie les informations du souhait pour préremplir le formulaire de modification.
    public function details(Souhait $souhait): JsonResponse
    {
        if ($souhait->getStatut() !== 'souhaité') {
            return $this->json(['success' => false, 'message' => 'Souhait introuvable.'], 404);
        }
        $l = $souhait->getLivre();
        return $this->json(['success' => true, 'publique' => $souhait->isPublique(), 'livre' => [
            'titre' => $l->getTitre(), 'sous_titre' => $l->getSousTitre(), 'saga' => $l->getSaga(),
            'tome' => $l->getTome(), 'isbn13' => $l->getIsbn13(), 'isbn10' => $l->getIsbn10(),
            'edition' => $l->getEdition(), 'langue' => $l->getLangue(),
            'date_publication' => $l->getDatePublication()?->format('Y-m-d'),
            'nombre_pages' => $l->getNombrePages(), 'resume' => $l->getResume(), 'image_couverture' => $l->getImageCouverture(),
            'auteurs_liste' => array_map(fn($a) => $a->getNomAffichage() ?: trim($a->getPrenom().' '.$a->getNom()), $l->getAuteurs()->toArray()),
            'genres_ids' => array_map(fn($g) => $g->getId(), $l->getGenres()->toArray()),
        ]]);
    }

    #[Route('/wishlist/{id}/{action}', name: 'app_wishlist_action', requirements: ['id' => '\d+', 'action' => 'acheter|supprimer'], methods: ['POST'])]
    // Supprime un souhait ou le transforme en achat, avec un exemplaire et une lecture au statut « Pas commencé ».
    public function action(int $id, string $action, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $jeton = $request->request->get('_token');
        if (!is_string($jeton) || !$this->isCsrfTokenValid('wishlist_'.$id, $jeton)) {
            return $this->json(['success' => false, 'message' => 'Session expirée. Actualisez la page.'], 403);
        }
        $db = $em->getConnection();
        $db->beginTransaction();
        try {
            // Verrouiller le souhait : deux clics ne doivent pas créer deux achats.
            $souhait = $em->find(Souhait::class, $id, LockMode::PESSIMISTIC_WRITE);
            if (!$souhait || $souhait->getStatut() !== 'souhaité') {
                $db->rollBack();
                return $this->json(['success' => false, 'message' => 'Ce livre ne figure plus dans la wishlist.'], 409);
            }
            // Distingue la suppression du souhait de son achat, qui le transfère vers les livres possédés.
            if ($action === 'supprimer') {
                $em->remove($souhait);
            } else {
                $livre = $souhait->getLivre();
                $em->persist((new Exemplaire())->setLivre($livre)->setDateCreation(new \DateTime())->setDateAcquisition(new \DateTime()));
                $em->persist((new SessionLecture())->setLivre($livre)->setStatut('Pas commencé')
                    ->setDateCreation(new \DateTime())->setDateModification(new \DateTime()));
                $souhait->setStatut('acquis')->setDateAcquisition(new \DateTime())->setPublique(false);
            }
            $em->flush();
            $db->commit();
            return $this->json(['success' => true]);
        } catch (\Throwable $exception) {
            error_log('[INCIPIT - WISHLIST] '.$exception->getMessage());
            if ($db->isTransactionActive()) $db->rollBack();
            return $this->json(['success' => false, 'message' => 'Impossible d’enregistrer cette action. Réessayez.'], 500);
        }
    }
}
