<?php

namespace App\DTO;

use App\Entity\User;

/**
 * UserDTO - Data Transfer Object for User
 * Used for API responses to avoid exposing sensitive data
 */
class UserDTO
{
    public int $id;
    public string $email;
    public string $firstName;
    public string $lastName;
    public string $fullName;
    public array $roles;
    public string $status;
    public ?string $phone;
    public ?string $userDomain;
    public string $createdAt;
    public ?string $lastLoginAt;

    /**
     * Create DTO from User entity
     */
    public static function fromEntity(User $user): self
    {
        $dto = new self();
        $dto->id = $user->getId();
        $dto->email = $user->getEmail();
        $dto->firstName = $user->getFirstName() ?? '';
        $dto->lastName = $user->getLastName() ?? '';
        $dto->fullName = $user->getFullName();
        $dto->roles = $user->getRoles();
        $dto->status = $user->getStatus();
        $dto->phone = $user->getPhone();
        $dto->userDomain = $user->getUserDomain();
        $dto->createdAt = $user->getCreatedAt()?->format('Y-m-d H:i:s') ?? '';
        $dto->lastLoginAt = $user->getLastLoginAt()?->format('Y-m-d H:i:s');
        
        return $dto;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'roles' => $this->roles,
            'status' => $this->status,
            'phone' => $this->phone,
            'user_domain' => $this->userDomain,
            'created_at' => $this->createdAt,
            'last_login_at' => $this->lastLoginAt,
        ];
    }
}
