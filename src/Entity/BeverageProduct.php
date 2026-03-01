<?php

namespace App\Entity;

use App\Repository\BeverageProductRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BeverageProductRepository::class)]
class BeverageProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $shortDescription = null;

    /** Price in DH (Dirhams) */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    /** Discounted price if on sale */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $salePrice = null;

    #[ORM\Column(nullable: true)]
    private ?int $caloriesPer100ml = null;

    #[ORM\Column(nullable: true)]
    private ?int $hydrationScore = null;

    #[ORM\Column]
    private bool $isSugarFree = false;

    #[ORM\Column]
    private bool $isCaffeineFree = false;

    #[ORM\Column]
    private bool $isBio = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $healthBenefits = [];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $nutritionalInfo = [];

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $compatibleRegimes = [];

    #[ORM\Column(type: Types::SIMPLE_ARRAY, nullable: true)]
    private ?array $ingredients = [];

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $brand = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $volume = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column]
    private int $stockQuantity = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $averageRating = null;

    #[ORM\Column]
    private int $reviewCount = 0;

    #[ORM\Column]
    private int $salesCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: BeverageOrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $orderItems;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->orderItems = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getShortDescription(): ?string { return $this->shortDescription; }
    public function setShortDescription(?string $shortDescription): static { $this->shortDescription = $shortDescription; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): static { $this->price = $price; return $this; }

    public function getSalePrice(): ?string { return $this->salePrice; }
    public function setSalePrice(?string $salePrice): static { $this->salePrice = $salePrice; return $this; }

    public function getEffectivePrice(): string
    {
        return $this->salePrice ?: $this->price;
    }

    public function isOnSale(): bool
    {
        return $this->salePrice !== null && $this->salePrice < $this->price;
    }

    public function getDiscountPercent(): int
    {
        if (!$this->isOnSale() || $this->price <= 0) return 0;
        return (int) round((1 - $this->salePrice / $this->price) * 100);
    }

    public function getCaloriesPer100ml(): ?int { return $this->caloriesPer100ml; }
    public function setCaloriesPer100ml(?int $cal): static { $this->caloriesPer100ml = $cal; return $this; }

    public function getHydrationScore(): ?int { return $this->hydrationScore; }
    public function setHydrationScore(?int $score): static { $this->hydrationScore = $score; return $this; }

    public function isSugarFree(): bool { return $this->isSugarFree; }
    public function setIsSugarFree(bool $v): static { $this->isSugarFree = $v; return $this; }

    public function isCaffeineFree(): bool { return $this->isCaffeineFree; }
    public function setIsCaffeineFree(bool $v): static { $this->isCaffeineFree = $v; return $this; }

    public function isBio(): bool { return $this->isBio; }
    public function setIsBio(bool $v): static { $this->isBio = $v; return $this; }

    public function getHealthBenefits(): ?array { return $this->healthBenefits; }
    public function setHealthBenefits(?array $v): static { $this->healthBenefits = $v; return $this; }

    public function getNutritionalInfo(): ?array { return $this->nutritionalInfo; }
    public function setNutritionalInfo(?array $v): static { $this->nutritionalInfo = $v; return $this; }

    public function getCompatibleRegimes(): ?array { return $this->compatibleRegimes; }
    public function setCompatibleRegimes(?array $v): static { $this->compatibleRegimes = $v; return $this; }

    public function getIngredients(): ?array { return $this->ingredients; }
    public function setIngredients(?array $v): static { $this->ingredients = $v; return $this; }

    public function getBrand(): ?string { return $this->brand; }
    public function setBrand(?string $brand): static { $this->brand = $brand; return $this; }

    public function getOrigin(): ?string { return $this->origin; }
    public function setOrigin(?string $origin): static { $this->origin = $origin; return $this; }

    public function getVolume(): ?string { return $this->volume; }
    public function setVolume(?string $volume): static { $this->volume = $volume; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }

    public function getStockQuantity(): int { return $this->stockQuantity; }
    public function setStockQuantity(int $qty): static { $this->stockQuantity = $qty; return $this; }

    public function isInStock(): bool { return $this->stockQuantity > 0; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $v): static { $this->isActive = $v; return $this; }

    public function isFeatured(): bool { return $this->isFeatured; }
    public function setIsFeatured(bool $v): static { $this->isFeatured = $v; return $this; }

    public function getAverageRating(): ?string { return $this->averageRating; }
    public function setAverageRating(?string $v): static { $this->averageRating = $v; return $this; }

    public function getReviewCount(): int { return $this->reviewCount; }
    public function setReviewCount(int $v): static { $this->reviewCount = $v; return $this; }

    public function getSalesCount(): int { return $this->salesCount; }
    public function setSalesCount(int $v): static { $this->salesCount = $v; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }

    public function getOrderItems(): Collection { return $this->orderItems; }

    public function getCategoryEmoji(): string
    {
        return match ($this->category) {
            'thé' => '🍵', 'café' => '☕', 'infusion' => '🌿', 'eau' => '💧',
            'jus' => '🧃', 'smoothie' => '🥤', 'complément' => '💊',
            'sirop_sans_sucre' => '🍯', 'mocktail' => '🍹', 'superaliment' => '🌱',
            default => '🥤',
        };
    }
}
