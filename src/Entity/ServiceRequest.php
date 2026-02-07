<?php

namespace App\Entity;

use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\Table(name: 'service_request')]
class ServiceRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $seniorTelephone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $seniorEmail = null;

    #[ORM\Column(length: 100)]
    private ?string $typeService = null;

    #[ORM\Column(type: "text")]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(length: 50)]
    private ?string $niveauUrgence = 'normale';

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateSouhaitee = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?float $budgetMinimum = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?float $budgetMaximum = null;

    #[ORM\Column(type: "boolean")]
    private bool $notifierProches = false;

    #[ORM\Column(length: 50)]
    private string $statut = 'pending';

    #[ORM\Column(type: "datetime")]
    private \DateTimeInterface $createdAt;

    // ADMIN fields
    #[ORM\Column(type: "integer", nullable: true)]
    private ?int $technicienId = null;

    #[ORM\Column(type: "string", length: 100, nullable: true)]
    private ?string $technicienNom = null;

    #[ORM\Column(type: "text", nullable: true)]
    private ?string $notesAdmin = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateAssignation = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: "datetime", nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\OneToMany(mappedBy: "serviceRequest", targetEntity: Intervention::class)]
    private Collection $interventions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->interventions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getSeniorTelephone(): ?string { return $this->seniorTelephone; }
    public function setSeniorTelephone(?string $v): self { $this->seniorTelephone=$v; return $this; }

    public function getSeniorEmail(): ?string { return $this->seniorEmail; }
    public function setSeniorEmail(?string $v): self { $this->seniorEmail=$v; return $this; }

    public function getTypeService(): ?string { return $this->typeService; }
    public function setTypeService(string $v): self { $this->typeService=$v; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $v): self { $this->description=$v; return $this; }

    public function getAdresse(): ?string { return $this->adresse; }
    public function setAdresse(?string $v): self { $this->adresse=$v; return $this; }

    public function getVille(): ?string { return $this->ville; }
    public function setVille(?string $v): self { $this->ville=$v; return $this; }

    public function getCodePostal(): ?string { return $this->codePostal; }
    public function setCodePostal(?string $v): self { $this->codePostal=$v; return $this; }

    public function getNiveauUrgence(): ?string { return $this->niveauUrgence; }
    public function setNiveauUrgence(string $v): self { $this->niveauUrgence=$v; return $this; }

    public function getDateSouhaitee(): ?\DateTimeInterface { return $this->dateSouhaitee; }
    public function setDateSouhaitee(?\DateTimeInterface $v): self { $this->dateSouhaitee=$v; return $this; }

    public function getBudgetMinimum(): ?float { return $this->budgetMinimum; }
    public function setBudgetMinimum(?float $v): self { $this->budgetMinimum=$v; return $this; }

    public function getBudgetMaximum(): ?float { return $this->budgetMaximum; }
    public function setBudgetMaximum(?float $v): self { $this->budgetMaximum=$v; return $this; }

    public function isNotifierProches(): bool { return $this->notifierProches; }
    public function setNotifierProches(bool $v): self { $this->notifierProches=$v; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $v): self { $this->statut=$v; return $this; }

    public function getTechnicienId(): ?int { return $this->technicienId; }
    public function setTechnicienId(?int $v): self { $this->technicienId=$v; return $this; }

    public function getTechnicienNom(): ?string { return $this->technicienNom; }
    public function setTechnicienNom(?string $v): self { $this->technicienNom=$v; return $this; }

    public function getNotesAdmin(): ?string { return $this->notesAdmin; }
    public function setNotesAdmin(?string $v): self { $this->notesAdmin=$v; return $this; }

    public function getDateAssignation(): ?\DateTimeInterface { return $this->dateAssignation; }
    public function setDateAssignation(?\DateTimeInterface $v): self { $this->dateAssignation=$v; return $this; }

    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $v): self { $this->dateDebut=$v; return $this; }

    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $v): self { $this->dateFin=$v; return $this; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    /**
     * @return Collection<int, Intervention>
     */
    public function getInterventions(): Collection
    {
        return $this->interventions;
    }

    public function addIntervention(Intervention $intervention): self
    {
        if (!$this->interventions->contains($intervention)) {
            $this->interventions->add($intervention);
            $intervention->setServiceRequest($this);
        }
        return $this;
    }

    public function removeIntervention(Intervention $intervention): self
    {
        if ($this->interventions->removeElement($intervention)) {
            // set the owning side to null (unless already changed)
            if ($intervention->getServiceRequest() === $this) {
                $intervention->setServiceRequest(null);
            }
        }
        return $this;
    }
}
