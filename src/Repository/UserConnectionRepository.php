<?php

namespace App\Repository;

use App\Entity\UserConnection;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserConnection>
 */
class UserConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserConnection::class);
    }

    /**
     * Check if two users are connected.
     */
    public function areConnected(User $userA, User $userB): bool
    {
        $ids = [$userA->getId(), $userB->getId()];
        sort($ids);

        return (bool) $this->createQueryBuilder('uc')
            ->select('COUNT(uc.id)')
            ->where('uc.userA = :a AND uc.userB = :b')
            ->setParameter('a', $ids[0])
            ->setParameter('b', $ids[1])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get all friend IDs for a user.
     * Uses scalar result to avoid full entity hydration.
     * @return int[]
     */
    public function findFriendIds(User $user): array
    {
        $userId = $user->getId();

        $rows = $this->createQueryBuilder('uc')
            ->select('IDENTITY(uc.userA) as userAId, IDENTITY(uc.userB) as userBId')
            ->where('uc.userA = :uid OR uc.userB = :uid')
            ->setParameter('uid', $userId)
            ->getQuery()
            ->getScalarResult();

        $ids = [];
        foreach ($rows as $row) {
            $a = (int) $row['userAId'];
            $b = (int) $row['userBId'];
            $ids[] = ($a === $userId) ? $b : $a;
        }

        return $ids;
    }

    /**
     * Get all connections for a user with the friend User objects loaded.
     * @return UserConnection[]
     */
    public function findConnectionsForUser(User $user): array
    {
        return $this->createQueryBuilder('uc')
            ->leftJoin('uc.userA', 'a')
            ->leftJoin('uc.userB', 'b')
            ->addSelect('a', 'b')
            ->where('uc.userA = :uid OR uc.userB = :uid')
            ->setParameter('uid', $user->getId())
            ->orderBy('uc.connectedAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }
}
