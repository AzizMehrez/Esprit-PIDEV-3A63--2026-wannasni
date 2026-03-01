<?php

namespace App\Repository;

use App\Entity\VerificationRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VerificationRequest>
 */
class VerificationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationRequest::class);
    }

    /**
     * Find the latest active (pending) request for a user.
     */
    public function findPendingByUser(User $user): ?VerificationRequest
    {
        return $this->createQueryBuilder('vr')
            ->andWhere('vr.user = :user')
            ->andWhere('vr.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', VerificationRequest::STATUS_PENDING)
            ->orderBy('vr.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Check if user already has a pending or approved request.
     */
    public function hasActiveRequest(User $user): bool
    {
        $count = $this->createQueryBuilder('vr')
            ->select('COUNT(vr.id)')
            ->andWhere('vr.user = :user')
            ->andWhere('vr.status IN (:statuses)')
            ->setParameter('user', $user->getId())
            ->setParameter('statuses', [
                VerificationRequest::STATUS_PENDING,
                VerificationRequest::STATUS_APPROVED,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find all pending requests for admin review queue.
     */
    public function findAllPending(): array
    {
        return $this->createQueryBuilder('vr')
            ->andWhere('vr.status = :status')
            ->setParameter('status', VerificationRequest::STATUS_PENDING)
            ->orderBy('vr.createdAt', 'ASC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count pending requests for admin notification badge.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('vr')
            ->select('COUNT(vr.id)')
            ->andWhere('vr.status = :status')
            ->setParameter('status', VerificationRequest::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
