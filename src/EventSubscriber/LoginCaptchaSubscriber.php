<?php

namespace App\EventSubscriber;

use App\Service\CaptchaService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Enforces captcha validation on every login attempt.
 * The captcha modal is shown client-side when user clicks "Connexion".
 *
 * Event priorities relative to Symfony internals:
 *   CsrfProtectionListener  → 512
 *   UserProviderListener    → 256
 *   ★ LoginCaptchaSubscriber → 128  (after user lookup, before credential check)
 *   CheckCredentialsListener → 0
 */
class LoginCaptchaSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CaptchaService $captchaService,
        private readonly RequestStack   $requestStack,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => ['onCheckPassport', 128],
            LoginSuccessEvent::class  => ['onLoginSuccess', 0],
        ];
    }

    /**
     * Always validate the submitted _captcha field BEFORE Symfony checks the password.
     */
    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $captchaInput = $request->request->get('_captcha', '');

        if (empty($captchaInput) || !$this->captchaService->validateCaptcha($captchaInput)) {
            throw new CustomUserMessageAuthenticationException('captcha.invalid');
        }
    }

    /**
     * On successful login, clear any captcha state.
     */
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->captchaService->resetFailedAttempts();
    }
}
