<?php

namespace App\Repository;

use App\Entity\Beverage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Beverage>
 */
class BeverageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Beverage::class);
    }

    /**
     * Recherche de boissons actives par catégorie
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.category = :category')
            ->andWhere('b.isActive = true')
            ->setParameter('category', $category)
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de boissons compatibles avec un type de régime
     */
    public function findCompatibleWithRegime(string $regimeType): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->andWhere('b.compatibleRegimes LIKE :regime')
            ->setParameter('regime', '%' . $regimeType . '%')
            ->orderBy('b.hydrationScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de boissons adaptées à un moment de la journée
     */
    public function findForMoment(string $moment): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->andWhere('b.idealMoments LIKE :moment OR b.idealMoments LIKE :anytime')
            ->setParameter('moment', '%' . $moment . '%')
            ->setParameter('anytime', '%tout_moment%')
            ->orderBy('b.hydrationScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des boissons sans sucre
     */
    public function findSugarFree(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->andWhere('b.isSugarFree = true')
            ->orderBy('b.hydrationScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des boissons sans caféine
     */
    public function findCaffeineFree(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->andWhere('b.isCaffeineFree = true')
            ->orderBy('b.hydrationScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Top boissons par score d'hydratation
     */
    public function findTopHydrating(int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->andWhere('b.hydrationScore IS NOT NULL')
            ->orderBy('b.hydrationScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche textuelle
     */
    public function search(string $query): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->andWhere('b.name LIKE :q OR b.description LIKE :q OR b.category LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Toutes les boissons actives
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->orderBy('b.category', 'ASC')
            ->addOrderBy('b.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
