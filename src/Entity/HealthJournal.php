<?php

namespace App\Entity;

use App\Repository\HealthJournalRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

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
    #[Assert\NotNull(message: 'La date est requise')]
    #[Assert\NotBlank(message: 'La date ne peut pas être vide')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Choice(choices: ['excellent', 'good', 'average', 'poor'], message: 'Humeur invalide')]
    private ?string $humeur = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Choice(choices: ['very_good', 'good', 'average', 'poor'], message: 'Qualité sommeil invalide')]
    private ?string $qualiteSommeil = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Choice(choices: ['normal', 'increased', 'decreased', 'absent'], message: 'Appétit invalide')]
    private ?string $appetit = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'Le niveau de douleur doit être entre 0 et 10')]
    private ?int $niveauDouleur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $symptomes = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $tensionArterielle = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 30, max: 200, notInRangeMessage: 'Fréquence cardiaque doit être entre 30 et 200 bpm')]
    private ?int $frequenceCardiaque = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Range(min: 35.0, max: 42.0, notInRangeMessage: 'Température doit être entre 35°C et 42°C')]
    private ?float $temperature = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $medicamentsPris = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $activitePhysique = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $hydratation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les notes ne doivent pas dépasser 1000 caractères')]
    private ?string $notes = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getSenior(): ?User { return $this->senior; }
    public function getDate(): \DateTimeInterface { return $this->date; }
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
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }
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
