<?php

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity Entity - Represents a social/physical activity or event
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activites')]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $type = 'social'; // social, physical, cultural, educational

    #[ORM\Column(type: 'datetime', name: 'start_time')]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'datetime', name: 'end_time', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'integer', name: 'max_participants', nullable: true)]
    private ?int $maxParticipants = null;

    #[ORM\Column(type: 'integer', name: 'current_participants')]
    private int $currentParticipants = 0;

    #[ORM\Column(type: 'integer', name: 'coach_id', nullable: true)]
    private ?int $coachId = null;

    #[ORM\Column(type: 'boolean', name: 'is_active')]
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
