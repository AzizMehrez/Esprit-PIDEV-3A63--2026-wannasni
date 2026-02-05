<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function save(Participation $participation, bool $flush = false): void
    {
        $this->getEntityManager()->persist($participation);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Participation $participation, bool $flush = false): void
    {
        $this->getEntityManager()->remove($participation);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ========== LEGACY METHODS (for backward compatibility) ==========

    public function findByUserAndActivity(int $seniorId, int $activityId): ?Participation
    {
        $result = $this->findOneBy([
            'participantId' => $seniorId,
            'activityId' => $activityId,
        ]);
        
        // If not found by participantId, try legacy seniorId
        if (!$result) {
            $result = $this->findOneBy([
                'seniorId' => $seniorId,
                'activityId' => $activityId,
            ]);
        }
        
        return $result;
    }

    public function findByUser(int $seniorId): array
    {
        return $this->findBy(['seniorId' => $seniorId, 'status' => 'registered']);
    }

    public function findActiveByUser(int $seniorId): array
    {
        // Try new field first
        $results = $this->findBy(
            ['participantId' => $seniorId],
            ['registrationDate' => 'DESC']
        );
        
        // If not found, try legacy field
        if (empty($results)) {
            $results = $this->findBy(
                ['seniorId' => $seniorId],
                ['registeredAt' => 'DESC']
            );
        }
        
        return $results;
    }

    // ========== NEW ENHANCED METHODS ==========

    /**
     * Find all participations for a participant (senior)
     */
    public function findByParticipantId(int $participantId, ?string $status = null, ?string $orderBy = 'DESC'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.participantId = :participantId')
            ->setParameter('participantId', $participantId)
            ->orderBy('p.registrationDate', $orderBy);

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find participation by participant and activity (new method name)
     */
    public function findByParticipantAndActivity(int $participantId, int $activityId): ?Participation
    {
        return $this->findOneBy([
            'participantId' => $participantId,
            'activityId' => $activityId,
        ]);
    }

    /**
     * Get all participations for an activity
     */
    public function findByActivityId(int $activityId, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->orderBy('p.registrationDate', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get participations with feedback (ratings and comments)
     */
    public function findWithFeedback(int $activityId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.activityId = :activityId')
            ->andWhere('p.feedbackRating IS NOT NULL OR p.feedbackComment IS NOT NULL')
            ->setParameter('activityId', $activityId)
            ->orderBy('p.registrationDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get average rating for an activity
     */
    public function getAverageRating(int $activityId): ?float
    {
        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.feedbackRating) as avgRating')
            ->where('p.activityId = :activityId')
            ->andWhere('p.feedbackRating IS NOT NULL')
            ->setParameter('activityId', $activityId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['avgRating'] ?? null;
    }

    /**
     * Get participants by status (present, absent, etc.)
     */
    public function countByStatus(int $activityId): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count')
            ->where('p.activityId = :activityId')
            ->setParameter('activityId', $activityId)
            ->groupBy('p.status')
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Update participation status (for attendance tracking)
     */
    public function updateParticipationStatus(int $participationId, string $newStatus, ?\DateTimeInterface $confirmationDate = null): void
    {
        $participation = $this->find($participationId);
        if ($participation) {
            $participation->setStatus($newStatus);
            if ($confirmationDate) {
                $participation->setPresenceConfirmationDate($confirmationDate);
            }
            $this->save($participation, true);
        }
    }
}
