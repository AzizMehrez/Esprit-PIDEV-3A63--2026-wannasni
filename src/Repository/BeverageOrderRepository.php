<?php

namespace App\Repository;

use App\Entity\BeverageOrder;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class BeverageOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BeverageOrder::class);
    }

    public function findActiveCart(User $user): ?BeverageOrder
    {
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', BeverageOrder::STATUS_CART)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findUserOrders(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.status != :cart')
            ->setParameter('user', $user)
            ->setParameter('cart', BeverageOrder::STATUS_CART)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findRecentOrders(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.status != :cart')
            ->setParameter('user', $user)
            ->setParameter('cart', BeverageOrder::STATUS_CART)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getCartItemCount(User $user): int
    {
        $cart = $this->findActiveCart($user);
        return $cart ? $cart->getItemCount() : 0;
    }
}
