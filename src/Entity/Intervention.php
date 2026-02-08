<?php

namespace App\Entity;

use App\Repository\InterventionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InterventionRepository::class)]
class Intervention
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $employeId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $typesServices = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $competences = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?float $tarifHoraire = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $zoneIntervention = null;

    #[ORM\Column(nullable: true)]
    private ?int $heuresTravail = null;

    #[ORM\Column(length: 20)]
    private string $statutActuel = 'en_attente';

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: "interventions")]
    private ?ServiceRequest $serviceRequest = null;

    // Added Logic Fields
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $technicienNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $technicienEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $technicienTelephone = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 20)]
    private string $paymentStatus = 'pending'; // pending, paid

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $paymentDate = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $paymentMethod = null; // 'online', 'cash', 'check'



    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdEmploye(): ?int
    {
        return $this->employeId;
    }
    public function setIdEmploye(?int $v): self
    {
        $this->employeId = $v;
        return $this;
    }

    public function getTypesServices(): ?string
    {
        return $this->typesServices;
    }
    public function setTypesServices(?string $v): self
    {
        $this->typesServices = $v;
        return $this;
    }

    public function getCompetences(): ?string
    {
        return $this->competences;
    }
    public function setCompetences(?string $v): self
    {
        $this->competences = $v;
        return $this;
    }

    public function getTarifHoraire(): ?float
    {
        return $this->tarifHoraire;
    }
    public function setTarifHoraire(?float $v): self
    {
        $this->tarifHoraire = $v;
        return $this;
    }

    public function getZoneIntervention(): ?string
    {
        return $this->zoneIntervention;
    }
    public function setZoneIntervention(?string $v): self
    {
        $this->zoneIntervention = $v;
        return $this;
    }

    public function getHeuresTravail(): ?int
    {
        return $this->heuresTravail;
    }
    public function setHeuresTravail(?int $v): self
    {
        $this->heuresTravail = $v;
        return $this;
    }

    public function getStatutActuel(): string
    {
        return $this->statutActuel;
    }
    public function setStatutActuel(string $v): self
    {
        $this->statutActuel = $v;
        return $this;
    }

    public function getServiceRequest(): ?ServiceRequest
    {
        return $this->serviceRequest;
    }
    public function setServiceRequest(?ServiceRequest $s): self
    {
        $this->serviceRequest = $s;
        return $this;
    }

    public function getTechnicienNom(): ?string
    {
        return $this->technicienNom;
    }
    public function setTechnicienNom(?string $v): self
    {
        $this->technicienNom = $v;
        return $this;
    }

    public function getTechnicienEmail(): ?string
    {
        return $this->technicienEmail;
    }
    public function setTechnicienEmail(?string $v): self
    {
        $this->technicienEmail = $v;
        return $this;
    }

    public function getTechnicienTelephone(): ?string
    {
        return $this->technicienTelephone;
    }
    public function setTechnicienTelephone(?string $v): self
    {
        $this->technicienTelephone = $v;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }
    public function setNotes(?string $v): self
    {
        $this->notes = $v;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }
    public function setDateCreation(?\DateTimeInterface $v): self
    {
        $this->dateCreation = $v;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }
    public function setDateDebut(?\DateTimeInterface $v): self
    {
        $this->dateDebut = $v;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }
    public function setDateFin(?\DateTimeInterface $v): self
    {
        $this->dateFin = $v;
        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $v): self
    {
        $this->paymentStatus = $v;
        return $this;
    }

    public function getPaymentDate(): ?\DateTimeInterface
    {
        return $this->paymentDate;
    }

    public function setPaymentDate(?\DateTimeInterface $v): self
    {
        $this->paymentDate = $v;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $method): self
    {
        $this->paymentMethod = $method;
        return $this;
    }
}
