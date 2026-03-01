<?php

namespace App\Entity;

use App\Repository\BeverageOrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BeverageOrderItemRepository::class)]
class BeverageOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BeverageOrder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'beverage_order_id', nullable: false, onDelete: 'CASCADE')]
    private ?BeverageOrder $order = null;

    #[ORM\ManyToOne(targetEntity: BeverageProduct::class, inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BeverageProduct $product = null;

    #[ORM\Column]
    private int $quantity = 1;

    /** Unit price at purchase time */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $addedAt;

    public function __construct()
    {
        $this->addedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getOrder(): ?BeverageOrder { return $this->order; }
    public function setOrder(?BeverageOrder $order): static { $this->order = $order; return $this; }

    public function getProduct(): ?BeverageProduct { return $this->product; }
    public function setProduct(?BeverageProduct $product): static { $this->product = $product; return $this; }

    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = max(1, $quantity); return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }

    public function getLineTotal(): string
    {
        return number_format((float)$this->unitPrice * $this->quantity, 2, '.', '');
    }

    public function getAddedAt(): \DateTimeInterface { return $this->addedAt; }
}
