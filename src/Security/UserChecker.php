<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Checked before and after authentication.
 * Blocks users whose status is 'inactive' with a clear admin-suspension message.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if ($user->getStatus() === 'inactive') {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été suspendu par l\'administrateur. Veuillez contacter le support pour plus d\'informations.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Nothing needed after auth
    }
}
