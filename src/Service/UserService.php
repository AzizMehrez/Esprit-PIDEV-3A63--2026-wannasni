<?php

namespace App\Service;

use App\Entity\User;
use App\DTO\UserDTO;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;

/**
 * UserService - Contains all business logic for user management
 */
class UserService
{
    /**
     * Authenticate user and return result
     */
    public function authenticate(string $email, string $password): array
    {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }

        // Find user by email (mock - no DB)
        $user = $this->findUserByEmail($email);

        if (!$user) {
            throw new UnauthorizedException('Invalid credentials');
        }

        // Check account status
        if ($user->getStatus() === 'suspended') {
            throw new UnauthorizedException('Account suspended');
        }

        if ($user->getStatus() === 'inactive') {
            throw new UnauthorizedException('Account inactive');
        }

        // Verify password (mock)
        if (!$this->verifyPassword($password, $user->getPassword())) {
            throw new UnauthorizedException('Invalid credentials');
        }

        // Update last login
        $user->setLastLoginAt(new \DateTime());

        // Generate token (mock)
        $token = $this->generateAuthToken($user);

        return [
            'success' => true,
            'token' => $token,
            'user' => UserDTO::fromEntity($user)->toArray(),
        ];
    }

    /**
     * Get user profile by ID
     */
    public function getUserProfile(int $userId): UserDTO
    {
        $user = $this->findUserById($userId);

        if (!$user) {
            throw new ValidationException('User not found');
        }

        return UserDTO::fromEntity($user);
    }

    /**
     * Update user profile
     */
    public function updateProfile(int $userId, array $data): UserDTO
    {
        $user = $this->findUserById($userId);

        if (!$user) {
            throw new ValidationException('User not found');
        }

        // Validate and update email
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('Invalid email format');
            }
            $user->setEmail($data['email']);
        }

        // Validate and update phone
        if (isset($data['phone'])) {
            if (!$this->isValidPhone($data['phone'])) {
                throw new ValidationException('Invalid phone number');
            }
            $user->setPhone($data['phone']);
        }

        // Update names
        if (isset($data['first_name'])) {
            $user->setFirstName($data['first_name']);
        }

        if (isset($data['last_name'])) {
            $user->setLastName($data['last_name']);
        }

        // Save changes (mock - no DB)

        return UserDTO::fromEntity($user);
    }

    /**
     * Check if user can access another user's data
     */
    public function canAccessUserData(User $accessor, int $targetUserId): bool
    {
        // Own data
        if ($accessor->getId() === $targetUserId) {
            return true;
        }

        // Admin access
        if ($accessor->hasRole('ROLE_ADMIN')) {
            return true;
        }

        // Doctor can access patient data
        if ($accessor->hasRole('ROLE_DOCTOR')) {
            return $this->isDoctorOfPatient($accessor->getId(), $targetUserId);
        }

        // Family can access connected senior data
        if ($accessor->hasRole('ROLE_FAMILY')) {
            return $this->isFamilyMember($accessor->getId(), $targetUserId);
        }

        return false;
    }

    /**
     * Register new user
     */
    public function registerUser(array $data): UserDTO
    {
        // Validate required fields
        if (empty($data['email'])) {
            throw new ValidationException('Email is required');
        }

        if (empty($data['password'])) {
            throw new ValidationException('Password is required');
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email format');
        }

        // Check password strength
        if (strlen($data['password']) < 8) {
            throw new ValidationException('Password must be at least 8 characters');
        }

        // Check if email already exists (mock)
        if ($this->emailExists($data['email'])) {
            throw new ValidationException('Email already registered');
        }

        // Create user
        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($this->hashPassword($data['password']));
        $user->setFirstName($data['first_name'] ?? null);
        $user->setLastName($data['last_name'] ?? null);
        $user->setPhone($data['phone'] ?? null);
        $user->setStatus('active');

        // Assign role based on registration type
        $role = $data['role'] ?? 'ROLE_SENIOR';
        if (in_array($role, ['ROLE_SENIOR', 'ROLE_FAMILY', 'ROLE_DOCTOR', 'ROLE_TECHNICIEN'])) {
            $user->addRole($role);
        }
        
        // Set user domain based on role
        $roleDomain = strtolower(str_replace('ROLE_', 'role.', $role));
        $user->setUserDomain($roleDomain);

        // Save user (mock - no DB)

        return UserDTO::fromEntity($user);
    }

    // =========================================================================
    // Private helper methods (mocked - no actual database)
    // =========================================================================

    private function findUserByEmail(string $email): ?User
    {
        // Mock: Return a test user for demonstration
        $user = new User();
        $user->setId(1);
        $user->setEmail($email);
        $user->setPassword($this->hashPassword('password123'));
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setStatus('active');
        $user->addRole('ROLE_SENIOR');
        return $user;
    }

    private function findUserById(int $id): ?User
    {
        // Mock implementation
        $user = new User();
        $user->setId($id);
        $user->setEmail('user@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setStatus('active');
        return $user;
    }

    private function verifyPassword(string $plain, string $hash): bool
    {
        // Mock: Always return true for demonstration
        return password_verify($plain, $hash) || $plain === 'password123';
    }

    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    private function generateAuthToken(User $user): string
    {
        // Mock: Generate a simple token
        return base64_encode(json_encode([
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'exp' => time() + 3600
        ]));
    }

    private function isValidPhone(string $phone): bool
    {
        return preg_match('/^[0-9+\-\s]{8,20}$/', $phone) === 1;
    }

    private function emailExists(string $email): bool
    {
        // Mock: Return false (email doesn't exist)
        return false;
    }

    private function isDoctorOfPatient(int $doctorId, int $patientId): bool
    {
        // Mock: Check doctor-patient relationship
        return true;
    }

    private function isFamilyMember(int $familyId, int $seniorId): bool
    {
        // Mock: Check family relationship
        return true;
    }
}
