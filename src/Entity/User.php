<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * User Entity - Represents a user in the system
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $password = null; // Hashed — null for social-only accounts

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageProfil = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $codePostal = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'active'; // active, inactive, suspended

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiresAt = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $verificationCode = null;

    // Face ID fields for Python face_recognition integration
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $faceEncoding = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $faceImagePath = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $faceConsentAt = null;

    // Technician fields for intervention assignment
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $specialite = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $tarifHoraire = null;

    #[ORM\Column(type: 'boolean')]
    private bool $disponible = true;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $userDomain = null;

    // Networking profile fields
    #[ORM\Column(type: 'boolean')]
    private bool $profilePublic = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    // Verification fields
    #[ORM\Column(type: 'boolean')]
    private bool $isAccountVerified = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $verifiedAt = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $verificationBadgeType = null; // 'blue', 'purple'

    // Networking ban (soft-ban: read-only networking)
    #[ORM\Column(type: 'boolean')]
    private bool $isNetworkingBanned = false;

    // Networking moderation: strike counter for content violations
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $networkingStrikes = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastStrikeAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        
        // Auto-grant ROLE_ADMIN to @wannasni.com emails
        if ($this->email && str_ends_with(strtolower($this->email), '@wannasni.com')) {
            $roles[] = 'ROLE_ADMIN';
        }
        
        return array_unique($roles);
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getImageProfil(): ?string
    {
        return $this->imageProfil;
    }

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function getCodePostal(): ?string
    {
        return $this->codePostal;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    // Setters
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function addRole(string $role): self
    {
        if (!in_array($role, $this->roles)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function setImageProfil(?string $imageProfil): self
    {
        $this->imageProfil = $imageProfil;
        return $this;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): self
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function setCodePostal(?string $codePostal): self
    {
        $this->codePostal = $codePostal;
        return $this;
    }

    public function setPays(?string $pays): self
    {
        $this->pays = $pays;
        return $this;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeInterface $resetTokenExpiresAt): self
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function getVerificationCode(): ?string
    {
        return $this->verificationCode;
    }

    public function setVerificationCode(?string $verificationCode): self
    {
        $this->verificationCode = $verificationCode;
        return $this;
    }

    // Face ID getters and setters
    public function getFaceEncoding(): ?array
    {
        return $this->faceEncoding;
    }

    public function setFaceEncoding(?array $faceEncoding): self
    {
        $this->faceEncoding = $faceEncoding;
        return $this;
    }

    public function getFaceImagePath(): ?string
    {
        return $this->faceImagePath;
    }

    public function setFaceImagePath(?string $faceImagePath): self
    {
        $this->faceImagePath = $faceImagePath;
        return $this;
    }

    public function getFaceConsentAt(): ?\DateTimeInterface
    {
        return $this->faceConsentAt;
    }

    public function setFaceConsentAt(?\DateTimeInterface $faceConsentAt): self
    {
        $this->faceConsentAt = $faceConsentAt;
        return $this;
    }

    /**
     * Get full name
     */
    public function getFullName(): string
    {
        return trim(($this->firstName ?? '') . ' ' . ($this->lastName ?? ''));
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Clear temporary, sensitive data if stored
    }

    // Technician getters and setters
    public function getSpecialite(): ?string
    {
        return $this->specialite;
    }

    public function setSpecialite(?string $specialite): self
    {
        $this->specialite = $specialite;
        return $this;
    }

    public function getTarifHoraire(): ?string
    {
        return $this->tarifHoraire;
    }

    public function setTarifHoraire(?string $tarifHoraire): self
    {
        $this->tarifHoraire = $tarifHoraire;
        return $this;
    }

    public function isDisponible(): bool
    {
        return $this->disponible;
    }

    public function setDisponible(bool $disponible): self
    {
        $this->disponible = $disponible;
        return $this;
    }

    public function getUserDomain(): ?string
    {
        return $this->userDomain;
    }

    public function setUserDomain(?string $userDomain): self
    {
        $this->userDomain = $userDomain;
        return $this;
    }

    // Networking profile getters and setters
    public function isProfilePublic(): bool
    {
        return $this->profilePublic;
    }

    public function setProfilePublic(bool $profilePublic): self
    {
        $this->profilePublic = $profilePublic;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    // ── Verification getters/setters ──

    public function isAccountVerified(): bool
    {
        return $this->isAccountVerified;
    }

    public function setIsAccountVerified(bool $isAccountVerified): self
    {
        $this->isAccountVerified = $isAccountVerified;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeInterface $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }

    public function getVerificationBadgeType(): ?string
    {
        return $this->verificationBadgeType;
    }

    public function setVerificationBadgeType(?string $verificationBadgeType): self
    {
        $this->verificationBadgeType = $verificationBadgeType;
        return $this;
    }

    /**
     * Effective badge: purple for admins/@wannasni.com, blue for verified regulars.
     * Purple always overrides blue.
     */
    public function getEffectiveBadge(): ?string
    {
        // Admin or @wannasni.com domain => always purple
        if (in_array('ROLE_ADMIN', $this->getRoles()) ||
            ($this->email && str_ends_with(strtolower($this->email), '@wannasni.com'))) {
            return 'purple';
        }
        // Regular verified user => blue
        if ($this->isAccountVerified) {
            return 'blue';
        }
        return null;
    }

    // ── Networking ban getters/setters ──

    public function isNetworkingBanned(): bool
    {
        return $this->isNetworkingBanned;
    }

    public function setIsNetworkingBanned(bool $isNetworkingBanned): self
    {
        $this->isNetworkingBanned = $isNetworkingBanned;
        return $this;
    }

    // ── Networking moderation strikes ──

    public function getNetworkingStrikes(): int
    {
        return $this->networkingStrikes;
    }

    public function setNetworkingStrikes(int $strikes): self
    {
        $this->networkingStrikes = $strikes;
        return $this;
    }

    public function incrementNetworkingStrikes(): self
    {
        $this->networkingStrikes++;
        $this->lastStrikeAt = new \DateTime();
        return $this;
    }

    public function getLastStrikeAt(): ?\DateTimeInterface
    {
        return $this->lastStrikeAt;
    }

    public function setLastStrikeAt(?\DateTimeInterface $lastStrikeAt): self
    {
        $this->lastStrikeAt = $lastStrikeAt;
        return $this;
    }

    /**
     * Check if user profile is complete (has firstName, lastName, imageProfil)
     */
    public function isProfileComplete(): bool
    {
        return !empty($this->firstName) && !empty($this->lastName) && !empty($this->imageProfil);
    }
}
