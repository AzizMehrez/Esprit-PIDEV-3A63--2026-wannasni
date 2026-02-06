<?php

namespace App\Entity;

/**
 * ServiceRequest Entity - Represents a service request from a senior
 */
class ServiceRequest
{
    private ?int $id = null;
    private ?int $seniorId = null;
    private ?string $category = null; // plumbing, groceries, cleaning, etc.
    private ?string $description = null;
    private string $urgency = 'normal'; // low, normal, high
    private string $status = 'requested'; // requested, assigned, in_progress, completed, rated
    private ?\DateTimeInterface $requestedAt = null;
    private ?\DateTimeInterface $scheduledAt = null;
    private ?\DateTimeInterface $completedAt = null;
    private ?int $assignedToId = null;
    private ?int $rating = null;
    private ?string $feedback = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getCategory(): ?string { return $this->category; }
    public function getDescription(): ?string { return $this->description; }
    public function getUrgency(): string { return $this->urgency; }
    public function getStatus(): string { return $this->status; }
    public function getRequestedAt(): ?\DateTimeInterface { return $this->requestedAt; }
    public function getScheduledAt(): ?\DateTimeInterface { return $this->scheduledAt; }
    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function getAssignedToId(): ?int { return $this->assignedToId; }
    public function getRating(): ?int { return $this->rating; }
    public function getFeedback(): ?string { return $this->feedback; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setCategory(?string $category): self { $this->category = $category; return $this; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function setUrgency(string $urgency): self { $this->urgency = $urgency; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setRequestedAt(?\DateTimeInterface $requestedAt): self { $this->requestedAt = $requestedAt; return $this; }
    public function setScheduledAt(?\DateTimeInterface $scheduledAt): self { $this->scheduledAt = $scheduledAt; return $this; }
    public function setCompletedAt(?\DateTimeInterface $completedAt): self { $this->completedAt = $completedAt; return $this; }
    public function setAssignedToId(?int $assignedToId): self { $this->assignedToId = $assignedToId; return $this; }
    public function setRating(?int $rating): self { $this->rating = $rating; return $this; }
    public function setFeedback(?string $feedback): self { $this->feedback = $feedback; return $this; }
}
