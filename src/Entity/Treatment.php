<?php

namespace App\Entity;

use App\Repository\TreatmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Treatment Entity
 */
#[ORM\Entity(repositoryClass: TreatmentRepository::class)]
class Treatment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $senior = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $prescribedByDoctor = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $medication = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $dosage = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $frequency = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructions = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column]
    private bool $isActive = true;

    // Getters and Setters
    public function getId(): ?int { return $this->id; }

    public function getSenior(): ?User { return $this->senior; }
    public function setSenior(?User $senior): self { $this->senior = $senior; return $this; }

    public function getPrescribedByDoctor(): ?User { return $this->prescribedByDoctor; }
    public function setPrescribedByDoctor(?User $prescribedByDoctor): self { $this->prescribedByDoctor = $prescribedByDoctor; return $this; }

    public function getMedication(): ?string { return $this->medication; }
    public function setMedication(?string $medication): self { $this->medication = $medication; return $this; }

    public function getDosage(): ?string { return $this->dosage; }
    public function setDosage(?string $dosage): self { $this->dosage = $dosage; return $this; }

    public function getFrequency(): ?string { return $this->frequency; }
    public function setFrequency(?string $frequency): self { $this->frequency = $frequency; return $this; }

    public function getInstructions(): ?string { return $this->instructions; }
    public function setInstructions(?string $instructions): self { $this->instructions = $instructions; return $this; }

    public function getStartDate(): ?\DateTimeInterface { return $this->startDate; }
    public function setStartDate(?\DateTimeInterface $startDate): self { $this->startDate = $startDate; return $this; }

    public function getEndDate(): ?\DateTimeInterface { return $this->endDate; }
    public function setEndDate(?\DateTimeInterface $endDate): self { $this->endDate = $endDate; return $this; }

    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
}
