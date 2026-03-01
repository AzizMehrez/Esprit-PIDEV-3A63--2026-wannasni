<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Service\SocialAuthService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Handles OAuth "connect" (redirect to provider) and "callback" (return from provider)
 * for Google, GitHub, and X (Twitter).
 *
 * When the provider email already exists locally, the user is redirected to a
 * confirmation page before the social account is linked.
 */
class SocialAuthController extends AbstractController
{
    public function __construct(
        private SocialAuthService $socialAuth,
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {}

    // ────────────────────────────────────────────────────────────────
    //  GOOGLE
    // ────────────────────────────────────────────────────────────────

    #[Route(
        path: '/{_locale}/connect/google',
        name: 'connect_google_start',
        requirements: ['_locale' => 'fr|en|ar'],
    )]
    public function connectGoogleStart(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('google')
            ->redirect(['openid', 'email', 'profile'], []);
    }

    #[Route(
        path: '/{_locale}/connect/google/check',
        name: 'connect_google_check',
        requirements: ['_locale' => 'fr|en|ar'],
    )]
    public function connectGoogleCheck(Request $request, ClientRegistry $clientRegistry): Response
    {
        try {
            $client = $clientRegistry->getClient('google');
            /** @var \League\OAuth2\Client\Provider\GoogleUser $googleUser */
            $googleUser = $client->fetchUser();

            return $this->handleOAuthCallback(
                request: $request,
                provider: 'google',
                providerUserId: $googleUser->getId(),
                email: $googleUser->getEmail(),
                firstName: $googleUser->getFirstName(),
                lastName: $googleUser->getLastName(),
                displayName: $googleUser->getName(),
                avatarUrl: $googleUser->getAvatar(),
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion avec Google : ' . $e->getMessage());
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  GITHUB
    // ────────────────────────────────────────────────────────────────

    #[Route(
        path: '/{_locale}/connect/github',
        name: 'connect_github_start',
        requirements: ['_locale' => 'fr|en|ar'],
    )]
    public function connectGithubStart(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('github')
            ->redirect(['user:email'], []);
    }

    #[Route(
        path: '/{_locale}/connect/github/check',
        name: 'connect_github_check',
        requirements: ['_locale' => 'fr|en|ar'],
    )]
    public function connectGithubCheck(Request $request, ClientRegistry $clientRegistry): Response
    {
        try {
            $client = $clientRegistry->getClient('github');
            /** @var \League\OAuth2\Client\Provider\GithubResourceOwner $ghUser */
            $ghUser = $client->fetchUser();

            // GitHub may not expose email directly; fall back to nickname-based placeholder
            $email = $ghUser->getEmail();
            $name = $ghUser->getName() ?? $ghUser->getNickname() ?? '';

            // Split full name into first / last
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0] ?? null;
            $lastName = $parts[1] ?? null;

            return $this->handleOAuthCallback(
                request: $request,
                provider: 'github',
                providerUserId: (string) $ghUser->getId(),
                email: $email,
                firstName: $firstName,
                lastName: $lastName,
                displayName: $name,
                avatarUrl: $ghUser->toArray()['avatar_url'] ?? null,
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion avec GitHub : ' . $e->getMessage());
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  X (Twitter) — hidden until credentials are configured
    // ────────────────────────────────────────────────────────────────

    #[Route(
        path: '/{_locale}/connect/x',
        name: 'connect_x_start',
        requirements: ['_locale' => 'fr|en|ar'],
    )]
    public function connectXStart(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('x_twitter')
            ->redirect(['tweet.read', 'users.read'], []);
    }

    #[Route(
        path: '/{_locale}/connect/x/check',
        name: 'connect_x_check',
        requirements: ['_locale' => 'fr|en|ar'],
    )]
    public function connectXCheck(Request $request, ClientRegistry $clientRegistry): Response
    {
        try {
            $client = $clientRegistry->getClient('x_twitter');
            $xUser = $client->fetchUser();
            $data = $xUser->toArray();

            return $this->handleOAuthCallback(
                request: $request,
                provider: 'x',
                providerUserId: (string) ($data['id'] ?? $xUser->getId()),
                email: $data['email'] ?? null,
                firstName: $data['name'] ?? null,
                lastName: null,
                displayName: $data['name'] ?? $data['username'] ?? null,
                avatarUrl: $data['profile_image_url'] ?? null,
            );
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion avec X : ' . $e->getMessage());
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  CONFIRM LINK (existing local email matches)
    // ────────────────────────────────────────────────────────────────

    #[Route(
        path: '/{_locale}/connect/confirm-link',
        name: 'connect_confirm_link',
        requirements: ['_locale' => 'fr|en|ar'],
        methods: ['GET', 'POST'],
    )]
    public function confirmLink(Request $request): Response
    {
        $session = $request->getSession();
        $pending = $session->get('_social_pending_link');

        if (!$pending) {
            $this->addFlash('error', 'Aucune demande de liaison en attente.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $pending['email']]);
        if (!$user) {
            $session->remove('_social_pending_link');
            $this->addFlash('error', 'Compte introuvable.');
            return $this->redirectToRoute('app_login', ['_locale' => $request->getLocale()]);
        }

        // POST = user confirmed the link
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('confirm_social_link', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('connect_confirm_link', ['_locale' => $request->getLocale()]);
            }

            // Create the social link
            $this->socialAuth->linkSocialAccount(
                $user,
                $pending['provider'],
                $pending['providerUserId'],
                $pending['providerEmail'] ?? null,
                $pending['displayName'] ?? null,
                $pending['avatarUrl'] ?? null,
            );

            $session->remove('_social_pending_link');

            // Log the user in
            $this->authenticateUser($user, $request);

            $this->addFlash('success', 'Votre compte ' . ucfirst($pending['provider']) . ' a été lié avec succès !');
            return $this->redirectAfterLogin($user, $request->getLocale());
        }

        // GET = show confirmation page
        return $this->render('front/social_link_confirm.html.twig', [
            'provider' => $pending['provider'],
            'providerDisplayName' => $pending['displayName'] ?? '',
            'existingEmail' => $pending['email'],
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  SHARED LOGIC
    // ────────────────────────────────────────────────────────────────

    private function handleOAuthCallback(
        Request $request,
        string $provider,
        string $providerUserId,
        ?string $email,
        ?string $firstName,
        ?string $lastName,
        ?string $displayName,
        ?string $avatarUrl,
    ): Response {
        $locale = $request->getLocale() ?: 'fr';

        // 1) Already linked? → log in directly
        $existingUser = $this->socialAuth->findLinkedUser($provider, $providerUserId);
        if ($existingUser) {
            // Check account status
            if ($existingUser->getStatus() !== 'active') {
                $this->addFlash('error', 'Votre compte a été suspendu par l\'administrateur.');
                return $this->redirectToRoute('app_login', ['_locale' => $locale]);
            }
            $this->authenticateUser($existingUser, $request);
            return $this->redirectAfterLogin($existingUser, $locale);
        }

        // 2) Email matches an existing local account? → ask for confirmation
        if ($email) {
            $localUser = $this->socialAuth->findUserByEmail($email);
            if ($localUser) {
                // Store pending link data in session
                $request->getSession()->set('_social_pending_link', [
                    'provider' => $provider,
                    'providerUserId' => $providerUserId,
                    'email' => $email,
                    'providerEmail' => $email,
                    'displayName' => $displayName,
                    'avatarUrl' => $avatarUrl,
                ]);

                return $this->redirectToRoute('connect_confirm_link', ['_locale' => $locale]);
            }
        }

        // 3) No match at all → create new user + link
        if (!$email) {
            $this->addFlash('error', 'Impossible de récupérer votre email depuis ' . ucfirst($provider) . '. Veuillez utiliser l\'inscription classique.');
            return $this->redirectToRoute('app_register', ['_locale' => $locale]);
        }

        // Block @wannasni.com domain
        if (str_ends_with(strtolower($email), '@wannasni.com')) {
            $this->addFlash('error', 'Le domaine @wannasni.com est réservé aux administrateurs.');
            return $this->redirectToRoute('app_login', ['_locale' => $locale]);
        }

        $newUser = $this->socialAuth->createUserFromSocial($email, $firstName, $lastName, $avatarUrl);
        $this->socialAuth->linkSocialAccount($newUser, $provider, $providerUserId, $email, $displayName, $avatarUrl);

        $this->authenticateUser($newUser, $request);

        $this->addFlash('success', 'Votre compte a été créé avec succès via ' . ucfirst($provider) . ' !');
        return $this->redirectAfterLogin($newUser, $locale);
    }

    /**
     * Programmatically authenticate a user (same approach as FaceID login).
     */
    private function authenticateUser(User $user, Request $request): void
    {
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $this->tokenStorage->setToken($token);

        // Store token in session so Symfony firewall picks it up
        $request->getSession()->set('_security_main', serialize($token));

        // Update last login
        $user->setLastLoginAt(new \DateTime());
        $this->em->flush();
    }

    /**
     * Redirect after successful social login — mirrors LoginSuccessHandler logic.
     */
    private function redirectAfterLogin(User $user, string $locale): Response
    {
        if ($user->getEmail() && str_ends_with(strtolower($user->getEmail()), '@wannasni.com')) {
            return $this->redirectToRoute('admin_dashboard');
        }
        return $this->redirectToRoute('app_dashboard', ['_locale' => $locale]);
    }
}
