<?php

namespace App\Controller;

use App\Entity\Livre;
use App\Form\LivreType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LivreController extends AbstractController
{
    #[Route('/livre', name: 'app_livre', methods: ['GET'])]
public function index(
    \App\Repository\LivreRepository $livreRepository
): Response {
    $livres = $livreRepository->findBy([], ['id' => 'DESC']);

    return $this->render('livre/index.html.twig', [
        'livres' => $livres,
    ]);
}

    #[Route('/livre/ajouter', name: 'app_livre_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Création d'un nouveau livre
        $livre = new Livre();

        // Création du formulaire à partir de LivreType
        $form = $this->createForm(LivreType::class, $livre);

        // Récupération des données envoyées
        $form->handleRequest($request);

        // Vérification de la soumission et de la validité
        if ($form->isSubmitted() && $form->isValid()) {
            // Enregistrement dans MySQL
            $entityManager->persist($livre);
            $entityManager->flush();

            // Message de confirmation
            $this->addFlash(
                'success',
                'Le livre a été ajouté à votre bibliothèque.'
            );

            // Redirection pour éviter un double enregistrement
            return $this->redirectToRoute('app_livre_ajouter');
        }

        // Affichage du formulaire
        return $this->render('livre/ajouter.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
