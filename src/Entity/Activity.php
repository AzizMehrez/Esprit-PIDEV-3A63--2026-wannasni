<?php

namespace App\Entity;

/**
 * Activity Entity - Represents a social/physical activity or event
 */
class Activity
{
    private ?int $id = null;
    private ?string $title = null;
    private ?string $description = null;
    private ?string $type = 'social'; // social, physical, cultural, educational
    private ?\DateTimeInterface $startTime = null;
    private ?\DateTimeInterface $endTime = null;
    private ?string $location = null;
    private ?int $maxParticipants = null;
    private int $currentParticipants = 0;
    private ?int $coachId = null;
    private bool $isActive = true;

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getTitle(): ?string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getType(): ?string { return $this->type; }
    public function getStartTime(): ?\DateTimeInterface { return $this->startTime; }
    public function getEndTime(): ?\DateTimeInterface { return $this->endTime; }
    public function getLocation(): ?string { return $this->location; }
    public function getMaxParticipants(): ?int { return $this->maxParticipants; }
    public function getCurrentParticipants(): int { return $this->currentParticipants; }
    public function getCoachId(): ?int { return $this->coachId; }
    public function isActive(): bool { return $this->isActive; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setTitle(?string $title): self { $this->title = $title; return $this; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setType(?string $type): self { $this->type = $type; return $this; }
    public function setStartTime(?\DateTimeInterface $startTime): self { $this->startTime = $startTime; return $this; }
    public function setEndTime(?\DateTimeInterface $endTime): self { $this->endTime = $endTime; return $this; }
    public function setLocation(?string $location): self { $this->location = $location; return $this; }
    public function setMaxParticipants(?int $max): self { $this->maxParticipants = $max; return $this; }
    public function setCurrentParticipants(int $count): self { $this->currentParticipants = $count; return $this; }
    public function setCoachId(?int $coachId): self { $this->coachId = $coachId; return $this; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }

    /**
     * Check if activity is full
     */
    public function isFull(): bool
    {
        if ($this->maxParticipants === null) {
            return false; // No limit
        }
        return $this->currentParticipants >= $this->maxParticipants;
    }
}
