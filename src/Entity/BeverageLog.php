<?php

namespace App\Entity;

use App\Repository\BeverageLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BeverageLogRepository::class)]
class BeverageLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Beverage $beverage = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $customBeverageName = null; // Si boisson hors catalogue

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $category = null;

    #[ORM\Column]
    private int $quantityMl = 250; // Quantité en ml

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $consumedAt = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $moment = null; // matin, déjeuner, etc.

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $satisfactionRating = null; // 1-5

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'boolean')]
    private bool $wasRecommended = false; // Était-ce une suggestion du sommelier?

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $mealContext = null; // Ce que l'utilisateur mangeait

    public function __construct()
    {
        $this->consumedAt = new \DateTime();
    }

    // ── Getters & Setters ──

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getBeverage(): ?Beverage { return $this->beverage; }
    public function setBeverage(?Beverage $beverage): static { $this->beverage = $beverage; return $this; }
    public function getCustomBeverageName(): ?string { return $this->customBeverageName; }
    public function setCustomBeverageName(?string $name): static { $this->customBeverageName = $name; return $this; }
    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): static { $this->category = $category; return $this; }
    public function getQuantityMl(): int { return $this->quantityMl; }
    public function setQuantityMl(int $quantityMl): static { $this->quantityMl = $quantityMl; return $this; }
    public function getConsumedAt(): ?\DateTimeInterface { return $this->consumedAt; }
    public function setConsumedAt(?\DateTimeInterface $consumedAt): static { $this->consumedAt = $consumedAt; return $this; }
    public function getMoment(): ?string { return $this->moment; }
    public function setMoment(?string $moment): static { $this->moment = $moment; return $this; }
    public function getSatisfactionRating(): ?int { return $this->satisfactionRating; }
    public function setSatisfactionRating(?int $satisfactionRating): static { $this->satisfactionRating = $satisfactionRating; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }
    public function isWasRecommended(): bool { return $this->wasRecommended; }
    public function setWasRecommended(bool $wasRecommended): static { $this->wasRecommended = $wasRecommended; return $this; }
    public function getMealContext(): ?array { return $this->mealContext; }
    public function setMealContext(?array $mealContext): static { $this->mealContext = $mealContext; return $this; }

    public function getBeverageName(): string
    {
        if ($this->beverage) {
            return $this->beverage->getName();
        }
        return $this->customBeverageName ?? 'Boisson inconnue';
    }

    public function getBeverageEmoji(): string
    {
        if ($this->beverage) {
            return $this->beverage->getCategoryEmoji();
        }
        return match ($this->category) {
            'thé' => '🍵',
            'café' => '☕',
            'infusion' => '🌿',
            'eau' => '💧',
            'jus' => '🧃',
            'smoothie' => '🥤',
            'sirop_sans_sucre' => '🍯',
            'mocktail' => '🍹',
            default => '🥂',
        };
    }
}
