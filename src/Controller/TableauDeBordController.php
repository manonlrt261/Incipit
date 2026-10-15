<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TableauDeBordController extends AbstractController
{
    #[Route('/tableau-de-bord', name: 'app_tableau_de_bord', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('tableau_de_bord/index.html.twig');
    }
}
