<?php

namespace App\Entity;

/**
 * NutritionPlan Entity - Dietary plan for a senior
 */
class NutritionPlan
{
    private ?int $id = null;
    private ?int $seniorId = null;
    private ?int $dailyCalorieTarget = 2000;
    private array $dietaryRestrictions = []; // gluten-free, dairy-free, etc.
    private array $allergies = []; // nuts, shellfish, etc.
    private ?\DateTimeInterface $startDate = null;
    private bool $isActive = true;

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getDailyCalorieTarget(): ?int { return $this->dailyCalorieTarget; }
    public function getDietaryRestrictions(): array { return $this->dietaryRestrictions; }
    public function getAllergies(): array { return $this->allergies; }
    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function isActive(): bool { return $this->isActive; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setDailyCalorieTarget(?int $target): self { $this->dailyCalorieTarget = $target; return $this; }
    public function setDietaryRestrictions(array $restrictions): self { $this->dietaryRestrictions = $restrictions; return $this; }
    public function setAllergies(array $allergies): self { $this->allergies = $allergies; return $this; }
    public function setStartDate(?\DateTimeInterface $date): self { $this->startDate = $date; return $this; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}
