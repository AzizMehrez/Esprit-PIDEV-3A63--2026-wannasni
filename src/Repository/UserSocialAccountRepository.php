<?php

namespace App\Repository;

use App\Entity\UserSocialAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSocialAccount>
 */
class UserSocialAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSocialAccount::class);
    }

    /**
     * Find an existing social link by provider + provider user id.
     */
    public function findByProviderAndId(string $provider, string $providerUserId): ?UserSocialAccount
    {
        return $this->findOneBy([
            'provider' => $provider,
            'providerUserId' => $providerUserId,
        ]);
    }
}
