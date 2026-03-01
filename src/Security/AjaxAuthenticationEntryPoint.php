<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Returns a JSON 401 response for AJAX requests instead of redirecting to the login page.
 * For non-AJAX requests, redirects to the login page as usual.
 */
class AjaxAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private RouterInterface $router
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // For AJAX / fetch requests, return JSON 401
        if ($request->isXmlHttpRequest() || $request->headers->get('Content-Type') === 'application/json') {
            return new JsonResponse([
                'error' => 'Votre session a expiré. Veuillez vous reconnecter.',
                'login_url' => $this->router->generate('app_login', ['_locale' => $request->getLocale() ?: 'fr']),
            ], Response::HTTP_UNAUTHORIZED);
        }

        // For normal requests, redirect to login page
        $locale = $request->getLocale() ?: 'fr';
        return new RedirectResponse($this->router->generate('app_login', ['_locale' => $locale]));
    }
}
