<?php

namespace App\Repository;

use App\Entity\HealthJournal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HealthJournal>
 */
class HealthJournalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthJournal::class);
    }

    public function findBySenior($senior): array
    {
        $qb = $this->createQueryBuilder('h')
            ->orderBy('h.date', 'DESC');

        if ($senior instanceof \App\Entity\User) {
            $qb->andWhere('h.senior = :senior')
               ->setParameter('senior', $senior);
        } else {
            $qb->andWhere('h.senior = :senior')
               ->setParameter('senior', (int) $senior);
        }

        return $qb->getQuery()->getResult();
    }
}
