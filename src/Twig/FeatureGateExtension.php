<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\FeatureGateService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig pour le système de gating des fonctionnalités
 * 
 * Fonctions disponibles dans les templates :
 * - has_feature('ai_image_detection')  → true/false
 * - feature_plan('ai_image_detection') → 'Premium'
 * - feature_info('ai_image_detection') → {label, description, icon}
 */
class FeatureGateExtension extends AbstractExtension
{
    public function __construct(
        private FeatureGateService $featureGate,
        private Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('has_feature', [$this, 'hasFeature']),
            new TwigFunction('feature_plan', [$this, 'featurePlan']),
            new TwigFunction('feature_info', [$this, 'featureInfo']),
        ];
    }

    public function hasFeature(string $featureId): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return $this->featureGate->hasFeature($user, $featureId);
    }

    public function featurePlan(string $featureId): string
    {
        return $this->featureGate->getRequiredPlanName($featureId);
    }

    public function featureInfo(string $featureId): array
    {
        return $this->featureGate->getFeatureInfo($featureId);
    }
}
