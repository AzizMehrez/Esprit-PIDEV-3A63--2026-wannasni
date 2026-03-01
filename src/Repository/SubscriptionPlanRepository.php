<?php

namespace App\Repository;

use App\Entity\SubscriptionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubscriptionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SubscriptionPlan::class);
    }

    /**
     * @return SubscriptionPlan[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.active = :active')
            ->setParameter('active', true)
            ->orderBy('p.sortOrder', 'ASC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug): ?SubscriptionPlan
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
