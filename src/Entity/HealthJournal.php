<?php

namespace App\Entity;

/**
 * HealthJournal Entity - Represents health vitals record for a senior
 */
class HealthJournal
{
    private ?int $id = null;
    private ?int $seniorId = null;
    private ?\DateTimeInterface $date = null;
    private ?float $bloodPressureSystolic = null;
    private ?float $bloodPressureDiastolic = null;
    private ?float $heartRate = null;
    private ?float $temperature = null;
    private ?float $weight = null;
    private ?float $bloodSugar = null;
    private ?string $notes = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSeniorId(): ?int { return $this->seniorId; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function getBloodPressureSystolic(): ?float { return $this->bloodPressureSystolic; }
    public function getBloodPressureDiastolic(): ?float { return $this->bloodPressureDiastolic; }
    public function getHeartRate(): ?float { return $this->heartRate; }
    public function getTemperature(): ?float { return $this->temperature; }
    public function getWeight(): ?float { return $this->weight; }
    public function getBloodSugar(): ?float { return $this->bloodSugar; }
    public function getNotes(): ?string { return $this->notes; }

    // Setters
    public function setId(?int $id): self { $this->id = $id; return $this; }
    public function setSeniorId(?int $seniorId): self { $this->seniorId = $seniorId; return $this; }
    public function setDate(?\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function setBloodPressureSystolic(?float $bloodPressureSystolic): self { $this->bloodPressureSystolic = $bloodPressureSystolic; return $this; }
    public function setBloodPressureDiastolic(?float $bloodPressureDiastolic): self { $this->bloodPressureDiastolic = $bloodPressureDiastolic; return $this; }
    public function setHeartRate(?float $heartRate): self { $this->heartRate = $heartRate; return $this; }
    public function setTemperature(?float $temperature): self { $this->temperature = $temperature; return $this; }
    public function setWeight(?float $weight): self { $this->weight = $weight; return $this; }
    public function setBloodSugar(?float $bloodSugar): self { $this->bloodSugar = $bloodSugar; return $this; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    /**
     * Get blood pressure as formatted string
     */
    public function getBloodPressure(): ?string
    {
        if ($this->bloodPressureSystolic && $this->bloodPressureDiastolic) {
            return $this->bloodPressureSystolic . '/' . $this->bloodPressureDiastolic;
        }
        return null;
    }
}
