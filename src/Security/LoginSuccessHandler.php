<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private RouterInterface $router
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        $locale = $request->getLocale() ?: 'fr';
        
        // Check if user has @wannasni.com email
        if ($user && method_exists($user, 'getEmail')) {
            $email = $user->getEmail();
            if ($email && str_ends_with(strtolower($email), '@wannasni.com')) {
                // Redirect directly to admin dashboard for wannasni.com users
                return new RedirectResponse($this->router->generate('admin_dashboard'));
            }
        }
        
        // Default redirect to user dashboard
        return new RedirectResponse($this->router->generate('app_dashboard', ['_locale' => $locale]));
    }
}
