<?php

namespace App\Repository;

use App\Entity\Treatment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Treatment>
 */
class TreatmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Treatment::class);
    }

    public function findActiveBySenior($senior): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.statut = :status')
            ->setParameter('status', 'active')
            ->orderBy('t.dateDebut', 'ASC');

        if ($senior instanceof \App\Entity\User) {
            $qb->andWhere('t.senior = :senior')
               ->setParameter('senior', $senior);
        } else {
            $qb->andWhere('t.senior = :senior')
               ->setParameter('senior', (int) $senior);
        }

        return $qb->getQuery()->getResult();
    }
}
