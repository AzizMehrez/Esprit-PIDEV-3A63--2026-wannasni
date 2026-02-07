<?php

namespace App\Entity;

use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\Table(name: 'service_request')]
class ServiceRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: "Le numéro de téléphone est obligatoire.")]
    #[Assert\Length(min: 8, max: 20, minMessage: "Le numéro doit faire au moins 8 caractères.")]
    private ?string $seniorTelephone = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'email '{{ value }}' n'est pas valide.")]
    #[Assert\Regex(
        pattern: "/@gmail\.com$/",
        message: "L'email doit être une adresse Gmail (@gmail.com)."
    )]
    private ?string $seniorEmail = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le type de service est obligatoire.")]
    private ?string $typeService = null;

    #[ORM\Column(type: "text")]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    private ?string $adresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "La ville est obligatoire.")]
    private ?string $ville = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\NotBlank(message: "Le code postal est obligatoire.")]
    private ?string $codePostal = null;

    #[ORM\Column(length: 50)]
    private ?string $niveauUrgence = 'normale';

    #[ORM\Column(type: "datetime", nullable: true)]
    #[Assert\GreaterThan("today", message: "La date souhaitée doit être dans le futur.")]
    private ?\DateTimeInterface $dateSouhaitee = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: "Le budget minimum doit être positif.")]
    private ?float $budgetMinimum = null;

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: "Le budget maximum doit être positif.")]
    #[Assert\GreaterThan(propertyPath: "budgetMinimum", message: "Le budget maximum doit être supérieur au budget minimum.")]
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

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeniorTelephone(): ?string
    {
        return $this->seniorTelephone;
    }
    public function setSeniorTelephone(?string $v): self
    {
        $this->seniorTelephone = $v;
        return $this;
    }

    public function getSeniorEmail(): ?string
    {
        return $this->seniorEmail;
    }
    public function setSeniorEmail(?string $v): self
    {
        $this->seniorEmail = $v;
        return $this;
    }

    public function getTypeService(): ?string
    {
        return $this->typeService;
    }
    public function setTypeService(string $v): self
    {
        $this->typeService = $v;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(string $v): self
    {
        $this->description = $v;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }
    public function setAdresse(?string $v): self
    {
        $this->adresse = $v;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }
    public function setVille(?string $v): self
    {
        $this->ville = $v;
        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }
    public function setCodePostal(?string $v): self
    {
        $this->codePostal = $v;
        return $this;
    }

    public function getNiveauUrgence(): ?string
    {
        return $this->niveauUrgence;
    }
    public function setNiveauUrgence(string $v): self
    {
        $this->niveauUrgence = $v;
        return $this;
    }

    public function getDateSouhaitee(): ?\DateTimeInterface
    {
        return $this->dateSouhaitee;
    }
    public function setDateSouhaitee(?\DateTimeInterface $v): self
    {
        $this->dateSouhaitee = $v;
        return $this;
    }

    public function getBudgetMinimum(): ?float
    {
        return $this->budgetMinimum;
    }
    public function setBudgetMinimum(?float $v): self
    {
        $this->budgetMinimum = $v;
        return $this;
    }

    public function getBudgetMaximum(): ?float
    {
        return $this->budgetMaximum;
    }
    public function setBudgetMaximum(?float $v): self
    {
        $this->budgetMaximum = $v;
        return $this;
    }

    public function isNotifierProches(): bool
    {
        return $this->notifierProches;
    }
    public function setNotifierProches(bool $v): self
    {
        $this->notifierProches = $v;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }
    public function setStatut(string $v): self
    {
        $this->statut = $v;
        return $this;
    }

    public function getTechnicienId(): ?int
    {
        return $this->technicienId;
    }
    public function setTechnicienId(?int $v): self
    {
        $this->technicienId = $v;
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

    public function getNotesAdmin(): ?string
    {
        return $this->notesAdmin;
    }
    public function setNotesAdmin(?string $v): self
    {
        $this->notesAdmin = $v;
        return $this;
    }

    public function getDateAssignation(): ?\DateTimeInterface
    {
        return $this->dateAssignation;
    }
    public function setDateAssignation(?\DateTimeInterface $v): self
    {
        $this->dateAssignation = $v;
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

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

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
