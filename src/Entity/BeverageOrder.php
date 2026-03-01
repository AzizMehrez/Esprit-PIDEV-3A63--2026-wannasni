<?php

namespace App\Entity;

use App\Repository\BeverageOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BeverageOrderRepository::class)]
class BeverageOrder
{
    public const STATUS_CART = 'cart';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_CART;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $orderNumber = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalAmount = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $shippingCost = '0.00';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shippingAddress = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shippingCity = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $shippingPostalCode = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paymentMethod = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $confirmedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $shippedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deliveredAt = null;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: BeverageOrderItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getOrderNumber(): ?string { return $this->orderNumber; }
    public function setOrderNumber(?string $orderNumber): static { $this->orderNumber = $orderNumber; return $this; }

    public function getTotalAmount(): string { return $this->totalAmount; }
    public function setTotalAmount(string $totalAmount): static { $this->totalAmount = $totalAmount; return $this; }

    public function getShippingCost(): string { return $this->shippingCost; }
    public function setShippingCost(string $shippingCost): static { $this->shippingCost = $shippingCost; return $this; }

    public function getGrandTotal(): string
    {
        return number_format((float)$this->totalAmount + (float)$this->shippingCost, 2, '.', '');
    }

    public function getShippingAddress(): ?string { return $this->shippingAddress; }
    public function setShippingAddress(?string $v): static { $this->shippingAddress = $v; return $this; }

    public function getShippingCity(): ?string { return $this->shippingCity; }
    public function setShippingCity(?string $v): static { $this->shippingCity = $v; return $this; }

    public function getShippingPostalCode(): ?string { return $this->shippingPostalCode; }
    public function setShippingPostalCode(?string $v): static { $this->shippingPostalCode = $v; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $v): static { $this->phone = $v; return $this; }

    public function getPaymentMethod(): ?string { return $this->paymentMethod; }
    public function setPaymentMethod(?string $v): static { $this->paymentMethod = $v; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $v): static { $this->notes = $v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }
    public function getConfirmedAt(): ?\DateTimeInterface { return $this->confirmedAt; }
    public function setConfirmedAt(?\DateTimeInterface $v): static { $this->confirmedAt = $v; return $this; }
    public function getShippedAt(): ?\DateTimeInterface { return $this->shippedAt; }
    public function setShippedAt(?\DateTimeInterface $v): static { $this->shippedAt = $v; return $this; }
    public function getDeliveredAt(): ?\DateTimeInterface { return $this->deliveredAt; }
    public function setDeliveredAt(?\DateTimeInterface $v): static { $this->deliveredAt = $v; return $this; }

    public function getItems(): Collection { return $this->items; }

    public function addItem(BeverageOrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }
        return $this;
    }

    public function removeItem(BeverageOrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
            }
        }
        return $this;
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }
        return $count;
    }

    public function recalculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += (float)$item->getLineTotal();
        }
        $this->totalAmount = number_format($total, 2, '.', '');
    }

    public function generateOrderNumber(): void
    {
        $this->orderNumber = 'WAN-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 8));
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CART => '🛒 Panier',
            self::STATUS_PENDING => '⏳ En attente',
            self::STATUS_CONFIRMED => '✅ Confirmée',
            self::STATUS_SHIPPED => '📦 Expédiée',
            self::STATUS_DELIVERED => '🎉 Livrée',
            self::STATUS_CANCELLED => '❌ Annulée',
            default => $this->status,
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_CART => '#94a3b8',
            self::STATUS_PENDING => '#f59e0b',
            self::STATUS_CONFIRMED => '#3b82f6',
            self::STATUS_SHIPPED => '#8b5cf6',
            self::STATUS_DELIVERED => '#10b981',
            self::STATUS_CANCELLED => '#ef4444',
            default => '#64748b',
        };
    }
}
