<?php

namespace App\Entity;

use App\Repository\PretRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PretRepository::class)]
class Pret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $datePret = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateRetourPrevue = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateRetourEffective = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $statut = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: Exemplaire::class)]
    #[ORM\JoinColumn(name: 'exemplaire_id', referencedColumnName: 'id', nullable: false)]
    private ?Exemplaire $exemplaire = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(name: 'contact_id', referencedColumnName: 'id', nullable: false)]
    private ?Contact $contact = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatePret(): ?\DateTime
    {
        return $this->datePret;
    }

    public function setDatePret(\DateTime $datePret): static
    {
        $this->datePret = $datePret;

        return $this;
    }

    public function getDateRetourPrevue(): ?\DateTime
    {
        return $this->dateRetourPrevue;
    }

    public function setDateRetourPrevue(?\DateTime $dateRetourPrevue): static
    {
        $this->dateRetourPrevue = $dateRetourPrevue;

        return $this;
    }

    public function getDateRetourEffective(): ?\DateTime
    {
        return $this->dateRetourEffective;
    }

    public function setDateRetourEffective(?\DateTime $dateRetourEffective): static
    {
        $this->dateRetourEffective = $dateRetourEffective;

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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

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

    public function getExemplaire(): ?Exemplaire
    {
        return $this->exemplaire;
    }

    public function setExemplaire(?Exemplaire $exemplaire): static
    {
        $this->exemplaire = $exemplaire;

        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;

        return $this;
    }
}
