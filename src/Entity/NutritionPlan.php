<?php

namespace App\Entity;

use App\Repository\NutritionPlanRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * NutritionPlan Entity - Dietary plan for a senior
 */
#[ORM\Entity(repositoryClass: NutritionPlanRepository::class)]
#[ORM\Table(name: 'nutrition_plan')]
class NutritionPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'senior_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $senior = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $dailyCalorieTarget = 2000;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $dietaryRestrictions = []; // gluten-free, dairy-free, etc.

    #[ORM\Column(type: 'json', nullable: true)]
    private array $allergies = []; // nuts, shellfish, etc.

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSenior(): ?User { return $this->senior; }
    public function getDailyCalorieTarget(): ?int { return $this->dailyCalorieTarget; }
    public function getDietaryRestrictions(): array { return $this->dietaryRestrictions; }
    public function getAllergies(): array { return $this->allergies; }
    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function isActive(): bool { return $this->isActive; }

    // Setters
    public function setSenior(?User $senior): self { $this->senior = $senior; return $this; }
    public function setDailyCalorieTarget(?int $target): self { $this->dailyCalorieTarget = $target; return $this; }
    public function setDietaryRestrictions(array $restrictions): self { $this->dietaryRestrictions = $restrictions; return $this; }
    public function setAllergies(array $allergies): self { $this->allergies = $allergies; return $this; }
    public function setStartDate(?\DateTimeInterface $date): self { $this->startDate = $date; return $this; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}
