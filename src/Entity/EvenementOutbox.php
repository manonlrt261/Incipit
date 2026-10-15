<?php

namespace App\Entity;

use App\Repository\EvenementOutboxRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvenementOutboxRepository::class)]
class EvenementOutbox
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private ?string $identifiantEvenement = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $typeEvenement = null;

    #[ORM\Column(type: 'json')]
    private array $donnees = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateTransmission = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $statut = null;

    #[ORM\Column(type: 'integer')]
    private ?int $nombreTentatives = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifiantEvenement(): ?string
    {
        return $this->identifiantEvenement;
    }

    public function setIdentifiantEvenement(string $identifiantEvenement): static
    {
        $this->identifiantEvenement = $identifiantEvenement;

        return $this;
    }

    public function getTypeEvenement(): ?string
    {
        return $this->typeEvenement;
    }

    public function setTypeEvenement(string $typeEvenement): static
    {
        $this->typeEvenement = $typeEvenement;

        return $this;
    }

    public function getDonnees(): array
    {
        return $this->donnees;
    }

    public function setDonnees(array $donnees): static
    {
        $this->donnees = $donnees;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateTransmission(): ?\DateTime
    {
        return $this->dateTransmission;
    }

    public function setDateTransmission(?\DateTime $dateTransmission): static
    {
        $this->dateTransmission = $dateTransmission;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getNombreTentatives(): ?int
    {
        return $this->nombreTentatives;
    }

    public function setNombreTentatives(int $nombreTentatives): static
    {
        $this->nombreTentatives = $nombreTentatives;

        return $this;
    }
}
