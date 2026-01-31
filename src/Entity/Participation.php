<?php

namespace App\Entity;

/**
 * Participation Entity - Represents a senior's participation in an activity
 */
class Participation
{
    private ?int $id = null;
    private ?int $activityId = null;
    private ?int $seniorId = null;
    private string $status = 'registered'; // registered, attended, cancelled
    private ?\DateTimeInterface $registeredAt = null;
    private ?int $rating = null;
    private ?string $feedback = null;

    public function __construct()
    {
        $this->registeredAt = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getActivityId(): ?int { return $this->activityId; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getStatus(): string { return $this->status; }
    public function getRegisteredAt(): ?\DateTimeInterface { return $this->registeredAt; }
    public function getRating(): ?int { return $this->rating; }
    public function getFeedback(): ?string { return $this->feedback; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setActivityId(?int $activityId): self { $this->activityId = $activityId; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setRegisteredAt(?\DateTimeInterface $registeredAt): self { $this->registeredAt = $registeredAt; return $this; }
    public function setRating(?int $rating): self { $this->rating = $rating; return $this; }
    public function setFeedback(?string $feedback): self { $this->feedback = $feedback; return $this; }
}
