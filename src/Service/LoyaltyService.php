<?php

namespace App\Service;

use App\Entity\LoyaltyPoint;
use App\Entity\LoyaltyReward;
use App\Entity\User;
use App\Entity\Intervention;
use App\Repository\LoyaltyPointRepository;
use App\Repository\LoyaltyRewardRepository;
use App\Repository\InterventionRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class LoyaltyService
{
    // Points awarded per action
    private const POINTS_PER_INTERVENTION = 50;
    private const POINTS_SUBSCRIPTION_BONUS = 100;
    private const POINTS_ACTIVITY_BONUS = 20;

    /** @var array<int, int> Per-request cache for getTotalPoints() keyed by senior ID */
    private array $totalPointsCache = [];

    public function __construct(
        private EntityManagerInterface $em,
        private LoyaltyPointRepository $pointRepo,
        private LoyaltyRewardRepository $rewardRepo,
        private InterventionRepository $interventionRepo,
        private SubscriptionRepository $subscriptionRepo,
        private LoggerInterface $logger,
        private string $projectDir,
    ) {}

    // ─── Points Management ──────────────────────────────────────────────

    /**
     * Award points for a completed intervention
     */
    public function awardInterventionPoints(User $senior, Intervention $intervention): LoyaltyPoint
    {
        $basePoints = self::POINTS_PER_INTERVENTION;

        // Bonus based on intervention complexity
        $hours = $intervention->getHeuresTravail() ?? 1;
        $complexityBonus = min(50, $hours * 10);

        $totalPoints = $basePoints + $complexityBonus;

        $point = new LoyaltyPoint();
        $point->setSenior($senior)
            ->setPoints($totalPoints)
            ->setSource(LoyaltyPoint::SOURCE_INTERVENTION)
            ->setSourceId($intervention->getId())
            ->setDescription(sprintf(
                'Points gagnés pour l\'intervention #%d (%s) - %d pts de base + %d pts bonus complexité',
                $intervention->getId(),
                $intervention->getTypesServices() ?? 'Service',
                $basePoints,
                $complexityBonus
            ))
            ->setExpiresAt((new \DateTime())->modify('+1 year'));

        $this->em->persist($point);
        $this->em->flush();

        $this->logger->info('Loyalty points awarded', [
            'senior_id' => $senior->getId(),
            'intervention_id' => $intervention->getId(),
            'points' => $totalPoints,
        ]);

        // Check if we should generate a personalized reward
        $this->checkAndGenerateReward($senior);

        return $point;
    }

    /**
     * Award bonus points (subscription, activity, etc.)
     */
    public function awardBonusPoints(User $senior, int $points, string $source, ?int $sourceId = null, string $description = ''): LoyaltyPoint
    {
        $point = new LoyaltyPoint();
        $point->setSenior($senior)
            ->setPoints($points)
            ->setSource($source)
            ->setSourceId($sourceId)
            ->setDescription($description ?: "Bonus de $points points")
            ->setExpiresAt((new \DateTime())->modify('+1 year'));

        $this->em->persist($point);
        $this->em->flush();

        // Check if we should generate a personalized reward
        $this->checkAndGenerateReward($senior);

        return $point;
    }

    /**
     * Deduct points (for reward redemption)
     */
    public function deductPoints(User $senior, int $points, string $description = ''): LoyaltyPoint
    {
        $point = new LoyaltyPoint();
        $point->setSenior($senior)
            ->setPoints(-$points)
            ->setSource(LoyaltyPoint::SOURCE_REDEMPTION)
            ->setDescription($description ?: "Échange de $points points");

        $this->em->persist($point);
        $this->em->flush();

        return $point;
    }

    // ─── Points Queries ─────────────────────────────────────────────────

    /**
     * Get total points with per-request memoization to avoid duplicate DB queries.
     */
    private function getCachedTotalPoints(User $senior): int
    {
        $seniorId = $senior->getId();
        if (!array_key_exists($seniorId, $this->totalPointsCache)) {
            $this->totalPointsCache[$seniorId] = $this->pointRepo->getTotalPoints($senior);
        }
        return $this->totalPointsCache[$seniorId];
    }

    /**
     * Get complete loyalty dashboard data for a senior
     */
    public function getDashboardData(User $senior): array
    {
        $totalPoints = $this->getCachedTotalPoints($senior);
        $monthlyEarned = $this->pointRepo->getMonthlyEarned($senior);
        $interventionCount = $this->pointRepo->countInterventionPoints($senior);
        $pointsBySource = $this->pointRepo->getPointsBySource($senior);
        $history = $this->pointRepo->getHistory($senior, 10);
        $availableRewards = $this->rewardRepo->findAvailableForSenior($senior);
        $redeemedRewards = $this->rewardRepo->findRedeemedForSenior($senior);

        // Calculate level via ML
        $levelData = $this->callMLPredictor([
            'action' => 'level',
            'total_points' => $totalPoints,
        ]);

        return [
            'totalPoints' => $totalPoints,
            'monthlyEarned' => $monthlyEarned,
            'interventionCount' => $interventionCount,
            'pointsBySource' => $this->formatPointsBySource($pointsBySource),
            'history' => $history,
            'availableRewards' => $availableRewards,
            'redeemedRewards' => $redeemedRewards,
            'level' => $levelData,
            'availableRewardCount' => count($availableRewards),
        ];
    }

    // ─── ML-Powered Reward Generation ───────────────────────────────────

    /**
     * Check if a new personalized reward should be generated
     */
    public function checkAndGenerateReward(User $senior): ?LoyaltyReward
    {
        $totalPoints = $this->getCachedTotalPoints($senior);

        // Only generate rewards at certain point thresholds: every 100 points
        $availableRewards = $this->rewardRepo->countAvailableForSenior($senior);
        if ($availableRewards >= 3) {
            return null; // Max 3 pending rewards
        }

        // Generate if total points is a multiple of 100
        if ($totalPoints < 100) {
            return null;
        }

        return $this->generatePersonalizedReward($senior);
    }

    /**
     * Use ML to generate a personalized reward
     */
    public function generatePersonalizedReward(User $senior): ?LoyaltyReward
    {
        // Prevent duplicates: get existing available reward types for this user
        $existingRewards = $this->rewardRepo->findAvailableForSenior($senior);
        $existingTypes = array_map(fn(LoyaltyReward $r) => $r->getType(), $existingRewards);
        $existingTitles = array_map(fn(LoyaltyReward $r) => $r->getTitle(), $existingRewards);

        // If all 3 types already exist, don't generate another
        $allTypes = [LoyaltyReward::TYPE_DISCOUNT, LoyaltyReward::TYPE_FREE_MAINTENANCE, LoyaltyReward::TYPE_PLAN_UPGRADE];
        $availableTypes = array_diff($allTypes, $existingTypes);
        if (empty($availableTypes)) {
            return null;
        }

        $features = $this->buildMLFeatures($senior);

        $prediction = $this->callMLPredictor([
            'action' => 'predict',
            'features' => $features,
            'excluded_types' => array_values($existingTypes),
        ]);

        if (!$prediction || isset($prediction['error'])) {
            $this->logger->error('ML prediction failed', [
                'error' => $prediction['error'] ?? 'Unknown',
                'senior_id' => $senior->getId(),
            ]);
            // Fallback: generate a basic discount reward
            return $this->createFallbackReward($senior, $features, $existingTypes);
        }

        // Final dedup check: skip if exact same title exists
        if (in_array($prediction['title'], $existingTitles, true)) {
            return null;
        }

        $reward = new LoyaltyReward();
        $reward->setSenior($senior)
            ->setType($prediction['reward_type'])
            ->setTitle($prediction['title'])
            ->setDescription($prediction['description'])
            ->setPointsCost($prediction['points_cost'])
            ->setDiscountPercent($prediction['discount_percent'] ?? null)
            ->setMlConfidence($prediction['confidence'])
            ->setMlFeatures($prediction['features_used'] ?? null)
            ->setStatus(LoyaltyReward::STATUS_AVAILABLE);

        $this->em->persist($reward);
        $this->em->flush();

        $this->logger->info('ML reward generated', [
            'senior_id' => $senior->getId(),
            'reward_type' => $prediction['reward_type'],
            'confidence' => $prediction['confidence'],
        ]);

        return $reward;
    }

    /**
     * Redeem a reward
     */
    public function redeemReward(User $senior, LoyaltyReward $reward): bool
    {
        if (!$reward->isRedeemable()) {
            return false;
        }

        $totalPoints = $this->pointRepo->getTotalPoints($senior);
        if ($totalPoints < $reward->getPointsCost()) {
            return false;
        }

        // Deduct points
        $this->deductPoints($senior, $reward->getPointsCost(),
            sprintf('Échange pour : %s', $reward->getTitle()));

        // Mark reward as redeemed
        $reward->setStatus(LoyaltyReward::STATUS_REDEEMED);
        $reward->setRedeemedAt(new \DateTime());

        $this->em->flush();

        $this->logger->info('Reward redeemed', [
            'senior_id' => $senior->getId(),
            'reward_id' => $reward->getId(),
            'reward_type' => $reward->getType(),
            'points_spent' => $reward->getPointsCost(),
        ]);

        return true;
    }

    // ─── ML Integration ─────────────────────────────────────────────────

    /**
     * Build feature vector for ML prediction
     */
    private function buildMLFeatures(User $senior): array
    {
        $totalPoints = $this->pointRepo->getTotalPoints($senior);
        $interventionCount = $this->pointRepo->countInterventionPoints($senior);
        $monthlyPoints = $this->pointRepo->getMonthlyEarned($senior);

        // Get subscription plan level
        $subscriptionPlan = 0;
        $subscription = $this->subscriptionRepo->findCurrentBySenior($senior);
        if ($subscription) {
            $planSlug = $subscription->getPlan()->getSlug();
            $subscriptionPlan = match ($planSlug) {
                'essentiel' => 1,
                'confort' => 2,
                'premium' => 3,
                default => 0,
            };
        }

        // Account age
        $createdAt = $senior->getCreatedAt() ?? new \DateTime();
        $accountAgeDays = (new \DateTime())->diff($createdAt)->days;

        // Average intervention cost (estimate from tarif)
        $avgCost = 50; // Default estimate

        // Days since last intervention
        $lastInterventionDays = 30; // Default

        return [
            'total_points' => $totalPoints,
            'total_interventions' => $interventionCount,
            'avg_intervention_cost' => $avgCost,
            'subscription_plan' => $subscriptionPlan,
            'account_age_days' => $accountAgeDays,
            'monthly_points' => $monthlyPoints,
            'last_intervention_days' => $lastInterventionDays,
        ];
    }

    /**
     * Call the Python ML predictor script
     */
    private function callMLPredictor(array $input): ?array
    {
        $scriptPath = $this->projectDir . '/ml_model/loyalty_reward_predictor.py';

        if (!file_exists($scriptPath)) {
            $this->logger->error('ML predictor script not found', ['path' => $scriptPath]);
            return $this->getFallbackPrediction($input);
        }

        $process = proc_open(
            ['python', $scriptPath],
            [
                0 => ['pipe', 'r'],  // stdin
                1 => ['pipe', 'w'],  // stdout
                2 => ['pipe', 'w'],  // stderr
            ],
            $pipes
        );

        if (!is_resource($process)) {
            $this->logger->error('Failed to start ML predictor process');
            return $this->getFallbackPrediction($input);
        }

        fwrite($pipes[0], json_encode($input));
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $returnCode = proc_close($process);

        if ($returnCode !== 0) {
            $this->logger->error('ML predictor returned error', [
                'returnCode' => $returnCode,
                'stderr' => $stderr,
            ]);
            return $this->getFallbackPrediction($input);
        }

        $result = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Failed to parse ML output', ['output' => $output]);
            return $this->getFallbackPrediction($input);
        }

        return $result;
    }

    /**
     * Fallback prediction when ML is unavailable (pure PHP implementation)
     */
    private function getFallbackPrediction(array $input): array
    {
        $action = $input['action'] ?? 'predict';

        if ($action === 'level') {
            $totalPoints = $input['total_points'] ?? 0;
            return $this->calculateLevelPHP($totalPoints);
        }

        $features = $input['features'] ?? [];
        $totalPoints = $features['total_points'] ?? 0;
        $subscriptionPlan = $features['subscription_plan'] ?? 0;

        // Simple heuristic fallback
        if ($subscriptionPlan < 3 && $totalPoints >= 400) {
            return [
                'reward_type' => 'plan_upgrade',
                'confidence' => 0.65,
                'discount_percent' => 0,
                'points_cost' => 500,
                'title' => '⭐ Upgrade de votre abonnement',
                'description' => 'Passez au plan supérieur gratuitement pendant 1 mois !',
            ];
        } elseif ($totalPoints >= 300) {
            return [
                'reward_type' => 'free_maintenance',
                'confidence' => 0.70,
                'discount_percent' => 100,
                'points_cost' => 350,
                'title' => '🔧 Visite de maintenance gratuite',
                'description' => 'Bénéficiez d\'une visite de maintenance préventive gratuite.',
            ];
        } else {
            $discount = min(25, 10 + intdiv($totalPoints, 50));
            return [
                'reward_type' => 'discount',
                'confidence' => 0.75,
                'discount_percent' => $discount,
                'points_cost' => $discount * 10,
                'title' => "🏷️ -{$discount}% sur votre prochaine intervention",
                'description' => "Profitez d'une réduction de {$discount}% sur votre prochaine demande de service.",
            ];
        }
    }

    /**
     * PHP-based level calculator (fallback)
     */
    private function calculateLevelPHP(int $totalPoints): array
    {
        $levels = [
            ['name' => 'Bronze', 'emoji' => '🥉', 'min' => 0, 'max' => 199],
            ['name' => 'Argent', 'emoji' => '🥈', 'min' => 200, 'max' => 499],
            ['name' => 'Or', 'emoji' => '🥇', 'min' => 500, 'max' => 999],
            ['name' => 'Platine', 'emoji' => '💎', 'min' => 1000, 'max' => 2499],
            ['name' => 'Diamant', 'emoji' => '👑', 'min' => 2500, 'max' => PHP_INT_MAX],
        ];

        $current = $levels[0];
        $next = $levels[1] ?? null;

        foreach ($levels as $i => $level) {
            if ($totalPoints >= $level['min']) {
                $current = $level;
                $next = $levels[$i + 1] ?? null;
            }
        }

        $progress = 0;
        $pointsToNext = 0;
        if ($next) {
            $range = $next['min'] - $current['min'];
            $inLevel = $totalPoints - $current['min'];
            $progress = min(100, intdiv($inLevel * 100, $range));
            $pointsToNext = max(0, $next['min'] - $totalPoints);
        }

        return [
            'current_level' => $current['name'],
            'emoji' => $current['emoji'],
            'progress' => $progress,
            'points_to_next' => $pointsToNext,
            'next_level' => $next ? $next['name'] : null,
        ];
    }

    /**
     * Create a fallback reward when ML fails
     */
    private function createFallbackReward(User $senior, array $features, array $existingTypes = []): ?LoyaltyReward
    {
        $totalPoints = $features['total_points'] ?? 0;

        // Avoid creating a discount if one already exists
        if (in_array(LoyaltyReward::TYPE_DISCOUNT, $existingTypes)) {
            if (!in_array(LoyaltyReward::TYPE_FREE_MAINTENANCE, $existingTypes) && $totalPoints >= 300) {
                $reward = new LoyaltyReward();
                $reward->setSenior($senior)
                    ->setType(LoyaltyReward::TYPE_FREE_MAINTENANCE)
                    ->setTitle('🔧 Visite de maintenance offerte')
                    ->setDescription('Bénéficiez d\'une visite de maintenance préventive gratuite. Votre fidélité est récompensée !')
                    ->setPointsCost(350)
                    ->setDiscountPercent(100)
                    ->setMlConfidence(0.5)
                    ->setStatus(LoyaltyReward::STATUS_AVAILABLE);
                $this->em->persist($reward);
                $this->em->flush();
                return $reward;
            }
            return null;
        }

        $discount = min(20, 10 + intdiv($totalPoints, 100));

        $reward = new LoyaltyReward();
        $reward->setSenior($senior)
            ->setType(LoyaltyReward::TYPE_DISCOUNT)
            ->setTitle("🏷️ -{$discount}% sur votre prochaine intervention")
            ->setDescription("Profitez d'une réduction de {$discount}% sur votre prochaine demande de service. Valable pendant 90 jours.")
            ->setPointsCost($discount * 10)
            ->setDiscountPercent($discount)
            ->setMlConfidence(0.5)
            ->setStatus(LoyaltyReward::STATUS_AVAILABLE);

        $this->em->persist($reward);
        $this->em->flush();

        return $reward;
    }

    /**
     * Get the full reward catalog: what the user can redeem now + what they could unlock
     */
    public function getRewardCatalog(User $senior): array
    {
        $totalPoints = $this->getCachedTotalPoints($senior);

        $catalogData = $this->callMLPredictor([
            'action' => 'catalog',
            'total_points' => $totalPoints,
        ]);

        if (!$catalogData || isset($catalogData['error'])) {
            // PHP fallback catalog
            return $this->getFallbackCatalog($totalPoints);
        }

        return $catalogData;
    }

    /**
     * PHP fallback catalog when ML script is unavailable
     */
    private function getFallbackCatalog(int $totalPoints): array
    {
        $catalog = [
            ['type' => 'discount', 'tier' => 'bronze', 'title' => '🏷️ -10% sur votre prochaine intervention', 'description' => 'Réduction de 10% applicable sur n\'importe quelle demande de service.', 'points_cost' => 100, 'discount_percent' => 10, 'min_points' => 100],
            ['type' => 'discount', 'tier' => 'silver', 'title' => '🏷️ -15% sur votre prochaine intervention', 'description' => 'Réduction de 15% applicable sur n\'importe quelle demande de service.', 'points_cost' => 150, 'discount_percent' => 15, 'min_points' => 150],
            ['type' => 'discount', 'tier' => 'gold', 'title' => '🏷️ -25% sur votre prochaine intervention', 'description' => 'Réduction de 25% sur votre prochaine demande.', 'points_cost' => 250, 'discount_percent' => 25, 'min_points' => 250],
            ['type' => 'free_maintenance', 'tier' => 'gold', 'title' => '🔧 Visite de maintenance gratuite', 'description' => 'Visite préventive offerte par un technicien qualifié.', 'points_cost' => 350, 'discount_percent' => 100, 'min_points' => 350],
            ['type' => 'plan_upgrade', 'tier' => 'platinum', 'title' => '⭐ Upgrade abonnement 1 mois', 'description' => 'Passez au plan supérieur pendant 1 mois !', 'points_cost' => 500, 'discount_percent' => 0, 'min_points' => 500],
            ['type' => 'free_maintenance', 'tier' => 'platinum', 'title' => '🔧 Pack 3 maintenances gratuites', 'description' => '3 visites gratuites utilisables sur 6 mois.', 'points_cost' => 800, 'discount_percent' => 100, 'min_points' => 800],
            ['type' => 'plan_upgrade', 'tier' => 'diamond', 'title' => '👑 Upgrade abonnement 3 mois', 'description' => 'Plan supérieur pendant 3 mois entiers !', 'points_cost' => 1200, 'discount_percent' => 0, 'min_points' => 1200],
        ];

        $redeemable = [];
        $upcoming = [];
        foreach ($catalog as $item) {
            if ($totalPoints >= $item['min_points']) {
                $item['status'] = 'redeemable';
                $redeemable[] = $item;
            } else {
                $item['status'] = 'locked';
                $item['points_needed'] = $item['min_points'] - $totalPoints;
                $upcoming[] = $item;
            }
        }

        return [
            'catalog' => $catalog,
            'redeemable' => $redeemable,
            'upcoming' => $upcoming,
            'total_points' => $totalPoints,
        ];
    }

    // ─── Admin Methods ──────────────────────────────────────────────────

    /**
     * Get global loyalty statistics for admin dashboard
     */
    public function getAdminStats(): array
    {
        $rewardStats = $this->rewardRepo->getGlobalStats();
        $leaderboard = $this->pointRepo->getLeaderboard(10);

        return [
            'rewardStats' => $rewardStats,
            'leaderboard' => $leaderboard,
        ];
    }

    /**
     * Manually award points to a senior (admin)
     */
    public function adminAwardPoints(User $senior, int $points, string $description): LoyaltyPoint
    {
        return $this->awardBonusPoints($senior, $points, LoyaltyPoint::SOURCE_BONUS, null, $description);
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function formatPointsBySource(array $pointsBySource): array
    {
        $labels = [
            LoyaltyPoint::SOURCE_INTERVENTION => 'Interventions',
            LoyaltyPoint::SOURCE_SUBSCRIPTION => 'Abonnement',
            LoyaltyPoint::SOURCE_ACTIVITY => 'Activités',
            LoyaltyPoint::SOURCE_BONUS => 'Bonus',
        ];

        $formatted = [];
        foreach ($pointsBySource as $entry) {
            $source = $entry['source'];
            $formatted[] = [
                'source' => $source,
                'label' => $labels[$source] ?? $source,
                'total' => (int) $entry['total'],
            ];
        }

        return $formatted;
    }
}
