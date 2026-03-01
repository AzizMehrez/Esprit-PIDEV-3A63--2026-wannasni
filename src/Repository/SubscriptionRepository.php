<?php

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * Trouve l'abonnement actif d'un senior
     */
    public function findActiveBySenior(User $senior): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.senior = :senior')
            ->andWhere('s.status = :status')
            ->setParameter('senior', $senior)
            ->setParameter('status', Subscription::STATUS_ACTIVE)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve l'abonnement actif ou en attente d'un senior
     */
    public function findCurrentBySenior(User $senior): ?Subscription
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.senior = :senior')
            ->andWhere('s.status IN (:statuses)')
            ->setParameter('senior', $senior)
            ->setParameter('statuses', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_PENDING,
                Subscription::STATUS_PAST_DUE,
            ])
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les abonnements souscrits par un utilisateur (famille)
     * @return Subscription[]
     */
    public function findBySubscriber(User $subscriber): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.subscriber = :subscriber')
            ->setParameter('subscriber', $subscriber)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve l'abonnement par Stripe Subscription ID
     */
    public function findByStripeSubscriptionId(string $stripeSubId): ?Subscription
    {
        return $this->findOneBy(['stripeSubscriptionId' => $stripeSubId]);
    }

    /**
     * Trouve les abonnements en impayé (pour cron/notification)
     * @return Subscription[]
     */
    public function findPastDue(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', Subscription::STATUS_PAST_DUE)
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique des abonnements d'un senior
     * @return Subscription[]
     */
    public function findAllBySenior(User $senior): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.senior = :senior')
            ->setParameter('senior', $senior)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(100)
            ->getQuery()
            ->getResult();
    }
}
