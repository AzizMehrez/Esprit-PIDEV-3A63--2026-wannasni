<?php

namespace App\Entity;

use App\Repository\VerificationRequestRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks user verification requests submitted from networking page.
 * Status: pending -> ai_rejected / approved / rejected
 */
#[ORM\Entity(repositoryClass: VerificationRequestRepository::class)]
#[ORM\Table(name: 'verification_request')]
#[ORM\Index(columns: ['status'], name: 'idx_vr_status')]
class VerificationRequest
{
    public const STATUS_PENDING     = 'pending';
    public const STATUS_AI_REJECTED = 'ai_rejected';
    public const STATUS_APPROVED    = 'approved';
    public const STATUS_REJECTED    = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'text')]
    private string $reason = '';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $aiReport = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $aiScore = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reviewNote = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reviewedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // ── Getters & Setters ──

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
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

    public function getAiReport(): ?array
    {
        return $this->aiReport;
    }

    public function setAiReport(?array $aiReport): self
    {
        $this->aiReport = $aiReport;
        return $this;
    }

    public function getAiScore(): ?float
    {
        return $this->aiScore;
    }

    public function setAiScore(?float $aiScore): self
    {
        $this->aiScore = $aiScore;
        return $this;
    }

    public function getReviewedBy(): ?User
    {
        return $this->reviewedBy;
    }

    public function setReviewedBy(?User $reviewedBy): self
    {
        $this->reviewedBy = $reviewedBy;
        return $this;
    }

    public function getReviewNote(): ?string
    {
        return $this->reviewNote;
    }

    public function setReviewNote(?string $reviewNote): self
    {
        $this->reviewNote = $reviewNote;
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

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function setReviewedAt(?\DateTimeInterface $reviewedAt): self
    {
        $this->reviewedAt = $reviewedAt;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAiRejected(): bool
    {
        return $this->status === self::STATUS_AI_REJECTED;
    }
}
