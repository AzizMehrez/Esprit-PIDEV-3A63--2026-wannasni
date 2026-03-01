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
     * Uses multi-step hydration to avoid cartesian product from multiple collection JOINs.
     *
     * Step 1: Fetch post IDs with Paginator (handles LIMIT + collection correctly)
     * Step 2: Load posts + author + media (single collection JOIN only)
     * Step 3: Batch-load likes and comments in separate queries (Doctrine identity map)
     *
     * @param int[] $friendIds
     * @return Post[]
     */
    public function findFeedPosts(User $user, array $friendIds, int $limit = 20, int $offset = 0): array
    {
        // Step 1: Get post IDs with proper pagination
        $idQb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->leftJoin('p.author', 'a')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (!empty($friendIds)) {
            $allIds = array_merge($friendIds, [$user->getId()]);
            $idQb->where('p.author IN (:ids)')
                 ->setParameter('ids', $allIds);
        } else {
            $idQb->where('p.author = :me OR a.profilePublic = true')
                 ->setParameter('me', $user);
        }

        $postIds = array_column($idQb->getQuery()->getScalarResult(), 'id');

        if (empty($postIds)) {
            return [];
        }

        // Step 2: Load posts with author + media (only 1 collection JOIN)
        // No LIMIT here: IDs from Step 1 already constrain the set (avoids setMaxResults+collection join issue)
        $posts = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('p.media', 'm')->addSelect('m')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getResult();

        // Step 3: Batch-load likes and comments in separate queries (avoids cartesian product)
        $this->batchLoadCollections($postIds);

        // Restore original order from Step 1
        $indexed = [];
        foreach ($posts as $post) {
            $indexed[$post->getId()] = $post;
        }
        $sorted = [];
        foreach ($postIds as $id) {
            if (isset($indexed[$id])) {
                $sorted[] = $indexed[$id];
            }
        }

        return $sorted;
    }

    /**
     * Get all posts by a specific user.
     * @return Post[]
     */
    public function findByAuthor(User $author, int $limit = 50, int $offset = 0): array
    {
        // Step 1: Get IDs
        $postIds = array_column(
            $this->createQueryBuilder('p')
                ->select('p.id')
                ->where('p.author = :author')
                ->setParameter('author', $author)
                ->orderBy('p.createdAt', 'DESC')
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getScalarResult(),
            'id'
        );

        if (empty($postIds)) {
            return [];
        }

        // Step 2: Load posts + media (no LIMIT — IDs already constrained)
        $posts = $this->createQueryBuilder('p')
            ->leftJoin('p.media', 'm')->addSelect('m')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getResult();

        // Step 3: Batch-load collections
        $this->batchLoadCollections($postIds);

        // Restore original order from Step 1
        $indexed = [];
        foreach ($posts as $post) {
            $indexed[$post->getId()] = $post;
        }
        $sorted = [];
        foreach ($postIds as $id) {
            if (isset($indexed[$id])) {
                $sorted[] = $indexed[$id];
            }
        }

        return $sorted;
    }

    /**
     * Get reels feed (only reel-type posts).
     * @param int[] $friendIds
     * @return Post[]
     */
    public function findReels(User $user, array $friendIds, int $limit = 20, int $offset = 0): array
    {
        // Step 1: Get IDs
        $idQb = $this->createQueryBuilder('p')
            ->select('p.id')
            ->leftJoin('p.author', 'a')
            ->where('p.type = :type')
            ->setParameter('type', Post::TYPE_REEL)
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        if (!empty($friendIds)) {
            $allIds = array_merge($friendIds, [$user->getId()]);
            $idQb->andWhere('p.author IN (:ids)')
                 ->setParameter('ids', $allIds);
        } else {
            $idQb->andWhere('p.author = :me OR a.profilePublic = true')
                 ->setParameter('me', $user);
        }

        $postIds = array_column($idQb->getQuery()->getScalarResult(), 'id');

        if (empty($postIds)) {
            return [];
        }

        // Step 2: Load posts + author + media (no LIMIT — IDs already constrained)
        $posts = $this->createQueryBuilder('p')
            ->leftJoin('p.author', 'a')->addSelect('a')
            ->leftJoin('p.media', 'm')->addSelect('m')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getResult();

        // Step 3: Batch-load collections
        $this->batchLoadCollections($postIds);

        // Restore original order from Step 1
        $indexed = [];
        foreach ($posts as $post) {
            $indexed[$post->getId()] = $post;
        }
        $sorted = [];
        foreach ($postIds as $id) {
            if (isset($indexed[$id])) {
                $sorted[] = $indexed[$id];
            }
        }

        return $sorted;
    }

    /**
     * Batch-load likes and comments for the given post IDs.
     * These are loaded in separate queries to avoid cartesian product.
     * Doctrine's identity map ensures already-loaded posts get their collections populated.
     *
     * @param int[] $postIds
     */
    private function batchLoadCollections(array $postIds): void
    {
        if (empty($postIds)) {
            return;
        }

        // Load likes for all posts in one query
        $this->getEntityManager()->createQueryBuilder()
            ->select('p', 'l')
            ->from(Post::class, 'p')
            ->leftJoin('p.likes', 'l')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getResult();

        // Load comments for all posts in one query
        $this->getEntityManager()->createQueryBuilder()
            ->select('p', 'c')
            ->from(Post::class, 'p')
            ->leftJoin('p.comments', 'c')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getResult();
    }
}
