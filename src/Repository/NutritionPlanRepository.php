<?php

namespace App\Repository;

use App\Entity\NutritionPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NutritionPlan>
 */
class NutritionPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NutritionPlan::class);
    }

    public function findActiveBySenior($senior): ?NutritionPlan
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = true')
            ->orderBy('p.startDate', 'DESC')
            ->setMaxResults(1);

        if ($senior instanceof \App\Entity\User) {
            $qb->andWhere('p.senior = :senior')
               ->setParameter('senior', $senior);
        } else {
            $qb->andWhere('p.senior = :senior')
               ->setParameter('senior', (int) $senior);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findAllBySenior($senior): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.startDate', 'DESC');

        if ($senior instanceof \App\Entity\User) {
            $qb->andWhere('p.senior = :senior')
               ->setParameter('senior', $senior);
        } else {
            $qb->andWhere('p.senior = :senior')
               ->setParameter('senior', (int) $senior);
        }

        return $qb->getQuery()->getResult();
    }
}
