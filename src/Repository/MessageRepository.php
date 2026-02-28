<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Get messages for a conversation, newest first or oldest first.
     * @return Message[]
     */
    public function findByConversation(Conversation $conversation, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.sender', 's')
            ->addSelect('s')
            ->where('m.conversation = :conv')
            ->setParameter('conv', $conversation)
            ->orderBy('m.createdAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Mark all messages from the other user in a conversation as read.
     */
    public function markAsRead(Conversation $conversation, User $reader): int
    {
        return $this->getEntityManager()
            ->createQuery('
                UPDATE App\Entity\Message m
                SET m.isRead = true
                WHERE m.conversation = :conv
                  AND m.sender != :reader
                  AND m.isRead = false
            ')
            ->setParameter('conv', $conversation)
            ->setParameter('reader', $reader)
            ->execute();
    }
}
