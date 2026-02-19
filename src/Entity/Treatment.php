<?php

namespace App\Entity;

use App\Repository\TreatmentRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

/**
 * Treatment Entity - Represents a prescribed treatment/medication
 */
#[ORM\Entity(repositoryClass: TreatmentRepository::class)]
#[ORM\Table(name: 'treatment')]
class Treatment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'senior_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $senior = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'docteur_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $docteur = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $datePrescription = null;

    #[ORM\Column(type: 'text')]
    private ?string $medicaments = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $posologie = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $frequence = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $instructions = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $renouvellements = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $statut = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $effetsSecondaires = null;

    public function __construct()
    {
        $this->datePrescription = new \DateTime();
        $this->dateDebut = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSenior(): ?User { return $this->senior; }
    public function getDocteur(): ?User { return $this->docteur; }
    public function getDatePrescription(): ?\DateTimeInterface { return $this->datePrescription; }
    public function getMedicaments(): ?string { return $this->medicaments; }
    public function getPosologie(): ?string { return $this->posologie; }
    public function getFrequence(): ?string { return $this->frequence; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function getInstructions(): ?string { return $this->instructions; }
    public function getRenouvellements(): ?int { return $this->renouvellements; }
    public function getStatut(): ?string { return $this->statut; }
    public function getEffetsSecondaires(): ?string { return $this->effetsSecondaires; }

    // Setters
    public function setSenior(?User $senior): self { $this->senior = $senior; return $this; }
    public function setDocteur(?User $docteur): self { $this->docteur = $docteur; return $this; }
    public function setDatePrescription(?\DateTimeInterface $datePrescription): self { $this->datePrescription = $datePrescription; return $this; }
    public function setMedicaments(?string $medicaments): self { $this->medicaments = $medicaments; return $this; }
    public function setPosologie(?string $posologie): self { $this->posologie = $posologie; return $this; }
    public function setFrequence(?string $frequence): self { $this->frequence = $frequence; return $this; }
    public function setDateDebut(?\DateTimeInterface $dateDebut): self { $this->dateDebut = $dateDebut; return $this; }
    public function setDateFin(?\DateTimeInterface $dateFin): self { $this->dateFin = $dateFin; return $this; }
    public function setInstructions(?string $instructions): self { $this->instructions = $instructions; return $this; }
    public function setRenouvellements(?int $renouvellements): self { $this->renouvellements = $renouvellements; return $this; }
    public function setStatut(?string $statut): self { $this->statut = $statut; return $this; }
    public function setEffetsSecondaires(?string $effetsSecondaires): self { $this->effetsSecondaires = $effetsSecondaires; return $this; }
}
