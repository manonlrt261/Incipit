<?php

namespace App\Controller;

use App\Service\VisibiliteAccueil;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfilController extends AbstractController
{
    #[Route('/espace/mon-profil', name: 'app_espace_profil', methods: ['GET'])]
    // Affiche le compte connecté et les réglages de visibilité de la bibliothèque partagée.
    public function index(VisibiliteAccueil $visibilite): Response
    {
        return $this->render('profil/index.html.twig', ['visibilite' => $visibilite->publique()]);
    }

    #[Route('/espace/mon-profil/mot-de-passe', name: 'app_profil_mot_de_passe', methods: ['POST'])]
    // Vérifie le mot de passe actuel et la confirmation avant d’enregistrer le nouveau mot de passe haché.
    public function motDePasse(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        if (!$this->jetonValide($request, 'profil_mot_de_passe')) {
            return $this->sessionExpiree();
        }
        $champs = $request->request->all();
        $actuel = $champs['actuel'] ?? '';
        $nouveau = $champs['nouveau'] ?? '';
        $confirmation = $champs['confirmation'] ?? '';
        $user = $this->getUser();

        if (!is_string($actuel) || $actuel === '' || strlen($actuel) > 4096 || !$hasher->isPasswordValid($user, $actuel)) {
            return $this->json(['success' => false, 'message' => 'Le mot de passe actuel est incorrect.'], 422);
        }

        if (!is_string($nouveau) || mb_strlen($nouveau) < 12 || strlen($nouveau) > 4096) {
            return $this->json(['success' => false, 'message' => 'Choisissez un mot de passe d’au moins 12 caractères (4 096 octets maximum).'], 422);
        }

        if (!is_string($confirmation) || !hash_equals($nouveau, $confirmation)) {
            return $this->json(['success' => false, 'message' => 'La confirmation ne correspond pas au nouveau mot de passe.'], 422);
        }

        if ($hasher->isPasswordValid($user, $nouveau)) {
            return $this->json(['success' => false, 'message' => 'Choisissez un mot de passe différent de l’actuel.'], 422);
        }
        $user->setPassword($hasher->hashPassword($user, $nouveau))->setDateModification(new \DateTime());
        $em->flush();

        return $this->json(['success' => true, 'message' => 'Votre mot de passe a été modifié.']);
    }

    #[Route('/espace/mon-profil/visibilite', name: 'app_profil_visibilite', methods: ['POST'])]
    // Enregistre la visibilité commune aux administrateurs, sans modifier les autres sections.
    public function visibilite(Request $request, Connection $connexion, \App\Service\BibliothequePartagee $bibliotheque): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->jetonValide($request, 'profil_visibilite')) {
            return $this->sessionExpiree();
        }
        // Limite les réglages aux sections autorisées avant de choisir la colonne à mettre à jour.
        $colonnes = ['wishlist' => 'afficher_wishlist', 'auteurs' => 'afficher_auteurs_favoris', 'favoris' => 'afficher_livres_favoris'];
        $champs = $request->request->all();
        $section = $champs['section'] ?? '';
        $visible = $champs['visible'] ?? '';

        if (!is_string($section) || !isset($colonnes[$section]) || !in_array($visible, ['0', '1'], true)) {
            return $this->json(['success' => false, 'message' => 'Réglage de visibilité invalide.'], 422);
        }
        // Utilise les valeurs par défaut à la création ; lors d’une mise à jour, seule la section demandée change.
        $valeurs = ['wishlist' => 1, 'auteurs' => 1, 'favoris' => 1];
        $valeurs[$section] = (int) $visible;
        $colonne = $colonnes[$section];
        $connexion->executeStatement(
            "INSERT INTO parametre_confidentialite (utilisateur_id, afficher_wishlist, afficher_auteurs_favoris, afficher_livres_favoris, date_modification) VALUES (:id, :wishlist, :auteurs, :favoris, :date) ON DUPLICATE KEY UPDATE $colonne = :visible, date_modification = :modification",
            [...$valeurs, 'id' => $bibliotheque->proprietaireId(), 'date' => date('Y-m-d H:i:s'), 'visible' => (int) $visible, 'modification' => date('Y-m-d H:i:s')]
        );

        return $this->json(['success' => true, 'visible' => $visible === '1']);
    }

    // Vérifie que le jeton reçu correspond à l’action de modification demandée.
    private function jetonValide(Request $request, string $intention): bool
    {
        $token = $request->request->all()['_token'] ?? '';

        return is_string($token) && $this->isCsrfTokenValid($intention, $token);
    }
    // Renvoie le message commun invitant à actualiser la page lorsque le jeton est invalide.
    private function sessionExpiree(): JsonResponse
    {
        return $this->json(['success' => false, 'message' => 'Session expirée. Actualisez la page.'], 403);
    }
}
