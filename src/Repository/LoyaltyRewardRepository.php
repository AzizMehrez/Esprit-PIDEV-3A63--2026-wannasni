<?php

namespace App\Repository;

use App\Entity\LoyaltyReward;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoyaltyReward>
 */
class LoyaltyRewardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoyaltyReward::class);
    }

    /**
     * Get available (non-expired, non-redeemed) rewards for a senior
     */
    public function findAvailableForSenior(User $senior): array
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.senior = :senior')
            ->andWhere('lr.status = :status')
            ->andWhere('lr.expiresAt IS NULL OR lr.expiresAt > :now')
            ->setParameter('senior', $senior)
            ->setParameter('status', LoyaltyReward::STATUS_AVAILABLE)
            ->setParameter('now', new \DateTime())
            ->orderBy('lr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get redeemed rewards for a senior
     */
    public function findRedeemedForSenior(User $senior): array
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.senior = :senior')
            ->andWhere('lr.status = :status')
            ->setParameter('senior', $senior)
            ->setParameter('status', LoyaltyReward::STATUS_REDEEMED)
            ->orderBy('lr.redeemedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all rewards for a senior (for history)
     */
    public function findAllForSenior(User $senior): array
    {
        return $this->createQueryBuilder('lr')
            ->where('lr.senior = :senior')
            ->setParameter('senior', $senior)
            ->orderBy('lr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count available rewards for a senior
     */
    public function countAvailableForSenior(User $senior): int
    {
        $result = $this->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->where('lr.senior = :senior')
            ->andWhere('lr.status = :status')
            ->andWhere('lr.expiresAt IS NULL OR lr.expiresAt > :now')
            ->setParameter('senior', $senior)
            ->setParameter('status', LoyaltyReward::STATUS_AVAILABLE)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Expire old rewards
     */
    public function expireOldRewards(): int
    {
        return $this->createQueryBuilder('lr')
            ->update()
            ->set('lr.status', ':expired')
            ->where('lr.status = :available')
            ->andWhere('lr.expiresAt IS NOT NULL')
            ->andWhere('lr.expiresAt < :now')
            ->setParameter('expired', LoyaltyReward::STATUS_EXPIRED)
            ->setParameter('available', LoyaltyReward::STATUS_AVAILABLE)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->execute();
    }

    /**
     * Admin: get all rewards with stats
     */
    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('lr');

        $total = (int) $qb->select('COUNT(lr.id)')
            ->getQuery()->getSingleScalarResult();

        $redeemed = (int) $this->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->where('lr.status = :status')
            ->setParameter('status', LoyaltyReward::STATUS_REDEEMED)
            ->getQuery()->getSingleScalarResult();

        $available = (int) $this->createQueryBuilder('lr')
            ->select('COUNT(lr.id)')
            ->where('lr.status = :status')
            ->setParameter('status', LoyaltyReward::STATUS_AVAILABLE)
            ->getQuery()->getSingleScalarResult();

        return [
            'total' => $total,
            'redeemed' => $redeemed,
            'available' => $available,
            'redemptionRate' => $total > 0 ? round(($redeemed / $total) * 100, 1) : 0,
        ];
    }
}
