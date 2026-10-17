<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    // Reçoit les services nécessaires à la création des liens de réinitialisation et à la mise à jour du compte.
    public function __construct(
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('', name: 'app_forgot_password_request')]
    // Traite la demande de lien de réinitialisation du mot de passe.
    public function request(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();

            return $this->processSendingPasswordResetEmail($email, $mailer);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/check-email', name: 'app_check_email')]
    // Affiche la confirmation d’envoi sans révéler si l’adresse appartient à un compte.
    public function checkEmail(): Response
    {
        // Utilise un faux jeton en l’absence de demande en session pour ne pas révéler l’existence d’un compte.
        if (null === ($resetToken = $this->getTokenObjectFromSession())) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    // Vérifie le lien de réinitialisation et traite le formulaire du nouveau mot de passe.
    public function reset(Request $request, UserPasswordHasherInterface $passwordHasher, TranslatorInterface $translator, ?string $token = null): Response
    {
        if ($token) {
            // Conserve le jeton en session puis le retire de l’URL pour limiter son exposition aux scripts de la page.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        try {
            /** @var Utilisateur $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('reset_password_error', sprintf(
                '%s - %s',
                $translator->trans(ResetPasswordExceptionInterface::MESSAGE_PROBLEM_VALIDATE, [], 'ResetPasswordBundle'),
                $translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));

            return $this->redirectToRoute('app_forgot_password_request');
        }

        // Le jeton valide autorise l’affichage et le traitement du formulaire du nouveau mot de passe.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Invalide la demande pour que le lien de réinitialisation ne puisse servir qu’une seule fois.
            $this->resetPasswordHelper->removeResetRequest($token);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Enregistre uniquement le mot de passe haché, jamais la saisie en clair.
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            // Efface les données temporaires de réinitialisation après la modification réussie.
            $this->cleanSessionAfterReset();

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    // Prépare et envoie le lien de réinitialisation en gardant une réponse identique pour les adresses inconnues.
    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer): RedirectResponse
    {
        $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Renvoie la même confirmation pour une adresse inconnue que pour une adresse enregistrée.
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            // Garde la même réponse en cas de refus pour ne pas révéler l’existence d’un compte.
            return $this->redirectToRoute('app_check_email');
        }

        
        $email = (new TemplatedEmail())
            ->from(new Address('incipitbibliothequevirtuelle@gmail.com', 'Incipit'))
            ->to((string) $user->getEmail())
            ->subject('Incipit - Réinitialisation de votre mot de passe')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
            ]);


        $mailer->send($email);

        // Transmet la durée de validité du lien à la page de confirmation via la session.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
