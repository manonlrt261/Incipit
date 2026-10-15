<?php

namespace App\Entity;

use App\Repository\SouhaitRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SouhaitRepository::class)]
class Souhait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $priorite = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $prixEstime = null;

    #[ORM\Column(type: 'string', length: 2048, nullable: true)]
    private ?string $lienAchat = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateAjout = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateAcquisition = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $statut = null;

    #[ORM\ManyToOne(targetEntity: Livre::class)]
    #[ORM\JoinColumn(name: 'livre_id', referencedColumnName: 'id', nullable: false)]
    private ?Livre $livre = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPriorite(): ?string
    {
        return $this->priorite;
    }

    public function setPriorite(?string $priorite): static
    {
        $this->priorite = $priorite;

        return $this;
    }

    public function getPrixEstime(): ?string
    {
        return $this->prixEstime;
    }

    public function setPrixEstime(?string $prixEstime): static
    {
        $this->prixEstime = $prixEstime;

        return $this;
    }

    public function getLienAchat(): ?string
    {
        return $this->lienAchat;
    }

    public function setLienAchat(?string $lienAchat): static
    {
        $this->lienAchat = $lienAchat;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getDateAjout(): ?\DateTime
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTime $dateAjout): static
    {
        $this->dateAjout = $dateAjout;

        return $this;
    }

    public function getDateAcquisition(): ?\DateTime
    {
        return $this->dateAcquisition;
    }

    public function setDateAcquisition(?\DateTime $dateAcquisition): static
    {
        $this->dateAcquisition = $dateAcquisition;

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

    public function getLivre(): ?Livre
    {
        return $this->livre;
    }

    public function setLivre(?Livre $livre): static
    {
        $this->livre = $livre;

        return $this;
    }
}
