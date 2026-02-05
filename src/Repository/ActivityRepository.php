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

    public function save(Activity $activity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($activity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Activity $activity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($activity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAll(): array
    {
        return $this->findBy([], ['startTime' => 'ASC']);
    }

    public function findUpcoming(): array
    {
        return $this->findBy(['isActive' => true], ['startTime' => 'ASC']);
    }

    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type], ['startTime' => 'ASC']);
    }

    public function searchActivities(?string $query, ?string $type, ?bool $isActive): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($query) {
            $qb->andWhere('a.title LIKE :query OR a.description LIKE :query OR a.location LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($type && $type !== 'all') {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $type);
        }

        if ($isActive !== null) {
            $qb->andWhere('a.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        return $qb->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}