<?php

namespace App\Entity;

use App\Repository\LivreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LivreRepository::class)]
#[ORM\Table(name: 'livre')]
class Livre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(name: 'sous_titre', length: 255, nullable: true)]
    private ?string $sousTitre = null;

    #[ORM\Column(length: 13, nullable: true)]
    private ?string $isbn13 = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $isbn10 = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resume = null;

    #[ORM\Column(name: 'date_publication', type: 'date', nullable: true)]
    private ?\DateTime $datePublication = null;

    #[ORM\Column(name: 'nombre_pages', type: 'integer', nullable: true)]
    private ?int $nombrePages = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $langue = null;

    #[ORM\Column(name: 'image_couverture', length: 2048, nullable: true)]
    private ?string $imageCouverture = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(name: 'date_modification', type: 'datetime')]
    private ?\DateTime $dateModification = null;

    #[ORM\ManyToMany(targetEntity: Auteur::class)]
    #[ORM\JoinTable(name: 'livre_auteur')]
    #[ORM\JoinColumn(
    name: 'livre_id',
    referencedColumnName: 'id',
    onDelete: 'CASCADE'
)]
    #[ORM\InverseJoinColumn(
    name: 'auteur_id',
    referencedColumnName: 'id',
    onDelete: 'CASCADE'
)]
    private Collection $auteurs;

    public function __construct()
    {
        $this->auteurs = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->dateModification = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getSousTitre(): ?string
    {
        return $this->sousTitre;
    }

    public function setSousTitre(?string $sousTitre): static
    {
        $this->sousTitre = $sousTitre;

        return $this;
    }

    public function getIsbn13(): ?string
    {
        return $this->isbn13;
    }

    public function setIsbn13(?string $isbn13): static
    {
        $this->isbn13 = $isbn13;

        return $this;
    }

    public function getIsbn10(): ?string
    {
        return $this->isbn10;
    }

    public function setIsbn10(?string $isbn10): static
    {
        $this->isbn10 = $isbn10;

        return $this;
    }

    public function getResume(): ?string
    {
        return $this->resume;
    }

    public function setResume(?string $resume): static
    {
        $this->resume = $resume;

        return $this;
    }

    public function getDatePublication(): ?\DateTime
    {
        return $this->datePublication;
    }

    public function setDatePublication(?\DateTime $datePublication): static
    {
        $this->datePublication = $datePublication;

        return $this;
    }

    public function getNombrePages(): ?int
    {
        return $this->nombrePages;
    }

    public function setNombrePages(?int $nombrePages): static
    {
        $this->nombrePages = $nombrePages;

        return $this;
    }

    public function getLangue(): ?string
    {
        return $this->langue;
    }

    public function setLangue(?string $langue): static
    {
        $this->langue = $langue;

        return $this;
    }

    public function getImageCouverture(): ?string
    {
        return $this->imageCouverture;
    }

    public function setImageCouverture(?string $imageCouverture): static
    {
        $this->imageCouverture = $imageCouverture;

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

    public function getDateModification(): ?\DateTime
    {
        return $this->dateModification;
    }

    public function setDateModification(\DateTime $dateModification): static
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    /**
     * @return Collection<int, Auteur>
     */
    public function getAuteurs(): Collection
    {
        return $this->auteurs;
    }

    public function addAuteur(Auteur $auteur): static
    {
        if (!$this->auteurs->contains($auteur)) {
            $this->auteurs->add($auteur);
        }

        return $this;
    }

    public function removeAuteur(Auteur $auteur): static
    {
        $this->auteurs->removeElement($auteur);

        return $this;
    }
}
