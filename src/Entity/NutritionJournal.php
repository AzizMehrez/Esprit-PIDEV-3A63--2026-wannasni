<?php

namespace App\Entity;

use App\Repository\NutritionJournalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * NutritionJournal Entity - Daily meal and hydration log
 */
#[ORM\Entity(repositoryClass: NutritionJournalRepository::class)]
#[ORM\Table(name: 'nutrition_journal')]
class NutritionJournal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'senior_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $senior = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $mealType = null; // breakfast, lunch, dinner, snack

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $calories = 0;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $waterIntakeMl = 0;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSenior(): ?User { return $this->senior; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function getMealType(): ?string { return $this->mealType; }
    public function getDescription(): ?string { return $this->description; }
    public function getCalories(): ?int { return $this->calories; }
    public function getWaterIntakeMl(): ?float { return $this->waterIntakeMl; }

    // Setters
    public function setSenior(?User $senior): self { $this->senior = $senior; return $this; }
    public function setDate(?\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function setMealType(?string $mealType): self { $this->mealType = $mealType; return $this; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setCalories(?int $calories): self { $this->calories = $calories; return $this; }
    public function setWaterIntakeMl(?float $ml): self { $this->waterIntakeMl = $ml; return $this; }
}
