<?php

namespace App\Entity;

/**
 * Treatment Entity - Represents a prescribed treatment/medication
 */
class Treatment
{
    private ?int $id = null;
    private ?int $seniorId = null;
    private ?int $prescribedByDoctorId = null;
    private ?string $medication = null;
    private ?string $dosage = null;
    private ?string $frequency = null; // daily, twice_daily, weekly, etc.
    private ?string $instructions = null;
    private ?\DateTimeInterface $startDate = null;
    private ?\DateTimeInterface $endDate = null;
    private bool $isActive = true;

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getPrescribedByDoctorId(): ?int { return $this->prescribedByDoctorId; }
    public function getMedication(): ?string { return $this->medication; }
    public function getDosage(): ?string { return $this->dosage; }
    public function getFrequency(): ?string { return $this->frequency; }
    public function getInstructions(): ?string { return $this->instructions; }
    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function isActive(): bool { return $this->isActive; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setPrescribedByDoctorId(?int $prescribedByDoctorId): self { $this->prescribedByDoctorId = $prescribedByDoctorId; return $this; }
    public function setMedication(?string $medication): self { $this->medication = $medication; return $this; }
    public function setDosage(?string $dosage): self { $this->dosage = $dosage; return $this; }
    public function setFrequency(?string $frequency): self { $this->frequency = $frequency; return $this; }
    public function setInstructions(?string $instructions): self { $this->instructions = $instructions; return $this; }
    public function setStartDate(?\DateTimeInterface $startDate): self { $this->startDate = $startDate; return $this; }
    public function setEndDate(?\DateTimeInterface $endDate): self { $this->endDate = $endDate; return $this; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}
