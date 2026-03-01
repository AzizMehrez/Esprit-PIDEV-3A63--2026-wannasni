<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * Find or create a conversation between two users.
     */
    public function findBetween(User $userA, User $userB): ?Conversation
    {
        $ids = [$userA->getId(), $userB->getId()];
        sort($ids);

        return $this->createQueryBuilder('c')
            ->where('c.userA = :a AND c.userB = :b')
            ->setParameter('a', $ids[0])
            ->setParameter('b', $ids[1])
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all conversations for a user, most recent first.
     * @return Conversation[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.userA', 'a')
            ->leftJoin('c.userB', 'b')
            ->addSelect('a', 'b')
            ->where('c.userA = :uid OR c.userB = :uid')
            ->setParameter('uid', $user->getId())
            ->orderBy('c.lastMessageAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread messages across all conversations for a user.
     */
    public function countUnreadForUser(User $user): int
    {
        $uid = $user->getId();

        return (int) $this->getEntityManager()
            ->createQuery('
                SELECT COUNT(m.id) FROM App\Entity\Message m
                JOIN m.conversation c
                WHERE (c.userA = :uid OR c.userB = :uid)
                  AND m.sender != :uid2
                  AND m.isRead = false
            ')
            ->setParameter('uid', $uid)
            ->setParameter('uid2', $uid)
            ->getSingleScalarResult();
    }
}
