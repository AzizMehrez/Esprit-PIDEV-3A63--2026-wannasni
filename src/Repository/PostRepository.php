<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Get feed posts for a user: own posts + friends' posts (public or connected).
     * @param int[] $friendIds
     */
    public function findFeedPosts(User $user, array $friendIds, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->leftJoin('p.media', 'm')
            ->addSelect('a', 'm')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (!empty($friendIds)) {
            $allIds = array_merge($friendIds, [$user->getId()]);
            $qb->where('p.author IN (:ids)')
               ->setParameter('ids', $allIds);
        } else {
            // Only own posts + public posts
            $qb->where('p.author = :me OR a.profilePublic = true')
               ->setParameter('me', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all posts by a specific user.
     */
    public function findByAuthor(User $author, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.media', 'm')
            ->addSelect('m')
            ->where('p.author = :author')
            ->setParameter('author', $author)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get reels feed (only reel-type posts).
     * @param int[] $friendIds
     */
    public function findReels(User $user, array $friendIds, int $limit = 20, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')
            ->leftJoin('p.media', 'm')
            ->addSelect('a', 'm')
            ->where('p.type = :type')
            ->setParameter('type', Post::TYPE_REEL)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (!empty($friendIds)) {
            $allIds = array_merge($friendIds, [$user->getId()]);
            $qb->andWhere('p.author IN (:ids)')
               ->setParameter('ids', $allIds);
        } else {
            $qb->andWhere('p.author = :me OR a.profilePublic = true')
               ->setParameter('me', $user);
        }

        return $qb->getQuery()->getResult();
    }
}
