<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'objectif_utilisateur_annee', columns: ['utilisateur_id', 'annee'])]
class ObjectifAnnuel
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\Column(name: 'utilisateur_id')]
    private int $utilisateurId;
    #[ORM\Column]
    private int $annee;
    #[ORM\Column]
    private int $nombreLivres;

    public function getId(): ?int { return $this->id; }
    public function getUtilisateurId(): int { return $this->utilisateurId; }
    public function setUtilisateurId(int $id): static { $this->utilisateurId = $id; return $this; }
    public function getAnnee(): int { return $this->annee; }
    public function setAnnee(int $annee): static { $this->annee = $annee; return $this; }
    public function getNombreLivres(): int { return $this->nombreLivres; }
    public function setNombreLivres(int $nombre): static
    {
        if ($nombre < 1) throw new \InvalidArgumentException('L’objectif doit être positif.');
        $this->nombreLivres = $nombre;
        return $this;
    }
}
