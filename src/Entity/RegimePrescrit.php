<?php

namespace App\Entity;

use App\Repository\RegimePrescritRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: RegimePrescritRepository::class)]
class RegimePrescrit
{
    // Constantes pour les types de régime
    public const TYPE_NORMAL = 'normal';
    public const TYPE_DIABETIQUE = 'diabétique';
    public const TYPE_HYPO_SODE = 'hypo_sodé';
    public const TYPE_SANS_GLUTEN = 'sans_gluten';
    public const TYPE_CARDIOPROTECTEUR = 'cardioprotecteur';
    
    // Constantes pour les repas par jour
    public const REPAS_3 = '3';
    public const REPAS_4 = '4';
    public const REPAS_5 = '5';
    public const REPAS_6 = '6';
    
    // Constantes pour le suivi requis
    public const SUIVI_AUCUN = 'aucun';
    public const SUIVI_QUOTIDIEN = 'quotidien';
    public const SUIVI_HEBDOMADAIRE = 'hebdomadaire';

    /** @var int|null */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DemandeRegime::class, inversedBy: 'regimesPrescrits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?DemandeRegime $demande = null;

    #[ORM\Column]
    private ?int $seniorId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $datePrescription = null;

    #[ORM\Column(nullable: true)]
    private ?int $nutritionnisteId = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    /* 
     * CONTRÔLE : La date de début ne peut pas être vide.
     */
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date de début ne peut pas être dans le passé.')]
    private ?\DateTimeInterface $dateDebut = null;


    #[ORM\Column(type: Types::DATE_MUTABLE)]
    /* 
     * CONTRÔLE : La date de fin ne peut pas être vide.
     * CONTRÔLE : La date de fin doit être strictement après la date de début.
     */
    #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
    #[Assert\GreaterThan(propertyPath: 'dateDebut', message: 'La date de fin doit être postérieure à la date de début.')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 30)]
    /* 
     * CONTRÔLE : Le type de régime doit correspondre à l'un des types autorisés.
     */
    #[Assert\Choice(callback: 'getTypeRegimeChoices', message: 'Type de régime invalide.')]
    private ?string $typeRegime = null;

    #[ORM\Column]
    /* 
     * CONTRÔLE : Les calories doivent être un nombre strictement positif.
     * Empêche la saisie de 0 ou de valeurs négatives.
     */
    #[Assert\Positive(message: 'Le nombre de calories doit être supérieur à zéro.')]
    private ?int $caloriesJournalieres = null;

    #[ORM\Column(length: 10)]
    /* 
     * CONTRÔLE : Le nombre de repas doit correspondre à l'une des fréquences prévues (3, 4, 5, 6).
     */
    #[Assert\Choice(callback: 'getRepasParJourChoices', message: 'Fréquence de repas invalide.')]
    private ?string $repasParJour = self::REPAS_3;

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $alimentsRecommandes = [];

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private array $alimentsInterdits = [];

    #[ORM\Column]
    /* 
     * CONTRÔLE : L'hydratation (en ml) doit être une valeur positive.
     */
    #[Assert\Positive(message: 'L\'hydratation quotidienne doit être supérieure à zéro.')]
    private ?int $hydratationQuotidienne = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    /* 
     * CONTRÔLE : Sécurité contre les caractères spéciaux non autorisés (uniquement alphanumérique + ponctuation).
     */
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9àâäéèêëïîôöùûüçÀÂÄÉÈÊËÏÎÔÖÙÛÜÇ\s\.,\?!()\'\"-]*$/u',
        message: 'Les recommandations contiennent des caractères non autorisés.'
    )]
    private ?string $recommandationsSpeciales = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $poidsActuel = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $taille = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $niveauActivite = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pathologies = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $difficulteDeglutition = false;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Choice(choices: ['normale', 'molle', 'hachee', 'mixee', 'liquide'], message: 'Texture invalide.')]
    private ?string $textureAlimentaire = null;

    #[ORM\Column(length: 20)]
    /* 
     * CONTRÔLE : Le type de suivi doit être valide (aucun, quotidien, hebdomadaire).
     */
    #[Assert\Choice(callback: 'getSuiviRequisChoices', message: 'Type de suivi invalide.')]
    private ?string $suiviRequis = self::SUIVI_HEBDOMADAIRE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $nutritionniste = null;

    public function __construct()
    {
        $this->datePrescription = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDemande(): ?DemandeRegime
    {
        return $this->demande;
    }

    public function setDemande(?DemandeRegime $demande): static
    {
        $this->demande = $demande;
        return $this;
    }

    public function getSeniorId(): ?int
    {
        return $this->seniorId;
    }

    public function setSeniorId(int $seniorId): static
    {
        $this->seniorId = $seniorId;
        return $this;
    }

    public function getDatePrescription(): ?\DateTimeInterface
    {
        return $this->datePrescription;
    }

    public function setDatePrescription(\DateTimeInterface $datePrescription): static
    {
        $this->datePrescription = $datePrescription;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getTypeRegime(): ?string
    {
        return $this->typeRegime;
    }

    public function setTypeRegime(?string $typeRegime): static
    {
        $this->typeRegime = $typeRegime;
        return $this;
    }

    public function getCaloriesJournalieres(): ?int
    {
        return $this->caloriesJournalieres;
    }

    public function setCaloriesJournalieres(int $caloriesJournalieres): static
    {
        $this->caloriesJournalieres = $caloriesJournalieres;
        return $this;
    }

    public function getRepasParJour(): ?string
    {
        return $this->repasParJour;
    }

    public function setRepasParJour(?string $repasParJour): static
    {
        $this->repasParJour = $repasParJour;
        return $this;
    }

    public function getAlimentsRecommandes(): array
    {
        return $this->alimentsRecommandes;
    }

    public function setAlimentsRecommandes(array $alimentsRecommandes): static
    {
        $this->alimentsRecommandes = $alimentsRecommandes;
        return $this;
    }

    public function getAlimentsInterdits(): array
    {
        return $this->alimentsInterdits;
    }

    public function setAlimentsInterdits(array $alimentsInterdits): static
    {
        $this->alimentsInterdits = $alimentsInterdits;
        return $this;
    }

    public function getHydratationQuotidienne(): ?int
    {
        return $this->hydratationQuotidienne;
    }

    public function setHydratationQuotidienne(int $hydratationQuotidienne): static
    {
        $this->hydratationQuotidienne = $hydratationQuotidienne;
        return $this;
    }

    public function getRecommandationsSpeciales(): ?string
    {
        return $this->recommandationsSpeciales;
    }

    public function setRecommandationsSpeciales(?string $recommandationsSpeciales): static
    {
        $this->recommandationsSpeciales = $recommandationsSpeciales;
        return $this;
    }

    public function getPoidsActuel(): ?float { return $this->poidsActuel; }
    public function setPoidsActuel(?float $poidsActuel): static { $this->poidsActuel = $poidsActuel; return $this; }

    public function getTaille(): ?float { return $this->taille; }
    public function setTaille(?float $taille): static { $this->taille = $taille; return $this; }

    public function getNiveauActivite(): ?string { return $this->niveauActivite; }
    public function setNiveauActivite(?string $niveauActivite): static { $this->niveauActivite = $niveauActivite; return $this; }

    public function getPathologies(): ?string { return $this->pathologies; }
    public function setPathologies(?string $pathologies): static { $this->pathologies = $pathologies; return $this; }

    public function isDifficulteDeglutition(): ?bool { return $this->difficulteDeglutition; }
    public function setDifficulteDeglutition(?bool $difficulteDeglutition): static { $this->difficulteDeglutition = $difficulteDeglutition; return $this; }

    public function getTextureAlimentaire(): ?string { return $this->textureAlimentaire; }
    public function setTextureAlimentaire(?string $textureAlimentaire): static { $this->textureAlimentaire = $textureAlimentaire; return $this; }

    public function getImc(): ?float
    {
        if ($this->poidsActuel && $this->taille && $this->taille > 0) {
            $tailleM = $this->taille / 100;
            return round($this->poidsActuel / ($tailleM * $tailleM), 1);
        }
        return null;
    }

    public function getSuiviRequis(): ?string
    {
        return $this->suiviRequis;
    }

    public function setSuiviRequis(?string $suiviRequis): static
    {
        $this->suiviRequis = $suiviRequis;
        return $this;
    }

    public function getNutritionnisteId(): ?int
    {
        return $this->nutritionnisteId;
    }

    public function setNutritionnisteId(int $nutritionnisteId): static
    {
        $this->nutritionnisteId = $nutritionnisteId;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
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

    public function getTypeRegimeChoices(): array
    {
        return [
            'Normal' => self::TYPE_NORMAL,
            'Diabétique' => self::TYPE_DIABETIQUE,
            'Hypo-sodé' => self::TYPE_HYPO_SODE,
            'Sans gluten' => self::TYPE_SANS_GLUTEN,
            'Cardioprotecteur' => self::TYPE_CARDIOPROTECTEUR,
        ];
    }

    public function getRepasParJourChoices(): array
    {
        return [
            '3 repas' => self::REPAS_3,
            '4 repas' => self::REPAS_4,
            '5 repas' => self::REPAS_5,
            '6 repas' => self::REPAS_6,
        ];
    }

    public function getSuiviRequisChoices(): array
    {
        return [
            'Aucun suivi' => self::SUIVI_AUCUN,
            'Suivi quotidien' => self::SUIVI_QUOTIDIEN,
            'Suivi hebdomadaire' => self::SUIVI_HEBDOMADAIRE,
        ];
    }

    public function __toString(): string
    {
        return sprintf('Régime #%d (Senior: %d)', $this->id, $this->seniorId);
    }
}
