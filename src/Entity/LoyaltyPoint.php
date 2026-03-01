<?php

namespace App\Entity;

use App\Repository\LoyaltyPointRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoyaltyPointRepository::class)]
#[ORM\Table(name: 'loyalty_point')]
#[ORM\Index(columns: ['senior_id'], name: 'idx_loyalty_senior')]
class LoyaltyPoint
{
    public const SOURCE_INTERVENTION = 'intervention';
    public const SOURCE_SUBSCRIPTION = 'subscription';
    public const SOURCE_ACTIVITY = 'activity';
    public const SOURCE_BONUS = 'bonus';
    public const SOURCE_REDEMPTION = 'redemption';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $senior;

    #[ORM\Column(type: 'integer')]
    private int $points = 0;

    #[ORM\Column(length: 50)]
    private string $source; // intervention, subscription, activity, bonus, redemption

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $sourceId = null; // ID of the intervention, subscription, etc.

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $earnedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct()
    {
        $this->earnedAt = new \DateTime();
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

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): self
    {
        $this->points = $points;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(?int $sourceId): self
    {
        $this->sourceId = $sourceId;
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

    public function getEarnedAt(): \DateTimeInterface
    {
        return $this->earnedAt;
    }

    protected function setEarnedAt(\DateTimeInterface $earnedAt): self
    {
        $this->earnedAt = $earnedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }
}
