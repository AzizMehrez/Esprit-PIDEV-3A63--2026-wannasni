<?php

namespace App\Repository;

use App\Entity\Activity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activity>
 *
 * @method Activity|null find($id, $lockMode = null, $lockVersion = null)
 * @method Activity|null findOneBy(array $criteria, array $orderBy = null)
 * @method Activity[]    findAll()
 * @method Activity[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.startTime', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming activities
     */
    public function findUpcoming(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.startTime > :now')
            ->setParameter('active', true)
            ->setParameter('now', new \DateTime())
            ->orderBy('a.startTime', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }
}
