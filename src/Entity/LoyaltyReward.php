<?php

namespace App\Entity;

use App\Repository\LoyaltyRewardRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoyaltyRewardRepository::class)]
#[ORM\Table(name: 'loyalty_reward')]
#[ORM\Index(columns: ['senior_id', 'status'], name: 'idx_reward_senior_status')]
class LoyaltyReward
{
    public const TYPE_DISCOUNT = 'discount';              // Réduction sur prochaine intervention
    public const TYPE_FREE_MAINTENANCE = 'free_maintenance'; // Visite de maintenance gratuite
    public const TYPE_PLAN_UPGRADE = 'plan_upgrade';       // Upgrade de plan d'abonnement

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $senior;

    #[ORM\Column(length: 50)]
    private string $type; // discount, free_maintenance, plan_upgrade

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    private int $pointsCost = 0; // Points needed to redeem

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $discountPercent = null; // For discount type

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_AVAILABLE;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $mlConfidence = null; // ML prediction confidence

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $mlFeatures = null; // ML features used for prediction

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $redeemedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        // Rewards expire in 90 days by default
        $this->expiresAt = (new \DateTime())->modify('+90 days');
    }

    // ─── Getters & Setters ──────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSenior(): User
    {
        return $this->senior;
    }

    public function setSenior(User $senior): self
    {
        $this->senior = $senior;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPointsCost(): int
    {
        return $this->pointsCost;
    }

    public function setPointsCost(int $pointsCost): self
    {
        $this->pointsCost = $pointsCost;
        return $this;
    }

    public function getDiscountPercent(): ?int
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?int $discountPercent): self
    {
        $this->discountPercent = $discountPercent;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getMlConfidence(): ?float
    {
        return $this->mlConfidence;
    }

    public function setMlConfidence(?float $mlConfidence): self
    {
        $this->mlConfidence = $mlConfidence;
        return $this;
    }

    public function getMlFeatures(): ?array
    {
        return $this->mlFeatures;
    }

    public function setMlFeatures(?array $mlFeatures): self
    {
        $this->mlFeatures = $mlFeatures;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    protected function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getRedeemedAt(): ?\DateTimeInterface
    {
        return $this->redeemedAt;
    }

    public function setRedeemedAt(?\DateTimeInterface $redeemedAt): self
    {
        $this->redeemedAt = $redeemedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    protected function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Get the emoji icon for this reward type
     */
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            self::TYPE_DISCOUNT => '🏷️',
            self::TYPE_FREE_MAINTENANCE => '🔧',
            self::TYPE_PLAN_UPGRADE => '⭐',
            default => '🎁',
        };
    }

    /**
     * Get french label for the reward type
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_DISCOUNT => 'Réduction',
            self::TYPE_FREE_MAINTENANCE => 'Maintenance Gratuite',
            self::TYPE_PLAN_UPGRADE => 'Upgrade Abonnement',
            default => 'Récompense',
        };
    }

    /**
     * Check if reward can be redeemed
     */
    public function isRedeemable(): bool
    {
        if ($this->status !== self::STATUS_AVAILABLE) {
            return false;
        }
        if ($this->expiresAt && $this->expiresAt < new \DateTime()) {
            return false;
        }
        return true;
    }
}
