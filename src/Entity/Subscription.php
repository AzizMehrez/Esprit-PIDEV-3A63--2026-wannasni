<?php

namespace App\Entity;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
#[ORM\Table(name: 'subscription')]
#[ORM\Index(columns: ['status'], name: 'idx_subscription_status')]
#[ORM\Index(columns: ['senior_id', 'status'], name: 'idx_subscription_senior_status')]
class Subscription
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_PENDING = 'pending';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Le senior bénéficiaire de l'abonnement */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $senior;

    /** L'utilisateur qui a souscrit (senior lui-même ou famille) */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $subscriber;

    #[ORM\ManyToOne(targetEntity: SubscriptionPlan::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SubscriptionPlan $plan;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startDate;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $nextBillingDate = null;

    #[ORM\Column(type: 'integer')]
    private int $failedPaymentAttempts = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalSaved = '0.00'; // économies cumulées

    #[ORM\Column(type: 'integer')]
    private int $maintenancesUsed = 0; // maintenances utilisées cette année

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $cancelledAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->startDate = new \DateTime();
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

    public function getSubscriber(): User
    {
        return $this->subscriber;
    }

    public function setSubscriber(User $subscriber): self
    {
        $this->subscriber = $subscriber;
        return $this;
    }

    public function getPlan(): SubscriptionPlan
    {
        return $this->plan;
    }

    public function setPlan(SubscriptionPlan $plan): self
    {
        $this->plan = $plan;
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

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): self
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;
        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): self
    {
        $this->stripeCustomerId = $stripeCustomerId;
        return $this;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    protected function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getNextBillingDate(): ?\DateTimeInterface
    {
        return $this->nextBillingDate;
    }

    public function setNextBillingDate(?\DateTimeInterface $nextBillingDate): self
    {
        $this->nextBillingDate = $nextBillingDate;
        return $this;
    }

    public function getFailedPaymentAttempts(): int
    {
        return $this->failedPaymentAttempts;
    }

    public function setFailedPaymentAttempts(int $failedPaymentAttempts): self
    {
        $this->failedPaymentAttempts = $failedPaymentAttempts;
        return $this;
    }

    public function incrementFailedPayments(): self
    {
        $this->failedPaymentAttempts++;
        if ($this->failedPaymentAttempts >= 3) {
            $this->status = self::STATUS_SUSPENDED;
        } else {
            $this->status = self::STATUS_PAST_DUE;
        }
        return $this;
    }

    public function getTotalSaved(): string
    {
        return $this->totalSaved;
    }

    public function setTotalSaved(string $totalSaved): self
    {
        $this->totalSaved = $totalSaved;
        return $this;
    }

    public function addSaving(float $amount): self
    {
        $this->totalSaved = (string) (((float) $this->totalSaved) + $amount);
        return $this;
    }

    public function getMaintenancesUsed(): int
    {
        return $this->maintenancesUsed;
    }

    public function setMaintenancesUsed(int $maintenancesUsed): self
    {
        $this->maintenancesUsed = $maintenancesUsed;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    protected function setCancelledAt(?\DateTimeInterface $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    // ─── Business Logic ─────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function activate(): self
    {
        $this->status = self::STATUS_ACTIVE;
        $this->startDate = new \DateTime();
        $this->endDate = new \DateTime('+1 year');
        $this->failedPaymentAttempts = 0;
        $this->nextBillingDate = new \DateTime('+1 month');
        return $this;
    }

    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = new \DateTime();
        $this->endDate = new \DateTime();
        return $this;
    }

    public function suspend(): self
    {
        $this->status = self::STATUS_SUSPENDED;
        return $this;
    }

    /**
     * Calcule la réduction applicable sur un montant
     */
    public function calculateDiscount(float $amount): float
    {
        if (!$this->isActive()) {
            return 0;
        }
        return $amount * ($this->plan->getDiscountPercent() / 100);
    }

    /**
     * Vérifie si des maintenances sont encore disponibles cette année
     */
    public function hasMaintenancesRemaining(): bool
    {
        return $this->maintenancesUsed < $this->plan->getMaintenancesPerYear();
    }

    /**
     * Nombre de maintenances restantes
     */
    public function getMaintenancesRemaining(): int
    {
        return max(0, $this->plan->getMaintenancesPerYear() - $this->maintenancesUsed);
    }

    /**
     * Label statut traduit
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Actif',
            self::STATUS_SUSPENDED => 'Suspendu',
            self::STATUS_CANCELLED => 'Annulé',
            self::STATUS_PAST_DUE => 'Paiement en retard',
            self::STATUS_PENDING => 'En attente',
            default => $this->status,
        };
    }

    /**
     * Badge CSS class
     */
    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'badge-success',
            self::STATUS_SUSPENDED => 'badge-warning',
            self::STATUS_CANCELLED => 'badge-danger',
            self::STATUS_PAST_DUE => 'badge-warning',
            self::STATUS_PENDING => 'badge-info',
            default => 'badge-secondary',
        };
    }
}
