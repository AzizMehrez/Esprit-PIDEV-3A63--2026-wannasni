<?php

namespace App\Entity;

use App\Repository\SubscriptionPlanRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\Table(name: 'subscription_plan')]
class SubscriptionPlan
{
    public const PLAN_ESSENTIEL = 'essentiel';
    public const PLAN_CONFORT = 'confort';
    public const PLAN_PREMIUM = 'premium';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $slug; // essentiel, confort, premium

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $priceMonthly; // prix mensuel en €

    #[ORM\Column(type: 'integer')]
    private int $discountPercent; // 10, 20, 30

    #[ORM\Column(type: 'integer')]
    private int $maintenancesPerYear; // 1, 2, 12

    #[ORM\Column(type: 'boolean')]
    private bool $prioriteUrgences = false;

    #[ORM\Column(type: 'boolean')]
    private bool $technicienDedie = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $stripePriceId = null; // Stripe Price ID pour prélèvements

    #[ORM\Column(type: 'boolean')]
    private bool $active = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    // ─── Getters & Setters ──────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getPriceMonthly(): string
    {
        return $this->priceMonthly;
    }

    public function setPriceMonthly(string $priceMonthly): self
    {
        $this->priceMonthly = $priceMonthly;
        return $this;
    }

    public function getDiscountPercent(): int
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(int $discountPercent): self
    {
        $this->discountPercent = $discountPercent;
        return $this;
    }

    public function getMaintenancesPerYear(): int
    {
        return $this->maintenancesPerYear;
    }

    public function setMaintenancesPerYear(int $maintenancesPerYear): self
    {
        $this->maintenancesPerYear = $maintenancesPerYear;
        return $this;
    }

    public function isPrioriteUrgences(): bool
    {
        return $this->prioriteUrgences;
    }

    public function setPrioriteUrgences(bool $prioriteUrgences): self
    {
        $this->prioriteUrgences = $prioriteUrgences;
        return $this;
    }

    public function isTechnicienDedie(): bool
    {
        return $this->technicienDedie;
    }

    public function setTechnicienDedie(bool $technicienDedie): self
    {
        $this->technicienDedie = $technicienDedie;
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

    public function getStripePriceId(): ?string
    {
        return $this->stripePriceId;
    }

    public function setStripePriceId(?string $stripePriceId): self
    {
        $this->stripePriceId = $stripePriceId;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    /**
     * Label formaté du prix
     */
    public function getFormattedPrice(): string
    {
        return number_format((float) $this->priceMonthly, 2, ',', ' ') . '€/mois';
    }

    /**
     * Label de maintenance
     */
    public function getMaintenanceLabel(): string
    {
        if ($this->maintenancesPerYear >= 12) {
            return 'Maintenance mensuelle';
        }
        return $this->maintenancesPerYear . ' maintenance' . ($this->maintenancesPerYear > 1 ? 's' : '') . '/an';
    }
}
