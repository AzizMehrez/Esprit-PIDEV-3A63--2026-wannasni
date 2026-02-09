<?php

namespace App\Repository;

use App\Entity\HealthJournal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HealthJournal>
 *
 * @method HealthJournal|null find($id, $lockMode = null, $lockVersion = null)
 * @method HealthJournal|null findOneBy(array $criteria, array $orderBy = null)
 * @method HealthJournal[]    findAll()
 * @method HealthJournal[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HealthJournalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HealthJournal::class);
    }

    public function save(HealthJournal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HealthJournal $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Finds health journals for a specific senior, ordered by date descending
     */
    public function findBySenior(int $seniorId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.seniorId = :seniorId')
            ->setParameter('seniorId', $seniorId)
            ->orderBy('h.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
