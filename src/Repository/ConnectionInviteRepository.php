<?php

namespace App\Repository;

use App\Entity\ConnectionInvite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConnectionInvite>
 */
class ConnectionInviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConnectionInvite::class);
    }

    /**
     * Find pending invites received by a user.
     * @return ConnectionInvite[]
     */
    public function findPendingForUser(User $user): array
    {
        return $this->createQueryBuilder('ci')
            ->leftJoin('ci.sender', 's')
            ->addSelect('s')
            ->where('ci.receiver = :user')
            ->andWhere('ci.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ConnectionInvite::STATUS_PENDING)
            ->orderBy('ci.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find pending invites sent by a user.
     * @return ConnectionInvite[]
     */
    public function findSentByUser(User $user): array
    {
        return $this->createQueryBuilder('ci')
            ->leftJoin('ci.receiver', 'r')
            ->addSelect('r')
            ->where('ci.sender = :user')
            ->andWhere('ci.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ConnectionInvite::STATUS_PENDING)
            ->orderBy('ci.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if an invite already exists (in either direction).
     */
    public function findExistingInvite(User $userA, User $userB): ?ConnectionInvite
    {
        return $this->createQueryBuilder('ci')
            ->where('(ci.sender = :a AND ci.receiver = :b) OR (ci.sender = :b AND ci.receiver = :a)')
            ->andWhere('ci.status = :pending')
            ->setParameter('a', $userA)
            ->setParameter('b', $userB)
            ->setParameter('pending', ConnectionInvite::STATUS_PENDING)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count pending invites for a user (for notification badge).
     */
    public function countPendingForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('ci')
            ->select('COUNT(ci.id)')
            ->where('ci.receiver = :user')
            ->andWhere('ci.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ConnectionInvite::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
