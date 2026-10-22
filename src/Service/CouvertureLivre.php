<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CouvertureLivre
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private string $projet)
    {
    }

    // Contrôle les images reçues aussi bien depuis la bibliothèque que depuis la wishlist.
    public function valider(?UploadedFile $fichier): void
    {
        if ($fichier && (!$fichier->isValid() || $fichier->getSize() > 5 * 1024 * 1024
            || !in_array($fichier->getMimeType(), ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true))) {
            throw new \InvalidArgumentException('Choisis une image JPG, PNG, WebP ou GIF de 5 Mo maximum.');
        }
    }

    // Enregistre sous un nom aléatoire pour ne jamais écraser une couverture existante.
    public function enregistrer(UploadedFile $fichier): string
    {
        $dossier = $this->projet . '/public/uploads/couvertures';

        if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            throw new \RuntimeException('Le dossier des couvertures ne peut pas être créé.');
        }
        $nom = bin2hex(random_bytes(16)) . '.' . $fichier->guessExtension();
        $fichier->move($dossier, $nom);

        return 'uploads/couvertures/' . $nom;
    }

    // Retire uniquement le nouveau fichier si l’enregistrement en base échoue.
    public function annuler(?string $chemin): void
    {
        if ($chemin && str_starts_with($chemin, 'uploads/couvertures/') && !str_contains($chemin, '..')) {
            $fichier = $this->projet . '/public/' . $chemin;

            if (is_file($fichier)) {
                unlink($fichier);
            }
        }
    }
}
