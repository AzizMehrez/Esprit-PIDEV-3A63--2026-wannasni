<?php

namespace App\Repository;

use App\Entity\RegimePrescrit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RegimePrescrit>
 *
 * @method RegimePrescrit|null find($id, $lockMode = null, $lockVersion = null)
 * @method RegimePrescrit|null findOneBy(array $criteria, array $orderBy = null)
 * @method RegimePrescrit[]    findAll()
 * @method RegimePrescrit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RegimePrescritRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RegimePrescrit::class);
    }

    /**
     * Trouve les régimes par nutritionniste ID
     */
    public function findByNutritionnisteId(int $nutritionnisteId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.nutritionnisteId = :nutritionnisteId')
            ->setParameter('nutritionnisteId', $nutritionnisteId)
            ->orderBy('r.datePrescription', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les régimes par senior ID
     */
    public function findBySeniorId(int $seniorId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.seniorId = :seniorId')
            ->setParameter('seniorId', $seniorId)
            ->orderBy('r.datePrescription', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un régime par la demande associée
     */
    public function findOneByDemandeId(int $demandeId): ?RegimePrescrit
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.demande', 'd')
            ->andWhere('d.id = :demandeId')
            ->setParameter('demandeId', $demandeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les régimes actifs (en cours)
     */
    public function findRegimesActifs(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('r')
            ->andWhere('r.dateDebut <= :now')
            ->andWhere('r.dateFin >= :now')
            ->setParameter('now', $now)
            ->orderBy('r.dateDebut', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les régimes par nutritionniste
     */
    public function countByNutritionnisteId(int $nutritionnisteId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.nutritionnisteId = :nutritionnisteId')
            ->setParameter('nutritionnisteId', $nutritionnisteId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les régimes par type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.typeRegime = :type')
            ->setParameter('type', $type)
            ->orderBy('r.datePrescription', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde un régime
     */
    public function save(RegimePrescrit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un régime
     */
    public function remove(RegimePrescrit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les régimes avec pagination
     */
    public function findPaginated(int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;
        
        return $this->createQueryBuilder('r')
            ->orderBy('r.datePrescription', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des régimes
     */
    public function getStatistics(): array
    {
        return $this->createQueryBuilder('r')
            ->select([
                'COUNT(r.id) as total',
                'COUNT(CASE WHEN r.dateFin >= CURRENT_DATE() THEN 1 END) as actifs',
                'AVG(r.caloriesJournalieres) as calories_moyennes',
                'r.typeRegime as type',
                'COUNT(r.typeRegime) as count_by_type'
            ])
            ->groupBy('r.typeRegime')
            ->getQuery()
            ->getResult();
    }
}