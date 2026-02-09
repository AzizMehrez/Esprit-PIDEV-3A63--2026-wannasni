<?php

namespace App\Entity;

use App\Repository\HealthJournalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * HealthJournal Entity
 */
#[ORM\Entity(repositoryClass: HealthJournalRepository::class)]
class HealthJournal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $senior = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $bloodPressureSystolic = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $bloodPressureDiastolic = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $heartRate = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $temperature = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $weight = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?float $bloodSugar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }

    public function getSenior(): ?User { return $this->senior; }
    public function setSenior(?User $senior): self { $this->senior = $senior; return $this; }

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(?\DateTimeInterface $date): self { $this->date = $date; return $this; }

    public function getBloodPressureSystolic(): ?float { return $this->bloodPressureSystolic; }
    public function setBloodPressureSystolic(?float $bloodPressureSystolic): self { $this->bloodPressureSystolic = $bloodPressureSystolic; return $this; }

    public function getBloodPressureDiastolic(): ?float { return $this->bloodPressureDiastolic; }
    public function setBloodPressureDiastolic(?float $bloodPressureDiastolic): self { $this->bloodPressureDiastolic = $bloodPressureDiastolic; return $this; }

    public function getHeartRate(): ?float { return $this->heartRate; }
    public function setHeartRate(?float $heartRate): self { $this->heartRate = $heartRate; return $this; }

    public function getTemperature(): ?float { return $this->temperature; }
    public function setTemperature(?float $temperature): self { $this->temperature = $temperature; return $this; }

    public function getWeight(): ?float { return $this->weight; }
    public function setWeight(?float $weight): self { $this->weight = $weight; return $this; }

    public function getBloodSugar(): ?float { return $this->bloodSugar; }
    public function setBloodSugar(?float $bloodSugar): self { $this->bloodSugar = $bloodSugar; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }

    public function getBloodPressure(): ?string
    {
        if ($this->bloodPressureSystolic && $this->bloodPressureDiastolic) {
            return $this->bloodPressureSystolic . '/' . $this->bloodPressureDiastolic;
        }
        return null;
    }
}
