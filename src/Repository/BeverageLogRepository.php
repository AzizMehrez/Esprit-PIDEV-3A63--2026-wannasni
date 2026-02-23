<?php

namespace App\Repository;

use App\Entity\BeverageLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BeverageLog>
 */
class BeverageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BeverageLog::class);
    }

    /**
     * Consommation du jour pour un utilisateur
     */
    public function findTodayLogs(User $user): array
    {
        $today = new \DateTime('today');
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.consumedAt >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->orderBy('l.consumedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Hydratation totale du jour en ml
     */
    public function getTodayHydration(User $user): int
    {
        $today = new \DateTime('today');
        try {
            return (int) $this->createQueryBuilder('l')
                ->select('COALESCE(SUM(l.quantityMl), 0)')
                ->where('l.user = :user')
                ->andWhere('l.consumedAt >= :today')
                ->setParameter('user', $user)
                ->setParameter('today', $today)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Nombre de boissons consommées aujourd'hui
     */
    public function getTodayCount(User $user): int
    {
        $today = new \DateTime('today');
        try {
            return (int) $this->createQueryBuilder('l')
                ->select('COUNT(l.id)')
                ->where('l.user = :user')
                ->andWhere('l.consumedAt >= :today')
                ->setParameter('user', $user)
                ->setParameter('today', $today)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Historique sur N jours
     */
    public function findHistoryDays(User $user, int $days = 7): array
    {
        $since = new \DateTime("-{$days} days");
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.consumedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('l.consumedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques d'hydratation par jour (7 derniers jours)
     */
    public function getHydrationStats(User $user, int $days = 7): array
    {
        $since = new \DateTime("-{$days} days");
        $logs = $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->andWhere('l.consumedAt >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->orderBy('l.consumedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($logs as $log) {
            $date = $log->getConsumedAt()->format('Y-m-d');
            if (!isset($stats[$date])) {
                $stats[$date] = ['date' => $date, 'totalMl' => 0, 'count' => 0, 'categories' => []];
            }
            $stats[$date]['totalMl'] += $log->getQuantityMl();
            $stats[$date]['count']++;
            $cat = $log->getCategory() ?? ($log->getBeverage() ? $log->getBeverage()->getCategory() : 'autre');
            $stats[$date]['categories'][$cat] = ($stats[$date]['categories'][$cat] ?? 0) + $log->getQuantityMl();
        }

        return array_values($stats);
    }

    /**
     * Boissons favorites (les plus consommées)
     */
    public function getFavoriteBeverages(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('l')
            ->select('l.customBeverageName, l.category, COUNT(l.id) as consumeCount, SUM(l.quantityMl) as totalMl, AVG(l.satisfactionRating) as avgRating')
            ->where('l.user = :user')
            ->groupBy('l.customBeverageName, l.category')
            ->orderBy('consumeCount', 'DESC')
            ->setParameter('user', $user)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
