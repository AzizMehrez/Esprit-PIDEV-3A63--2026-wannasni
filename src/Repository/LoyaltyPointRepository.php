<?php

namespace App\Repository;

use App\Entity\LoyaltyPoint;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoyaltyPoint>
 */
class LoyaltyPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoyaltyPoint::class);
    }

    /**
     * Get total points balance for a senior (earned - redeemed)
     */
    public function getTotalPoints(User $senior): int
    {
        $result = $this->createQueryBuilder('lp')
            ->select('SUM(lp.points) as total')
            ->where('lp.senior = :senior')
            ->andWhere('lp.expiresAt IS NULL OR lp.expiresAt > :now')
            ->setParameter('senior', $senior)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get points history for a senior
     */
    public function getHistory(User $senior, int $limit = 20): array
    {
        return $this->createQueryBuilder('lp')
            ->where('lp.senior = :senior')
            ->setParameter('senior', $senior)
            ->orderBy('lp.earnedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total points earned this month
     */
    public function getMonthlyEarned(User $senior): int
    {
        $startOfMonth = new \DateTime('first day of this month midnight');

        $result = $this->createQueryBuilder('lp')
            ->select('SUM(lp.points) as total')
            ->where('lp.senior = :senior')
            ->andWhere('lp.points > 0')
            ->andWhere('lp.earnedAt >= :start')
            ->setParameter('senior', $senior)
            ->setParameter('start', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Count total interventions that earned points
     */
    public function countInterventionPoints(User $senior): int
    {
        $result = $this->createQueryBuilder('lp')
            ->select('COUNT(lp.id)')
            ->where('lp.senior = :senior')
            ->andWhere('lp.source = :source')
            ->setParameter('senior', $senior)
            ->setParameter('source', LoyaltyPoint::SOURCE_INTERVENTION)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get points by source type
     */
    public function getPointsBySource(User $senior): array
    {
        return $this->createQueryBuilder('lp')
            ->select('lp.source, SUM(lp.points) as total')
            ->where('lp.senior = :senior')
            ->andWhere('lp.points > 0')
            ->groupBy('lp.source')
            ->setParameter('senior', $senior)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get leaderboard - top seniors by points
     */
    public function getLeaderboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('lp')
            ->select('IDENTITY(lp.senior) as seniorId, SUM(lp.points) as totalPoints')
            ->groupBy('lp.senior')
            ->orderBy('totalPoints', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
