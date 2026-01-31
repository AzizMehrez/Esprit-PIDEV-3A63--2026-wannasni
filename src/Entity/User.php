<?php

namespace App\Entity;

/**
 * User Entity - Represents a user in the system
 * Pure data model with no business logic
 */
class User
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $password = null; // Hashed
    private array $roles = ['ROLE_USER'];
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $phone = null;
    private string $status = 'active'; // active, inactive, suspended
    private ?\DateTimeInterface $createdAt = null;
    private ?\DateTimeInterface $lastLoginAt = null;

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
        return $this->roles;
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
}
