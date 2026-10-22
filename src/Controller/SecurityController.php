<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    // Affiche la connexion et sa dernière erreur, ou redirige une personne déjà connectée vers le tableau de bord.
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_tableau_de_bord');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout_confirmation', methods: ['GET'])]
    // Une ouverture directe propose une confirmation sans terminer la session par une requête GET.
    public function confirmerDeconnexion(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        return $this->render('security/logout.html.twig');
    }

    #[Route(path: '/logout', name: 'app_logout', methods: ['POST'])]
    // Déclare la route de déconnexion ; Symfony Security intercepte la requête et termine la session.
    public function logout(): void
    {
        throw new \LogicException(
            'Cette route est interceptée par Symfony Security.'
        );
    }
}
