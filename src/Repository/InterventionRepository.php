<?php

namespace App\Repository;

use App\Entity\Intervention;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Intervention>
 */
class InterventionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Intervention::class);
    }

    /**
     * Récupérer toutes les interventions avec leurs services liés
     */
    public function findAllWithServices(): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.serviceRequest', 's')
            ->addSelect('s')
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les interventions par statut
     */
    public function findByStatut(string $statut): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.serviceRequest', 's')
            ->addSelect('s')
            ->where('i.statutActuel = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les interventions d'un technicien
     */
    public function findByTechnicien(int $technicienId): array
    {
        return $this->createQueryBuilder('i')
            ->leftJoin('i.serviceRequest', 's')
            ->addSelect('s')
            ->where('i.idEmploye = :technicienId')
            ->setParameter('technicienId', $technicienId)
            ->orderBy('i.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les interventions par statut
     */
    public function countByStatut(string $statut): int
    {
        return $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.statutActuel = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
