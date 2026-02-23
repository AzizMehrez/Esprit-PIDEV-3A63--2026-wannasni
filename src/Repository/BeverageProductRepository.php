<?php

namespace App\Repository;

use App\Entity\BeverageProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BeverageProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BeverageProduct::class);
    }

    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.salesCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findFeatured(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.isFeatured = true')
            ->orderBy('p.salesCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.category = :category')
            ->setParameter('category', $category)
            ->orderBy('p.salesCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCompatibleWithRegime(string $regime): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.compatibleRegimes LIKE :regime')
            ->setParameter('regime', '%' . $regime . '%')
            ->orderBy('p.salesCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findInStock(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.stockQuantity > 0')
            ->orderBy('p.salesCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function search(string $query): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.name LIKE :q OR p.description LIKE :q OR p.brand LIKE :q')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('p.salesCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findBestSellers(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.stockQuantity > 0')
            ->orderBy('p.salesCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOnSale(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.salePrice IS NOT NULL')
            ->andWhere('p.salePrice < p.price')
            ->orderBy('p.salesCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByCategory(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.category, COUNT(p.id) as cnt')
            ->where('p.isActive = true')
            ->groupBy('p.category')
            ->getQuery()
            ->getResult();
    }
}
