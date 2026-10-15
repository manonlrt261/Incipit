<?php

namespace App\Entity;

use App\Repository\ParametreConfidentialiteRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParametreConfidentialiteRepository::class)]
class ParametreConfidentialite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $afficherLivresFavoris = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $afficherAuteursFavoris = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $afficherWishlist = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\OneToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isAfficherLivresFavoris(): ?bool
    {
        return $this->afficherLivresFavoris;
    }

    public function setAfficherLivresFavoris(bool $afficherLivresFavoris): static
    {
        $this->afficherLivresFavoris = $afficherLivresFavoris;

        return $this;
    }

    public function isAfficherAuteursFavoris(): ?bool
    {
        return $this->afficherAuteursFavoris;
    }

    public function setAfficherAuteursFavoris(bool $afficherAuteursFavoris): static
    {
        $this->afficherAuteursFavoris = $afficherAuteursFavoris;

        return $this;
    }

    public function isAfficherWishlist(): ?bool
    {
        return $this->afficherWishlist;
    }

    public function setAfficherWishlist(bool $afficherWishlist): static
    {
        $this->afficherWishlist = $afficherWishlist;

        return $this;
    }

    public function getDateModification(): ?\DateTime
    {
        return $this->dateModification;
    }

    public function setDateModification(\DateTime $dateModification): static
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}
