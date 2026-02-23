<?php

namespace App\Controller\Api;

use App\Service\UserService;
use App\Exception\ValidationException;
use App\Exception\UnauthorizedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * UserController - API endpoints for user management
 * Handles HTTP requests and delegates to UserService
 */
#[Route('/api/users')]
class UserController extends AbstractController
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Login endpoint
     */
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $result = $this->userService->authenticate(
                $data['email'] ?? '',
                $data['password'] ?? ''
            );

            return $this->json($result);

        } catch (ValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ], 422);

        } catch (UnauthorizedException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => $e->getMessage()]
            ], 401);
        }
    }

    /**
     * Register new user
     */
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $user = $this->userService->registerUser($data);

            return $this->json([
                'success' => true,
                'user' => $user->toArray()
            ], 201);

        } catch (ValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ], 422);
        }
    }

    /**
     * Get current user profile
     */
    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        try {
            // In real implementation, get user from security token
            $userId = 1; // Mock user ID

            $profile = $this->userService->getUserProfile($userId);

            return $this->json([
                'success' => true,
                'user' => $profile->toArray()
            ]);

        } catch (ValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'NOT_FOUND', 'message' => $e->getMessage()]
            ], 404);
        }
    }

    /**
     * Update user profile
     */
    #[Route('/profile', name: 'api_profile_update', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $userId = 1; // Mock user ID

            $updated = $this->userService->updateProfile($userId, $data);

            return $this->json([
                'success' => true,
                'user' => $updated->toArray()
            ]);

        } catch (ValidationException $e) {
            return $this->json([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => $e->getMessage()]
            ], 422);
        }
    }
}
