<?php

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * Find all active activities
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.isActive = :active')

            ->setParameter('active', true)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming activities
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.startTime >= :now')
            ->andWhere('a.isActive = :active')

            ->setParameter('now', new \DateTime())
            ->setParameter('active', true)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find activities by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.type = :type')

            ->setParameter('type', $type)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search activities by query
     */
    public function search(?string $query, ?string $type, ?string $status): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($query) {
            $qb->andWhere('a.title LIKE :query OR a.description LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($type) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }

        if ($status === 'active') {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        } elseif ($status === 'inactive') {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', false);
        }

        return $qb->orderBy('a.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
