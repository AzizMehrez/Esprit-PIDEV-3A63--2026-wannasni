<?php

namespace App\Entity;

use App\Repository\RapportHebdomadaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RapportHebdomadaireRepository::class)]
class RapportHebdomadaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?User $nutritionniste = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $senior = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $periodeDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $periodeFin = null;

    #[ORM\Column]
    private ?float $tauxConformite = null;

    #[ORM\Column]
    private array $alimentsProblematiques = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $suggestionsIA = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateGeneration = null;

    #[ORM\Column]
    private ?bool $estLu = null;

    public function __construct()
    {
        $this->dateGeneration = new \DateTime();
        $this->estLu = false;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNutritionniste(): ?User
    {
        return $this->nutritionniste;
    }

    public function setNutritionniste(?User $nutritionniste): static
    {
        $this->nutritionniste = $nutritionniste;

        return $this;
    }

    public function getSenior(): ?User
    {
        return $this->senior;
    }

    public function setSenior(?User $senior): static
    {
        $this->senior = $senior;

        return $this;
    }

    public function getPeriodeDebut(): ?\DateTimeInterface
    {
        return $this->periodeDebut;
    }

    public function setPeriodeDebut(\DateTimeInterface $periodeDebut): static
    {
        $this->periodeDebut = $periodeDebut;

        return $this;
    }

    public function getPeriodeFin(): ?\DateTimeInterface
    {
        return $this->periodeFin;
    }

    public function setPeriodeFin(\DateTimeInterface $periodeFin): static
    {
        $this->periodeFin = $periodeFin;

        return $this;
    }

    public function getTauxConformite(): ?float
    {
        return $this->tauxConformite;
    }

    public function setTauxConformite(float $tauxConformite): static
    {
        $this->tauxConformite = $tauxConformite;

        return $this;
    }

    public function getAlimentsProblematiques(): array
    {
        return $this->alimentsProblematiques;
    }

    public function setAlimentsProblematiques(array $alimentsProblematiques): static
    {
        $this->alimentsProblematiques = $alimentsProblematiques;

        return $this;
    }

    public function getSuggestionsIA(): ?string
    {
        return $this->suggestionsIA;
    }

    public function setSuggestionsIA(?string $suggestionsIA): static
    {
        $this->suggestionsIA = $suggestionsIA;

        return $this;
    }

    public function getDateGeneration(): ?\DateTimeInterface
    {
        return $this->dateGeneration;
    }

    public function setDateGeneration(\DateTimeInterface $dateGeneration): static
    {
        $this->dateGeneration = $dateGeneration;

        return $this;
    }

    public function isEstLu(): ?bool
    {
        return $this->estLu;
    }

    public function setEstLu(bool $estLu): static
    {
        $this->estLu = $estLu;

        return $this;
    }
}
