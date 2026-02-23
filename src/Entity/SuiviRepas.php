<?php

namespace App\Entity;

use App\Repository\SuiviRepasRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuiviRepasRepository::class)]
class SuiviRepas
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $senior = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?RegimePrescrit $regimePrescrit = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column]
    private array $alimentsIdentifies = [];

    #[ORM\Column(nullable: true)]
    private ?int $caloriesCalculees = null;

    #[ORM\Column]
    private ?bool $estConforme = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentairesIA = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateRepas = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $scoreNutritionnel = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $portionsEstimees = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $modeCuisson = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $scoreRisque = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $analyseTexture = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $detailsNutriments = null;

    public function __construct()
    {
        $this->dateRepas = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRegimePrescrit(): ?RegimePrescrit
    {
        return $this->regimePrescrit;
    }

    public function setRegimePrescrit(?RegimePrescrit $regimePrescrit): static
    {
        $this->regimePrescrit = $regimePrescrit;

        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): static
    {
        $this->photoUrl = $photoUrl;

        return $this;
    }

    public function getAlimentsIdentifies(): array
    {
        return $this->alimentsIdentifies;
    }

    public function setAlimentsIdentifies(array $alimentsIdentifies): static
    {
        $this->alimentsIdentifies = $alimentsIdentifies;

        return $this;
    }

    public function getCaloriesCalculees(): ?int
    {
        return $this->caloriesCalculees;
    }

    public function setCaloriesCalculees(?int $caloriesCalculees): static
    {
        $this->caloriesCalculees = $caloriesCalculees;

        return $this;
    }

    public function isEstConforme(): ?bool
    {
        return $this->estConforme;
    }

    public function setEstConforme(bool $estConforme): static
    {
        $this->estConforme = $estConforme;

        return $this;
    }

    public function getCommentairesIA(): ?string
    {
        return $this->commentairesIA;
    }

    public function setCommentairesIA(?string $commentairesIA): static
    {
        $this->commentairesIA = $commentairesIA;

        return $this;
    }

    public function getDateRepas(): ?\DateTimeInterface
    {
        return $this->dateRepas;
    }

    public function setDateRepas(\DateTimeInterface $dateRepas): static
    {
        $this->dateRepas = $dateRepas;

        return $this;
    }

    public function getScoreNutritionnel(): ?int { return $this->scoreNutritionnel; }
    public function setScoreNutritionnel(?int $scoreNutritionnel): static { $this->scoreNutritionnel = $scoreNutritionnel; return $this; }

    public function getPortionsEstimees(): ?array { return $this->portionsEstimees; }
    public function setPortionsEstimees(?array $portionsEstimees): static { $this->portionsEstimees = $portionsEstimees; return $this; }

    public function getModeCuisson(): ?string { return $this->modeCuisson; }
    public function setModeCuisson(?string $modeCuisson): static { $this->modeCuisson = $modeCuisson; return $this; }

    public function getScoreRisque(): ?int { return $this->scoreRisque; }
    public function setScoreRisque(?int $scoreRisque): static { $this->scoreRisque = $scoreRisque; return $this; }

    public function getAnalyseTexture(): ?array { return $this->analyseTexture; }
    public function setAnalyseTexture(?array $analyseTexture): static { $this->analyseTexture = $analyseTexture; return $this; }

    public function getDetailsNutriments(): ?array { return $this->detailsNutriments; }
    public function setDetailsNutriments(?array $detailsNutriments): static { $this->detailsNutriments = $detailsNutriments; return $this; }
}
