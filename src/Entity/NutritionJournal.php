<?php

namespace App\Entity;

/**
 * NutritionJournal Entity - Daily meal and hydration log
 */
class NutritionJournal
{
    private ?int $id = null;
    private ?int $seniorId = null;
    private ?\DateTimeInterface $date = null;
    private ?string $mealType = null; // breakfast, lunch, dinner, snack
    private ?string $description = null;
    private ?int $calories = 0;
    private ?float $waterIntakeMl = 0;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function getMealType(): ?string { return $this->mealType; }
    public function getDescription(): ?string { return $this->description; }
    public function getCalories(): ?int { return $this->calories; }
    public function getWaterIntakeMl(): ?float { return $this->waterIntakeMl; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setDate(?\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function setMealType(?string $mealType): self { $this->mealType = $mealType; return $this; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setCalories(?int $calories): self { $this->calories = $calories; return $this; }
    public function setWaterIntakeMl(?float $ml): self { $this->waterIntakeMl = $ml; return $this; }
}
