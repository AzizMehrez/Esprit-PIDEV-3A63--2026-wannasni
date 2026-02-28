<?php

namespace App\Entity;

use App\Repository\UserSocialAccountRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Stores OAuth provider links for social login (Google, GitHub, X/Twitter).
 * Each user may have multiple providers; each provider+providerUserId is unique.
 */
#[ORM\Entity(repositoryClass: UserSocialAccountRepository::class)]
#[ORM\Table(name: 'user_social_account')]
#[ORM\UniqueConstraint(name: 'uniq_provider_user', columns: ['provider', 'provider_user_id'])]
class UserSocialAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Provider name: 'google', 'github', 'x' */
    #[ORM\Column(type: 'string', length: 30)]
    private string $provider;

    /** The unique user id from the OAuth provider */
    #[ORM\Column(type: 'string', length: 255)]
    private string $providerUserId;

    /** Provider email (informational, may differ from local email) */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerEmail = null;

    /** Display name from provider */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerDisplayName = null;

    /** Avatar URL from provider */
    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $avatarUrl = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $linkedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastUsedAt = null;

    public function __construct()
    {
        $this->linkedAt = new \DateTime();
    }

    // ── Getters ──

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getProviderUserId(): string
    {
        return $this->providerUserId;
    }

    public function getProviderEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function getProviderDisplayName(): ?string
    {
        return $this->providerDisplayName;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function getLinkedAt(): \DateTimeInterface
    {
        return $this->linkedAt;
    }

    public function getLastUsedAt(): ?\DateTimeInterface
    {
        return $this->lastUsedAt;
    }

    // ── Setters ──

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function setProviderUserId(string $providerUserId): self
    {
        $this->providerUserId = $providerUserId;
        return $this;
    }

    public function setProviderEmail(?string $providerEmail): self
    {
        $this->providerEmail = $providerEmail;
        return $this;
    }

    public function setProviderDisplayName(?string $providerDisplayName): self
    {
        $this->providerDisplayName = $providerDisplayName;
        return $this;
    }

    public function setAvatarUrl(?string $avatarUrl): self
    {
        $this->avatarUrl = $avatarUrl;
        return $this;
    }

    public function setLastUsedAt(?\DateTimeInterface $lastUsedAt): self
    {
        $this->lastUsedAt = $lastUsedAt;
        return $this;
    }
}
