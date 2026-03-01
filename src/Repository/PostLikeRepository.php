<?php

namespace App\Repository;

use App\Entity\PostLike;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostLike>
 */
class PostLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostLike::class);
    }

    public function findByUserAndPost(User $user, Post $post): ?PostLike
    {
        return $this->findOneBy(['user' => $user, 'post' => $post]);
    }

    /**
     * Get all post IDs liked by a user from a given list of post IDs.
     * Single query instead of N find() calls.
     *
     * @param int[] $postIds
     * @return int[]
     */
    public function findLikedPostIds(User $user, array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        $rows = $this->createQueryBuilder('pl')
            ->select('IDENTITY(pl.post) as postId')
            ->where('pl.user = :user')
            ->andWhere('pl.post IN (:postIds)')
            ->setParameter('user', $user)
            ->setParameter('postIds', $postIds)
            ->getQuery()
            ->getScalarResult();

        return array_map('intval', array_column($rows, 'postId'));
    }

    public function countByPost(Post $post): int
    {
        return $this->count(['post' => $post]);
    }
}
