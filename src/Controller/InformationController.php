<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InformationController extends AbstractController
{
    #[Route('/aide-et-contact', name: 'app_aide_contact', methods: ['GET'], defaults: ['page' => 'contact'])]
    #[Route('/cgu', name: 'app_cgu', methods: ['GET'], defaults: ['page' => 'cgu'])]
    #[Route('/mentions-legales', name: 'app_mentions_legales', methods: ['GET'], defaults: ['page' => 'mentions'])]
    #[Route('/donnees-personnelles', name: 'app_donnees_personnelles', methods: ['GET'], defaults: ['page' => 'donnees'])]
    // Affiche le document public sélectionné par les valeurs définies dans les routes.
    public function afficher(string $page): Response
    {
        return $this->render('information/' . $page . '.html.twig');
    }
}
