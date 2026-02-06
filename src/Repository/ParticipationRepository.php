<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * Find participations by senior/user ID
     */
    public function findBySeniorId(int $seniorId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.seniorId = :seniorId')
            ->setParameter('seniorId', $seniorId)
            ->orderBy('p.registrationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find participations by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.registrationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search participations with filters
     */
    public function search(?int $activityId, ?string $status, ?int $participantId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.activity', 'a')
            ->addSelect('a');

        if ($activityId) {
            $qb->andWhere('a.id = :activityId')
               ->setParameter('activityId', $activityId);
        }

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        if ($participantId) {
            $qb->andWhere('p.seniorId = :participantId')
               ->setParameter('participantId', $participantId);
        }

        return $qb->orderBy('p.registrationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a senior is already registered for an activity
     */
    public function isRegistered(int $activityId, int $seniorId): bool
    {
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.id = :activityId')
            ->andWhere('p.seniorId = :seniorId')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('activityId', $activityId)
            ->setParameter('seniorId', $seniorId)
            ->setParameter('statuses', ['inscrit', 'présent'])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find all participations with activity relationships
     */
    public function findAllWithActivity(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.activity', 'a')
            ->addSelect('a')
            ->orderBy('p.registrationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find participation with activity relationship
     */
    public function findWithActivity(int $id): ?Participation
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.activity', 'a')
            ->addSelect('a')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count participations with feedback
     */
    public function countWithFeedback(): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.feedbackRating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
