<?php

namespace App\Entity;

use App\Repository\HealthJournalRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;

/**
 * HealthJournal Entity - Represents health vitals record for a senior
 */
#[ORM\Entity(repositoryClass: HealthJournalRepository::class)]
#[ORM\Table(name: 'health_journal')]
class HealthJournal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'senior_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $senior = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $humeur = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $qualiteSommeil = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $appetit = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $niveauDouleur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $symptomes = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $tensionArterielle = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $frequenceCardiaque = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $temperature = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $medicamentsPris = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $activitePhysique = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $hydratation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSenior(): ?User { return $this->senior; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function getHumeur(): ?string { return $this->humeur; }
    public function getQualiteSommeil(): ?string { return $this->qualiteSommeil; }
    public function getAppetit(): ?string { return $this->appetit; }
    public function getNiveauDouleur(): ?int { return $this->niveauDouleur; }
    public function getSymptomes(): ?string { return $this->symptomes; }
    public function getTensionArterielle(): ?string { return $this->tensionArterielle; }
    public function getFrequenceCardiaque(): ?int { return $this->frequenceCardiaque; }
    public function getTemperature(): ?float { return $this->temperature; }
    public function getMedicamentsPris(): ?string { return $this->medicamentsPris; }
    public function getActivitePhysique(): ?string { return $this->activitePhysique; }
    public function getHydratation(): ?string { return $this->hydratation; }
    public function getNotes(): ?string { return $this->notes; }

    // Setters
    public function setSenior(?User $senior): self { $this->senior = $senior; return $this; }
    public function setDate(?\DateTimeInterface $date): self { $this->date = $date; return $this; }
    public function setHumeur(?string $humeur): self { $this->humeur = $humeur; return $this; }
    public function setQualiteSommeil(?string $qualiteSommeil): self { $this->qualiteSommeil = $qualiteSommeil; return $this; }
    public function setAppetit(?string $appetit): self { $this->appetit = $appetit; return $this; }
    public function setNiveauDouleur(?int $niveauDouleur): self { $this->niveauDouleur = $niveauDouleur; return $this; }
    public function setSymptomes(?string $symptomes): self { $this->symptomes = $symptomes; return $this; }
    public function setTensionArterielle(?string $tensionArterielle): self { $this->tensionArterielle = $tensionArterielle; return $this; }
    public function setFrequenceCardiaque(?int $frequenceCardiaque): self { $this->frequenceCardiaque = $frequenceCardiaque; return $this; }
    public function setTemperature(?float $temperature): self { $this->temperature = $temperature; return $this; }
    public function setMedicamentsPris(?string $medicamentsPris): self { $this->medicamentsPris = $medicamentsPris; return $this; }
    public function setActivitePhysique(?string $activitePhysique): self { $this->activitePhysique = $activitePhysique; return $this; }
    public function setHydratation(?string $hydratation): self { $this->hydratation = $hydratation; return $this; }
    public function setNotes(?string $notes): self { $this->notes = $notes; return $this; }
}
