<?php

namespace App\Entity;

/**
 * Intervention Entity - Represents an intervention assigned to a technician
 */
class Intervention
{
    private ?int $id = null;
    private ?int $serviceRequestId = null;
    private ?int $technicianId = null;
    private string $status = 'scheduled'; // scheduled, started, completed, cancelled
    private ?\DateTimeInterface $scheduledDate = null;
    private ?\DateTimeInterface $startedAt = null;
    private ?\DateTimeInterface $completedAt = null;
    private ?string $notes = null;

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getServiceRequestId(): ?int { return $this->serviceRequestId; }
    public function getTechnicianId(): ?int { return $this->technicianId; }
    public function getStatus(): string { return $this->status; }
    public function getScheduledDate(): ?\DateTimeInterface { return $this->scheduledDate; }
    public function getStartedAt(): ?\DateTimeInterface { return $this->startedAt; }
    public function getCompletedAt(): ?\DateTimeInterface { return $this->completedAt; }
    public function getNotes(): ?string { return $this->notes; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setServiceRequestId(?int $serviceRequestId): self { $this->serviceRequestId = $serviceRequestId; return $this; }
    public function setTechnicianId(?int $technicianId): self { $this->technicianId = $technicianId; return $this; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    public function setScheduledDate(?\DateTimeInterface $scheduledDate): self { $this->scheduledDate = $scheduledDate; return $this; }
    public function setStartedAt(?\DateTimeInterface $startedAt): self { $this->startedAt = $startedAt; return $this; }
    public function setCompletedAt(?\DateTimeInterface $completedAt): self { $this->completedAt = $completedAt; return $this; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
}
