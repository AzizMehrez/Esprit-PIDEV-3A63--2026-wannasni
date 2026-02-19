<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\UserSocialAccount;
use App\Repository\UserSocialAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Handles social login business logic:
 *  - find existing social account link
 *  - find existing user by email
 *  - create new user from social profile
 *  - link social identity to user
 */
class SocialAuthService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserSocialAccountRepository $socialRepo,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * Try to find a User already linked to this provider+id.
     */
    public function findLinkedUser(string $provider, string $providerUserId): ?User
    {
        $social = $this->socialRepo->findByProviderAndId($provider, $providerUserId);
        if ($social) {
            $social->setLastUsedAt(new \DateTime());
            $this->em->flush();
            return $social->getUser();
        }
        return null;
    }

    /**
     * Find an existing local User whose email matches the provider email.
     */
    public function findUserByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /**
     * Create a brand-new User from the social provider profile.
     * Password is set to a secure random hash (no local login until they set one).
     */
    public function createUserFromSocial(
        string $email,
        ?string $firstName,
        ?string $lastName,
        ?string $avatarUrl,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles(['ROLE_USER']);
        $user->setUserDomain('role.senior');
        $user->setStatus('active');

        if ($avatarUrl) {
            $user->setImageProfil($avatarUrl);
        }

        // Set a non-login random password (user has no local password at this point)
        $randomPassword = bin2hex(random_bytes(32));
        $hash = $this->passwordHasher->hashPassword($user, $randomPassword);
        $user->setPassword($hash);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Create a UserSocialAccount link between a User and a provider.
     */
    public function linkSocialAccount(
        User $user,
        string $provider,
        string $providerUserId,
        ?string $providerEmail = null,
        ?string $displayName = null,
        ?string $avatarUrl = null,
    ): UserSocialAccount {
        $social = new UserSocialAccount();
        $social->setUser($user);
        $social->setProvider($provider);
        $social->setProviderUserId($providerUserId);
        $social->setProviderEmail($providerEmail);
        $social->setProviderDisplayName($displayName);
        $social->setAvatarUrl($avatarUrl);
        $social->setLastUsedAt(new \DateTime());

        $this->em->persist($social);
        $this->em->flush();

        return $social;
    }
}
