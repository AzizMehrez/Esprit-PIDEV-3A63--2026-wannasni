<?php

namespace App\Entity;

use App\Repository\DemandeRegimeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: DemandeRegimeRepository::class)]
class DemandeRegime
{
    // Constantes pour les statuts
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTE = 'accepté';
    public const STATUT_REFUSE = 'refusé';
    public const STATUT_TRAITE = 'traité';
    
    // Constantes pour les types de régime souhaité
    public const TYPE_NORMAL = 'normal';
    public const TYPE_DIABETIQUE = 'diabétique';
    public const TYPE_HYPO_SODE = 'hypo_sodé';
    public const TYPE_SANS_GLUTEN = 'sans_gluten';
    public const TYPE_CARDIOPROTECTEUR = 'cardioprotecteur';
    
    // Constantes pour les objectifs
    public const OBJECTIF_EQUILIBRE = 'équilibre';
    public const OBJECTIF_PERTE_POIDS = 'perte_poids';
    public const OBJECTIF_PRISE_MASSE = 'prise_masse';
    public const OBJECTIF_GESTION_MALADIE = 'gestion_maladie';

    /** @var int|null */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Image(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/gif'],
        mimeTypesMessage: 'Veuillez uploader une image JPEG, PNG ou GIF.'
    )]
    private ?string $codeBarresPhoto = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codeBarresNumero = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $produitAnalyse = [];

    #[ORM\Column]
    private ?int $seniorId = null;

    #[ORM\Column(nullable: true)]
    private ?int $nutritionnisteId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateDemande = null;

    #[ORM\Column(length: 20)]
    /* 
     * CONTRÔLE : Le statut doit être l'une des valeurs prédéfinies (en_attente, accepté, refuse, traite).
     * Cela empêche l'injection de statuts invalides via le web.
     */
    #[Assert\Choice(callback: 'getStatutChoices', message: 'Le statut choisi est invalide.')]
    private ?string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(length: 30)]
    /* 
     * CONTRÔLE : Le type de régime ne peut pas être vide.
     * CONTRÔLE : Il doit correspondre à la liste des types autorisés (normal, diabétique, etc.).
     */
    #[Assert\NotBlank(message: 'Veuillez choisir un type de régime.')]
    #[Assert\Choice(callback: 'getTypeRegimeSouhaiteChoices', message: 'Ce type de régime n\'est pas reconnu.')]
    private ?string $typeRegimeSouhaite = null;

    #[ORM\Column(length: 30)]
    /* 
     * CONTRÔLE : L'objectif principal ne peut pas être vide.
     * CONTRÔLE : Il doit correspondre à la liste des objectifs autorisés.
     */
    #[Assert\NotBlank(message: 'Veuillez spécifier un objectif.')]
    #[Assert\Choice(callback: 'getObjectifPrincipalChoices', message: 'Cet objectif n\'est pas valide.')]
    private ?string $objectifPrincipal = null;

    #[ORM\Column(length: 20, nullable: true)]
    /**
     * Numéro de téléphone du proche à prévenir en cas d'alerte.
     */
    private ?string $numeroProche = null;

    public function getNumeroProche(): ?string
    {
        return $this->numeroProche;
    }

    public function setNumeroProche(?string $numeroProche): static
    {
        $this->numeroProche = $numeroProche;
        return $this;
    }
    /* 
     * CONTRÔLE : Autorise uniquement les lettres (incluant accents), chiffres, espaces et ponctuation de base.
     * Cela protège contre l'insertion de scripts ou de caractères spéciaux non désirés.
     */
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9àâäéèêëïîôöùûüçÀÂÄÉÈÊËÏÎÔÖÙÛÜÇ\s\.,\?!()\'\"-]*$/u',
        message: 'Le champ allergies contient des caractères non autorisés.'
    )]
    private ?string $allergies = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    /* 
     * CONTRÔLE : Même règle de sécurité que pour les allergies (protection contre caractères spéciaux).
     */
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9àâäéèêëïîôöùûüçÀÂÄÉÈÊËÏÎÔÖÙÛÜÇ\s\.,\?!()\'\"-]*$/u',
        message: 'Le champ intolérances contient des caractères non autorisés.'
    )]
    private ?string $intolerances = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    /* 
     * CONTRÔLE : Même règle de sécurité (lettres, chiffres, espaces, ponctuation standard).
     */
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9àâäéèêëïîôöùûüçÀÂÄÉÈÊËÏÎÔÖÙÛÜÇ\s\.,\?!()\'\"-]*$/u',
        message: 'Le champ habitudes alimentaires contient des caractères non autorisés.'
    )]
    private ?string $habitudesAlimentaires = null;

    #[ORM\Column(nullable: false)]
    #[Assert\NotBlank(message: 'Le budget mensuel est obligatoire.')]
    #[Assert\Positive(message: 'Le budget mensuel doit être positif.')]
    #[Assert\GreaterThan(value: 30, message: 'Le budget mensuel doit être supérieur à 30 DT.')]
    private ?int $budgetMensuel = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive(message: 'Le poids doit être positif.')]
    #[Assert\Range(min: 20, max: 300, notInRangeMessage: 'Le poids doit être entre 20 et 300 kg.')]
    private ?float $poids = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\Positive(message: 'La taille doit être positive.')]
    #[Assert\Range(min: 50, max: 250, notInRangeMessage: 'La taille doit être entre 50 et 250 cm.')]
    private ?float $taille = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive(message: "L'âge doit être positif.")]
    #[Assert\Range(min: 18, max: 120, notInRangeMessage: "L'âge doit être entre 18 et 120 ans.")]
    private ?int $age = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pathologies = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $difficulteDeglutition = false;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Choice(choices: ['sedentaire', 'leger', 'modere', 'actif', 'tres_actif'], message: 'Niveau d\'activité invalide.')]
    private ?string $niveauActivite = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateTraitement = null;

    #[ORM\OneToMany(mappedBy: 'demande', targetEntity: RegimePrescrit::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $regimesPrescrits;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $nutritionniste = null;

    public function __construct()
    {
        $this->regimesPrescrits = new ArrayCollection();
        $this->dateDemande = new \DateTime();
        $this->produitAnalyse = [];
    }

    public function getCodeBarresPhoto(): ?string
    {
        return $this->codeBarresPhoto;
    }
    
    public function setCodeBarresPhoto(?string $codeBarresPhoto): static
    {
        $this->codeBarresPhoto = $codeBarresPhoto;
        return $this;
    }
    
    public function getCodeBarresNumero(): ?string
    {
        return $this->codeBarresNumero;
    }
    
    public function setCodeBarresNumero(?string $codeBarresNumero): static
    {
        $this->codeBarresNumero = $codeBarresNumero;
        return $this;
    }
    
    public function getProduitAnalyse(): array
    {
        return $this->produitAnalyse;
    }
    
    public function setProduitAnalyse(array $produitAnalyse): static
    {
        $this->produitAnalyse = $produitAnalyse;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeniorId(): ?int
    {
        return $this->seniorId;
    }

    public function setSeniorId(int $seniorId): static
    {
        $this->seniorId = $seniorId;
        return $this;
    }

    public function getNutritionnisteId(): ?int
    {
        return $this->nutritionnisteId;
    }

    public function setNutritionnisteId(int $nutritionnisteId): static
    {
        $this->nutritionnisteId = $nutritionnisteId;
        return $this;
    }

    public function getDateDemande(): ?\DateTimeInterface
    {
        return $this->dateDemande;
    }

    public function setDateDemande(\DateTimeInterface $dateDemande): static
    {
        $this->dateDemande = $dateDemande;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getTypeRegimeSouhaite(): ?string
    {
        return $this->typeRegimeSouhaite;
    }

    public function setTypeRegimeSouhaite(?string $typeRegimeSouhaite): static
    {
        $this->typeRegimeSouhaite = $typeRegimeSouhaite;
        return $this;
    }

    public function getObjectifPrincipal(): ?string
    {
        return $this->objectifPrincipal;
    }

    public function setObjectifPrincipal(?string $objectifPrincipal): static
    {
        $this->objectifPrincipal = $objectifPrincipal;
        return $this;
    }

    public function getAllergies(): ?string
    {
        return $this->allergies;
    }

    public function setAllergies(?string $allergies): static
    {
        $this->allergies = $allergies;
        return $this;
    }

    public function getIntolerances(): ?string
    {
        return $this->intolerances;
    }

    public function setIntolerances(?string $intolerances): static
    {
        $this->intolerances = $intolerances;
        return $this;
    }

    public function getHabitudesAlimentaires(): ?string
    {
        return $this->habitudesAlimentaires;
    }

    public function setHabitudesAlimentaires(?string $habitudesAlimentaires): static
    {
        $this->habitudesAlimentaires = $habitudesAlimentaires;
        return $this;
    }

    public function getBudgetMensuel(): ?int
    {
        return $this->budgetMensuel;
    }

    public function setBudgetMensuel(?int $budgetMensuel): static
    {
        $this->budgetMensuel = $budgetMensuel;
        return $this;
    }

    public function getPoids(): ?float { return $this->poids; }
    public function setPoids(?float $poids): static { $this->poids = $poids; return $this; }

    public function getTaille(): ?float { return $this->taille; }
    public function setTaille(?float $taille): static { $this->taille = $taille; return $this; }

    public function getAge(): ?int { return $this->age; }
    public function setAge(?int $age): static { $this->age = $age; return $this; }

    public function getPathologies(): ?string { return $this->pathologies; }
    public function setPathologies(?string $pathologies): static { $this->pathologies = $pathologies; return $this; }

    public function isDifficulteDeglutition(): ?bool { return $this->difficulteDeglutition; }
    public function setDifficulteDeglutition(?bool $difficulteDeglutition): static { $this->difficulteDeglutition = $difficulteDeglutition; return $this; }

    public function getNiveauActivite(): ?string { return $this->niveauActivite; }
    public function setNiveauActivite(?string $niveauActivite): static { $this->niveauActivite = $niveauActivite; return $this; }

    public function getImc(): ?float
    {
        if ($this->poids && $this->taille && $this->taille > 0) {
            $tailleM = $this->taille / 100;
            return round($this->poids / ($tailleM * $tailleM), 1);
        }
        return null;
    }

    public function getDateTraitement(): ?\DateTimeInterface
    {
        return $this->dateTraitement;
    }

    public function setDateTraitement(?\DateTimeInterface $dateTraitement): static
    {
        $this->dateTraitement = $dateTraitement;
        return $this;
    }

    /**
     * @return Collection<int, RegimePrescrit>
     */
    public function getRegimesPrescrits(): Collection
    {
        return $this->regimesPrescrits;
    }

    public function addRegimesPrescrit(RegimePrescrit $regimesPrescrit): static
    {
        if (!$this->regimesPrescrits->contains($regimesPrescrit)) {
            $this->regimesPrescrits->add($regimesPrescrit);
            $regimesPrescrit->setDemande($this);
        }

        return $this;
    }

    public function removeRegimesPrescrit(RegimePrescrit $regimesPrescrit): static
    {
        if ($this->regimesPrescrits->removeElement($regimesPrescrit)) {
            // set the owning side to null (unless already changed)
            if ($regimesPrescrit->getDemande() === $this) {
                $regimesPrescrit->setDemande(null);
            }
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getNutritionniste(): ?User
    {
        return $this->nutritionniste;
    }

    public function setNutritionniste(?User $nutritionniste): static
    {
        $this->nutritionniste = $nutritionniste;
        return $this;
    }

    public function getStatutChoices(): array
    {
        return [
            'En attente' => self::STATUT_EN_ATTENTE,
            'Accepté' => self::STATUT_ACCEPTE,
            'Refusé' => self::STATUT_REFUSE,
            'Traité' => self::STATUT_TRAITE,
        ];
    }

    public function getTypeRegimeSouhaiteChoices(): array
    {
        return [
            'Normal' => self::TYPE_NORMAL,
            'Diabétique' => self::TYPE_DIABETIQUE,
            'Hypo-sodé' => self::TYPE_HYPO_SODE,
            'Sans gluten' => self::TYPE_SANS_GLUTEN,
            'Cardioprotecteur' => self::TYPE_CARDIOPROTECTEUR,
        ];
    }

    public function getObjectifPrincipalChoices(): array
    {
        return [
            'Équilibre alimentaire' => self::OBJECTIF_EQUILIBRE,
            'Perte de poids' => self::OBJECTIF_PERTE_POIDS,
            'Prise de masse' => self::OBJECTIF_PRISE_MASSE,
            'Gestion de maladie' => self::OBJECTIF_GESTION_MALADIE,
        ];
    }

    public function __toString(): string
    {
        return sprintf('Demande #%d (Senior: %d)', $this->id, $this->seniorId);
    }
}
