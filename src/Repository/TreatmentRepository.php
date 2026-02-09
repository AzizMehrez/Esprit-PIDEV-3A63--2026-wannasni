<?php

namespace App\Repository;

use App\Entity\Treatment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Treatment>
 *
 * @method Treatment|null find($id, $lockMode = null, $lockVersion = null)
 * @method Treatment|null findOneBy(array $criteria, array $orderBy = null)
 * @method Treatment[]    findAll()
 * @method Treatment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TreatmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Treatment::class);
    }

    public function save(Treatment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Treatment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Search treatments by medication name
     */
    public function search(string $query, string $sort = 'id', string $direction = 'asc'): array
    {
        $qb = $this->createQueryBuilder('t');

        if ($query) {
            $qb->leftJoin('t.senior', 's')
               ->leftJoin('t.prescribedByDoctor', 'd')
               ->andWhere('t.medication LIKE :query OR s.firstName LIKE :query OR s.lastName LIKE :query OR d.firstName LIKE :query OR d.lastName LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        // Allow sorting by valid fields
        if (in_array($sort, ['id', 'medication', 'startDate', 'endDate', 'isActive'])) {
            $qb->orderBy('t.' . $sort, strtoupper($direction));
        }

        return $qb->getQuery()->getResult();
    }
}
