<?php

namespace App\Repository;

use App\Entity\NutritionJournal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NutritionJournal>
 */
class NutritionJournalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NutritionJournal::class);
    }

    public function findBySenior($senior): array
    {
        $qb = $this->createQueryBuilder('n')
            ->orderBy('n.date', 'DESC');

        if ($senior instanceof \App\Entity\User) {
            $qb->andWhere('n.senior = :senior')
               ->setParameter('senior', $senior);
        } else {
            $qb->andWhere('n.senior = :senior')
               ->setParameter('senior', (int) $senior);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByDate(\DateTimeInterface $date, $senior = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('DATE(n.date) = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('n.date', 'ASC');

        if ($senior !== null) {
            if ($senior instanceof \App\Entity\User) {
                $qb->andWhere('n.senior = :senior')
                   ->setParameter('senior', $senior);
            } else {
                $qb->andWhere('n.senior = :senior')
                   ->setParameter('senior', (int) $senior);
            }
        }

        return $qb->getQuery()->getResult();
    }

    public function getTotalCaloriesForDate(\DateTimeInterface $date, $senior): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('SUM(n.calories)')
            ->andWhere('DATE(n.date) = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        if ($senior instanceof \App\Entity\User) {
            $qb->andWhere('n.senior = :senior')
               ->setParameter('senior', $senior);
        } else {
            $qb->andWhere('n.senior = :senior')
               ->setParameter('senior', (int) $senior);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
