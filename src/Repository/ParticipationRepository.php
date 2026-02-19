<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 *
 * @method Participation|null find($id, $lockMode = null, $lockVersion = null)
 * @method Participation|null findOneBy(array $criteria, array $orderBy = null)
 * @method Participation[]    findAll()
 * @method Participation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * Find participations by activity ID
     */
    public function findByActivity(int $activityId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('p.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count active participations for an activity
     */
    public function countActiveByActivity(int $activityId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.activityId = :activityId')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('activityId', $activityId)
            ->setParameter('statuses', ['registered', 'inscrit', 'attended'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find participations by senior
     */
    public function findBySenior(int $seniorId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.seniorId = :seniorId')
            ->setParameter('seniorId', $seniorId)
            ->orderBy('p.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count participations with feedback
     */
    public function countWithFeedback(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.feedbackRating IS NOT NULL OR p.feedbackComment IS NOT NULL OR p.feedback IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
