<?php

namespace App\Service;

use App\Entity\Auteur;
use App\Entity\Livre;
use Doctrine\ORM\EntityManagerInterface;

final class AuteursLivre
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    // Nettoie et déduplique les noms avant toute modification du livre.
    public function nettoyer(array $noms): array
    {
        if (count($noms) > 50) throw new \InvalidArgumentException('Tu peux ajouter 50 auteurs maximum.');
        $resultat = [];
        foreach ($noms as $nom) {
            if (!is_string($nom)) throw new \InvalidArgumentException('Auteur invalide.');
            $nom = preg_replace('/\s+/u', ' ', trim($nom)) ?? '';
            if (mb_strlen($nom) > 255) throw new \InvalidArgumentException('Le nom d’un auteur est trop long.');
            if ($nom !== '') $resultat[mb_strtolower($nom)] = $nom;
        }

        return array_values($resultat);
    }

    // Réutilise la fiche existante, ou crée celle du nouvel auteur au moment d’enregistrer le livre.
    public function associer(Livre $livre, array $noms): void
    {
        $livre->getAuteurs()->clear();
        foreach ($noms as $nom) {
            $auteur = $this->entityManager->getRepository(Auteur::class)->createQueryBuilder('a')
                ->where('LOWER(TRIM(a.nomAffichage)) = :nom')->setParameter('nom', mb_strtolower($nom))
                ->orderBy('a.id', 'ASC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if (!$auteur) {
                // Stocke les nouvelles fiches avec prénom puis nom, tout en gardant le nom complet pour les suggestions.
                $parties = NomsAuteur::separer($nom);
                $auteur = (new Auteur())->setPrenom($parties['prenom'] ?: null)->setNom($parties['nom'])->setNomAffichage($nom);
                $this->entityManager->persist($auteur);
            }
            $livre->addAuteur($auteur);
        }
    }
}
