<?php
namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\VisibiliteAccueil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfilController extends AbstractController
{
    #[Route('/espace/mon-profil', name: 'app_espace_profil', methods: ['GET'])]
    // Affiche le compte et les réglages de visibilité de l’utilisateur connecté.
    public function index(VisibiliteAccueil $visibilite): Response
    {
        return $this->render('profil/index.html.twig', ['visibilite' => $visibilite->pourUtilisateur($this->getUser()->getId())]);
    }

    #[Route('/espace/mon-profil/email', name: 'app_profil_email', methods: ['POST'])]
    // Modifie l’adresse e-mail après validation du mot de passe actuel et de son unicité.
    public function email(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        if (!$this->jetonValide($request, 'profil_email')) return $this->sessionExpiree();
        $champs = $request->request->all();
        $email = $champs['email'] ?? '';
        $motDePasse = $champs['actuel'] ?? '';
        if (!is_string($email) || mb_strlen(trim($email)) > 180 || !filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
            return $this->json(['success' => false, 'message' => 'Saisissez une adresse e-mail valide (180 caractères maximum).'], 422);
        }
        $user = $this->getUser();
        if (!is_string($motDePasse) || $motDePasse === '' || strlen($motDePasse) > 4096 || !$hasher->isPasswordValid($user, $motDePasse)) {
            return $this->json(['success' => false, 'message' => 'Le mot de passe actuel est incorrect.'], 422);
        }
        // Uniformise l’adresse et vérifie qu’aucun autre compte ne l’utilise.
        $email = mb_strtolower(trim($email));
        $existant = $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
        if ($existant && $existant->getId() !== $user->getId()) {
            return $this->json(['success' => false, 'message' => 'Cette adresse e-mail est déjà utilisée.'], 409);
        }
        try {
            $user->setEmail($email)->setDateModification(new \DateTime());
            $em->flush();
            return $this->json(['success' => true, 'email' => $email, 'message' => 'Votre adresse e-mail a été modifiée.']);
        } catch (UniqueConstraintViolationException $exception) {
            return $this->json(['success' => false, 'message' => 'Cette adresse e-mail est déjà utilisée.'], 409);
        }
    }

    #[Route('/espace/mon-profil/mot-de-passe', name: 'app_profil_mot_de_passe', methods: ['POST'])]
    // Vérifie le mot de passe actuel et la confirmation avant d’enregistrer le nouveau mot de passe haché.
    public function motDePasse(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        if (!$this->jetonValide($request, 'profil_mot_de_passe')) return $this->sessionExpiree();
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
    // Enregistre la visibilité d’une section publique sans modifier les autres réglages.
    public function visibilite(Request $request, Connection $connexion): JsonResponse
    {
        if (!$this->jetonValide($request, 'profil_visibilite')) return $this->sessionExpiree();
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
            [...$valeurs, 'id' => $this->getUser()->getId(), 'date' => date('Y-m-d H:i:s'), 'visible' => (int) $visible, 'modification' => date('Y-m-d H:i:s')]
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
