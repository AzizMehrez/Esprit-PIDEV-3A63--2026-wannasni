<?php

namespace App\Entity;

use App\Repository\BeverageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BeverageRepository::class)]
class Beverage
{
    // Catégories de boissons
    public const CATEGORY_TEA = 'thé';
    public const CATEGORY_COFFEE = 'café';
    public const CATEGORY_INFUSION = 'infusion';
    public const CATEGORY_WATER = 'eau';
    public const CATEGORY_JUICE = 'jus';
    public const CATEGORY_SMOOTHIE = 'smoothie';
    public const CATEGORY_SYRUP = 'sirop_sans_sucre';
    public const CATEGORY_MOCKTAIL = 'mocktail';
    public const CATEGORY_OTHER = 'autre';

    // Moments de consommation
    public const MOMENT_MORNING = 'matin';
    public const MOMENT_LUNCH = 'déjeuner';
    public const MOMENT_AFTERNOON = 'après-midi';
    public const MOMENT_DINNER = 'dîner';
    public const MOMENT_EVENING = 'soirée';
    public const MOMENT_ANYTIME = 'tout_moment';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le nom de la boisson est obligatoire.')]
    private string $name = '';

    #[ORM\Column(length: 50)]
    #[Assert\Choice(callback: 'getCategoryChoices', message: 'Catégorie invalide.')]
    private string $category = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?int $caloriesPer100ml = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $nutritionalInfo = null; // {sugar, caffeine, antioxidants, vitamins...}

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $healthBenefits = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $idealMoments = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $pairingMeals = []; // Accords mets-boissons

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $compatibleRegimes = []; // diabétique, hypo_sodé, etc.

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $contraindications = []; // conditions de santé à éviter

    #[ORM\Column(nullable: true)]
    private ?int $hydrationScore = null; // 0-100

    #[ORM\Column(type: 'boolean')]
    private bool $isSugarFree = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isCaffeineFree = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $origin = null; // Pays/région d'origine

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $brand = null; // Marque partenaire

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $temperatureMin = null; // Température de service min (°C)

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $temperatureMax = null; // Température de service max (°C)

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $preparationInstructions = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public static function getCategoryChoices(): array
    {
        return [
            self::CATEGORY_TEA,
            self::CATEGORY_COFFEE,
            self::CATEGORY_INFUSION,
            self::CATEGORY_WATER,
            self::CATEGORY_JUICE,
            self::CATEGORY_SMOOTHIE,
            self::CATEGORY_SYRUP,
            self::CATEGORY_MOCKTAIL,
            self::CATEGORY_OTHER,
        ];
    }

    public static function getMomentChoices(): array
    {
        return [
            self::MOMENT_MORNING,
            self::MOMENT_LUNCH,
            self::MOMENT_AFTERNOON,
            self::MOMENT_DINNER,
            self::MOMENT_EVENING,
            self::MOMENT_ANYTIME,
        ];
    }

    // ── Getters & Setters ──

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCaloriesPer100ml(): ?int { return $this->caloriesPer100ml; }
    public function setCaloriesPer100ml(?int $caloriesPer100ml): static { $this->caloriesPer100ml = $caloriesPer100ml; return $this; }
    public function getNutritionalInfo(): ?array { return $this->nutritionalInfo; }
    public function setNutritionalInfo(?array $nutritionalInfo): static { $this->nutritionalInfo = $nutritionalInfo; return $this; }
    public function getHealthBenefits(): ?array { return $this->healthBenefits; }
    public function setHealthBenefits(?array $healthBenefits): static { $this->healthBenefits = $healthBenefits; return $this; }
    public function getIdealMoments(): ?array { return $this->idealMoments; }
    public function setIdealMoments(?array $idealMoments): static { $this->idealMoments = $idealMoments; return $this; }
    public function getPairingMeals(): ?array { return $this->pairingMeals; }
    public function setPairingMeals(?array $pairingMeals): static { $this->pairingMeals = $pairingMeals; return $this; }
    public function getCompatibleRegimes(): ?array { return $this->compatibleRegimes; }
    public function setCompatibleRegimes(?array $compatibleRegimes): static { $this->compatibleRegimes = $compatibleRegimes; return $this; }
    public function getContraindications(): ?array { return $this->contraindications; }
    public function setContraindications(?array $contraindications): static { $this->contraindications = $contraindications; return $this; }
    public function getHydrationScore(): ?int { return $this->hydrationScore; }
    public function setHydrationScore(?int $hydrationScore): static { $this->hydrationScore = $hydrationScore; return $this; }
    public function isSugarFree(): bool { return $this->isSugarFree; }
    public function setIsSugarFree(bool $isSugarFree): static { $this->isSugarFree = $isSugarFree; return $this; }
    public function isCaffeineFree(): bool { return $this->isCaffeineFree; }
    public function setIsCaffeineFree(bool $isCaffeineFree): static { $this->isCaffeineFree = $isCaffeineFree; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }
    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $origin): static { $this->origin = $origin; return $this; }
    public function getBrand(): ?string { return $this->brand; }
    public function setBrand(?string $brand): static { $this->brand = $brand; return $this; }
    public function getTemperatureMin(): ?int { return $this->temperatureMin; }
    public function setTemperatureMin(?int $temperatureMin): static { $this->temperatureMin = $temperatureMin; return $this; }
    public function getTemperatureMax(): ?int { return $this->temperatureMax; }
    public function setTemperatureMax(?int $temperatureMax): static { $this->temperatureMax = $temperatureMax; return $this; }
    public function getPreparationInstructions(): ?string { return $this->preparationInstructions; }
    public function setPreparationInstructions(?string $preparationInstructions): static { $this->preparationInstructions = $preparationInstructions; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    protected function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getTemperatureRange(): string
    {
        if ($this->temperatureMin !== null && $this->temperatureMax !== null) {
            return $this->temperatureMin . '°C - ' . $this->temperatureMax . '°C';
        }
        return 'Non spécifié';
    }

    public function getCategoryEmoji(): string
    {
        return match ($this->category) {
            self::CATEGORY_TEA => '🍵',
            self::CATEGORY_COFFEE => '☕',
            self::CATEGORY_INFUSION => '🌿',
            self::CATEGORY_WATER => '💧',
            self::CATEGORY_JUICE => '🧃',
            self::CATEGORY_SMOOTHIE => '🥤',
            self::CATEGORY_SYRUP => '🍯',
            self::CATEGORY_MOCKTAIL => '🍹',
            default => '🥂',
        };
    }
}
