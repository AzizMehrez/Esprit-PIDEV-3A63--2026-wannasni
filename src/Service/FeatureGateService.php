<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\SubscriptionPlan;

/**
 * Service de gating des fonctionnalités selon l'abonnement
 * 
 * Définit quelles fonctionnalités sont accessibles pour chaque plan.
 * Sans abonnement, certaines fonctionnalités sont verrouillées.
 */
class FeatureGateService
{
    // ─── Feature identifiers ────────────────────────────────────────────
    public const FEATURE_AI_IMAGE_DETECTION = 'ai_image_detection';
    public const FEATURE_PRIORITY_URGENCE   = 'priority_urgence';
    public const FEATURE_TECHNICIEN_DEDIE   = 'technicien_dedie';
    public const FEATURE_EXPORT_PDF         = 'export_pdf_devis';
    public const FEATURE_PAIEMENT_LIGNE     = 'paiement_en_ligne';
    public const FEATURE_DISCOUNT           = 'discount';

    // ─── Map feature → minimum plan required ────────────────────────────
    // Plans ordered: essentiel < confort < premium
    private const FEATURE_PLAN_MAP = [
        self::FEATURE_DISCOUNT           => SubscriptionPlan::PLAN_ESSENTIEL,
        self::FEATURE_PRIORITY_URGENCE   => SubscriptionPlan::PLAN_CONFORT,
        self::FEATURE_EXPORT_PDF         => SubscriptionPlan::PLAN_CONFORT,
        self::FEATURE_TECHNICIEN_DEDIE   => SubscriptionPlan::PLAN_PREMIUM,
        self::FEATURE_PAIEMENT_LIGNE     => SubscriptionPlan::PLAN_PREMIUM,
        self::FEATURE_AI_IMAGE_DETECTION => SubscriptionPlan::PLAN_PREMIUM,
    ];

    // ─── Feature labels & descriptions (for UI) ────────────────────────
    private const FEATURE_INFO = [
        self::FEATURE_AI_IMAGE_DETECTION => [
            'label' => 'Détection IA par image',
            'description' => 'Analysez une photo pour détecter automatiquement le type de service nécessaire.',
            'icon' => '🤖',
        ],
        self::FEATURE_PRIORITY_URGENCE => [
            'label' => 'Priorité urgences',
            'description' => 'Vos demandes urgentes sont traitées en priorité.',
            'icon' => '🚨',
        ],
        self::FEATURE_TECHNICIEN_DEDIE => [
            'label' => 'Technicien dédié',
            'description' => 'Un technicien personnel vous est affecté pour toutes vos interventions.',
            'icon' => '👨‍🔧',
        ],
        self::FEATURE_EXPORT_PDF => [
            'label' => 'Export PDF devis',
            'description' => 'Téléchargez vos devis au format PDF.',
            'icon' => '📄',
        ],
        self::FEATURE_PAIEMENT_LIGNE => [
            'label' => 'Paiement en ligne',
            'description' => 'Payez vos interventions directement en ligne.',
            'icon' => '💳',
        ],
        self::FEATURE_DISCOUNT => [
            'label' => 'Réduction sur interventions',
            'description' => 'Bénéficiez d\'une réduction sur toutes vos interventions.',
            'icon' => '💰',
        ],
    ];

    // Plan hierarchy for comparison
    private const PLAN_ORDER = [
        SubscriptionPlan::PLAN_ESSENTIEL => 1,
        SubscriptionPlan::PLAN_CONFORT   => 2,
        SubscriptionPlan::PLAN_PREMIUM   => 3,
    ];

    public function __construct(
        private SubscriptionService $subscriptionService,
    ) {}

    /**
     * Check if a user has access to a specific feature
     */
    public function hasFeature(User $user, string $featureId): bool
    {
        $subscription = $this->subscriptionService->getActiveSubscription($user);
        if (!$subscription) {
            return false;
        }

        $requiredPlan = self::FEATURE_PLAN_MAP[$featureId] ?? null;
        if (!$requiredPlan) {
            return true; // Feature unknown = open
        }

        $userPlanSlug = $subscription->getPlan()->getSlug();
        $userLevel = self::PLAN_ORDER[$userPlanSlug] ?? 0;
        $requiredLevel = self::PLAN_ORDER[$requiredPlan] ?? 0;

        return $userLevel >= $requiredLevel;
    }

    /**
     * Get the minimum plan slug required for a feature
     */
    public function getRequiredPlan(string $featureId): ?string
    {
        return self::FEATURE_PLAN_MAP[$featureId] ?? null;
    }

    /**
     * Get the display name of the minimum required plan
     */
    public function getRequiredPlanName(string $featureId): string
    {
        $slug = self::FEATURE_PLAN_MAP[$featureId] ?? null;
        return match ($slug) {
            SubscriptionPlan::PLAN_ESSENTIEL => 'Essentiel',
            SubscriptionPlan::PLAN_CONFORT   => 'Confort',
            SubscriptionPlan::PLAN_PREMIUM   => 'Premium',
            default => '—',
        };
    }

    /**
     * Get feature info (label, description, icon)
     */
    public function getFeatureInfo(string $featureId): array
    {
        return self::FEATURE_INFO[$featureId] ?? [
            'label' => $featureId,
            'description' => '',
            'icon' => '🔒',
        ];
    }

    /**
     * Get all features with lock status for a given user
     */
    public function getAllFeaturesStatus(User $user): array
    {
        $result = [];
        foreach (self::FEATURE_PLAN_MAP as $featureId => $requiredPlan) {
            $info = $this->getFeatureInfo($featureId);
            $result[] = [
                'id' => $featureId,
                'label' => $info['label'],
                'description' => $info['description'],
                'icon' => $info['icon'],
                'unlocked' => $this->hasFeature($user, $featureId),
                'requiredPlan' => $requiredPlan,
                'requiredPlanName' => $this->getRequiredPlanName($featureId),
            ];
        }
        return $result;
    }

    /**
     * Get features unlocked by a specific plan
     */
    public function getFeaturesForPlan(string $planSlug): array
    {
        $planLevel = self::PLAN_ORDER[$planSlug] ?? 0;
        $result = [];

        foreach (self::FEATURE_PLAN_MAP as $featureId => $requiredPlan) {
            $requiredLevel = self::PLAN_ORDER[$requiredPlan] ?? 0;
            if ($planLevel >= $requiredLevel) {
                $info = $this->getFeatureInfo($featureId);
                $result[] = [
                    'id' => $featureId,
                    'label' => $info['label'],
                    'icon' => $info['icon'],
                    'isNew' => ($requiredPlan === $planSlug), // newly unlocked at this plan level
                ];
            }
        }

        return $result;
    }
}
