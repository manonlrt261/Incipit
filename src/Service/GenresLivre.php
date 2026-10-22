<?php

namespace App\Service;

use App\Entity\Genre;
use App\Entity\Livre;
use Doctrine\ORM\EntityManagerInterface;

final class GenresLivre
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    // Vérifie les identifiants reçus avant de modifier les associations du livre.
    public function lire(array $ids): array
    {
        if (count($ids) > 50) {
            throw new \InvalidArgumentException('Trop de genres sélectionnés.');
        }
        $genres = [];

        foreach ($ids as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new \InvalidArgumentException('Genre invalide.');
            }
            $genre = $this->em->find(Genre::class, (int) $id);

            if (!$genre) {
                throw new \InvalidArgumentException('Le genre sélectionné est introuvable.');
            }
            $genres[(int) $id] = $genre;
        }

        return array_values($genres);
    }

    // Sans genre est exclusif : il remplace une sélection vide et disparaît en présence d’un autre genre.
    public function associer(Livre $livre, array $selection): void
    {
        $genres = array_filter($selection, static fn (Genre $genre) => $genre->getNom() !== 'Sans genre');

        if (!$genres) {
            $sansGenre = $this->em->getRepository(Genre::class)->findOneBy(['nom' => 'Sans genre']);

            if (!$sansGenre) {
                throw new \LogicException('Le genre Sans genre doit être créé par la migration.');
            }
            $genres = [$sansGenre];
        }
        $livre->getGenres()->clear();

        foreach ($genres as $genre) {
            $livre->addGenre($genre);
        }
    }
}
