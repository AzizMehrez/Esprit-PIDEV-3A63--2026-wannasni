<?php

namespace App\Security;

use App\Service\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
        private NotificationService $notificationService
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        // Check if this is an admin panel access attempt
        if (str_starts_with($request->getPathInfo(), '/admin')) {
            $token = $this->tokenStorage->getToken();
            $user = $token ? $token->getUser() : null;
            
            // Log unauthorized admin access attempt
            if ($user && method_exists($user, 'getEmail')) {
                $userEmail = $user->getEmail();
                $userId = method_exists($user, 'getId') ? $user->getId() : null;
                $userName = $this->getUserDisplayName($user);
                
                // Log this security event
                $this->logger->warning('Unauthorized admin panel access attempt', [
                    'user_email' => $userEmail,
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'ip_address' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent'),
                    'requested_path' => $request->getPathInfo(),
                    'timestamp' => new \DateTimeImmutable(),
                ]);

                // Create admin notification
                $notificationMessage = sprintf(
                    'Tentative d\'accès non autorisé au panneau d\'administration par l\'utilisateur %s (%s) depuis l\'IP %s. Page demandée: %s',
                    $userName,
                    $userEmail,
                    $request->getClientIp(),
                    $request->getPathInfo()
                );
                
                $this->notificationService->create(
                    'security_alert',
                    $notificationMessage,
                    $userId
                );
            }

            // Render custom access denied template for admin panel
            return new Response(
                $this->twig->render('security/access_denied_admin.html.twig', [
                    'user' => $user,
                    'requested_path' => $request->getPathInfo(),
                ]),
                Response::HTTP_FORBIDDEN
            );
        }

        // For non-admin pages, just return a generic access denied response
        return new Response(
            $this->twig->render('security/access_denied.html.twig'),
            Response::HTTP_FORBIDDEN
        );
    }

    private function getUserDisplayName($user): string
    {
        if (method_exists($user, 'getFirstName') && method_exists($user, 'getLastName')) {
            $firstName = $user->getFirstName();
            $lastName = $user->getLastName();
            
            if ($firstName || $lastName) {
                return trim($firstName . ' ' . $lastName);
            }
        }

        if (method_exists($user, 'getEmail')) {
            return $user->getEmail();
        }

        return 'Utilisateur inconnu';
    }
}