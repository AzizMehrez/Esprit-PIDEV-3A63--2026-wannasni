<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activites')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $activityType = 'suggeree_par_coach'; // suggérée_par_coach / événement_reel

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $organizerId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $category = 'social'; // physique / sociale / loisir / éducative

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $activityDate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $estimatedDuration = null; // in minutes

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $mode = 'presentiel'; // présentiel / virtuel / hybride

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxPlaces = null;

    #[ORM\Column(type: 'integer')]
    private int $reservedPlaces = 0;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $difficultyLevel = 'facile'; // facile / moyen / avancé

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $requiredMaterial = null; // JSON or simple text

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $participationPrice = null; // decimal for money

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $status = null; // planifié / confirmé / terminé / annulé

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $createdAt = null;

    // Legacy fields (keeping for backward compatibility)
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxParticipants = null;

    #[ORM\Column(type: 'integer')]
    private int $currentParticipants = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $coachId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    public function __construct()
    {
        // Only set if not already set from database
        if (!$this->createdAt) {
            $this->createdAt = new \DateTime();
        }
        if (!$this->activityDate) {
            $this->activityDate = new \DateTime();
        }
    }

    // ============ GETTERS & SETTERS ============

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }

    public function getActivityType(): ?string { return $this->activityType; }
    public function setActivityType(?string $activityType): self { $this->activityType = $activityType; return $this; }

    public function getOrganizerId(): ?int { return $this->organizerId; }
    public function setOrganizerId(?int $organizerId): self { $this->organizerId = $organizerId; return $this; }

    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): self { $this->category = $category; return $this; }

    public function getActivityDate(): ?\DateTimeInterface { return $this->activityDate; }
    public function setActivityDate(?\DateTimeInterface $activityDate): self { $this->activityDate = $activityDate; return $this; }

    public function getEstimatedDuration(): ?int { return $this->estimatedDuration; }
    public function setEstimatedDuration(?int $duration): self { $this->estimatedDuration = $duration; return $this; }

    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }

    public function getMode(): ?string { return $this->mode; }
    public function setMode(?string $mode): self { $this->mode = $mode; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getMaxPlaces(): ?int { return $this->maxPlaces; }
    public function setMaxPlaces(?int $maxPlaces): self { $this->maxPlaces = $maxPlaces; return $this; }

    public function getReservedPlaces(): int { return $this->reservedPlaces; }
    public function setReservedPlaces(int $reservedPlaces): self { $this->reservedPlaces = $reservedPlaces; return $this; }

    public function getDifficultyLevel(): ?string { return $this->difficultyLevel; }
    public function setDifficultyLevel(?string $level): self { $this->difficultyLevel = $level; return $this; }

    public function getRequiredMaterial(): ?string { return $this->requiredMaterial; }
    public function setRequiredMaterial(?string $material): self { $this->requiredMaterial = $material; return $this; }

    public function getParticipationPrice(): ?string { return $this->participationPrice; }
    public function setParticipationPrice(?string $price): self { $this->participationPrice = $price; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(?string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $createdAt): self { $this->createdAt = $createdAt; return $this; }

    // ============ LEGACY GETTERS & SETTERS ============

    public function getType(): ?string { return $this->type ?? $this->category; }
    public function setType(?string $type): self { $this->type = $type; return $this; }

    public function getStartTime(): ?\DateTimeInterface { return $this->startTime ?? $this->activityDate; }
    public function setStartTime(?\DateTimeInterface $startTime): self { $this->startTime = $startTime; return $this; }

    public function getEndTime(): ?\DateTimeInterface { return $this->endTime; }
    public function setEndTime(?\DateTimeInterface $endTime): self { $this->endTime = $endTime; return $this; }

    public function getMaxParticipants(): ?int { return $this->maxParticipants ?? $this->maxPlaces; }
    public function setMaxParticipants(?int $max): self { $this->maxParticipants = $max; return $this; }

    public function getCurrentParticipants(): int { return $this->currentParticipants ?? $this->reservedPlaces; }
    public function setCurrentParticipants(int $count): self { $this->currentParticipants = $count; return $this; }

    public function getCoachId(): ?int { return $this->coachId; }
    public function setCoachId(?int $coachId): self { $this->coachId = $coachId; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    public function isFull(): bool
    {
        $max = $this->maxPlaces ?? $this->maxParticipants;
        if ($max === null) return false;
        $current = $this->reservedPlaces ?? $this->currentParticipants;
        return $current >= $max;
    }
}
