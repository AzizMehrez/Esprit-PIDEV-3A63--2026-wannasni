<?php

namespace App\Tests\Unit\Service;

use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Service\FeatureGateService;
use App\Service\SubscriptionService;
use PHPUnit\Framework\TestCase;

class FeatureGateServiceTest extends TestCase
{
    private SubscriptionService $subscriptionService;
    private FeatureGateService $featureGate;

    protected function setUp(): void
    {
        $this->subscriptionService = $this->createMock(SubscriptionService::class);
        $this->featureGate = new FeatureGateService($this->subscriptionService);
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    private function mockSubscription(string $planSlug): void
    {
        $plan = $this->createMock(SubscriptionPlan::class);
        $plan->method('getSlug')->willReturn($planSlug);

        $subscription = $this->createMock(Subscription::class);
        $subscription->method('getPlan')->willReturn($plan);

        $this->subscriptionService
            ->method('getActiveSubscription')
            ->willReturn($subscription);
    }

    // ─── hasFeature ──────────────────────────────────────────────────────

    public function testHasFeatureReturnsFalseWithoutSubscription(): void
    {
        $this->subscriptionService
            ->method('getActiveSubscription')
            ->willReturn(null);

        $user = $this->createMock(User::class);

        $this->assertFalse(
            $this->featureGate->hasFeature($user, FeatureGateService::FEATURE_DISCOUNT)
        );
    }

    public function testHasFeatureEssentielUnlocksDiscount(): void
    {
        $this->mockSubscription(SubscriptionPlan::PLAN_ESSENTIEL);
        $user = $this->createMock(User::class);

        $this->assertTrue(
            $this->featureGate->hasFeature($user, FeatureGateService::FEATURE_DISCOUNT)
        );
    }

    public function testHasFeatureEssentielLocksPremiumFeature(): void
    {
        $this->mockSubscription(SubscriptionPlan::PLAN_ESSENTIEL);
        $user = $this->createMock(User::class);

        $this->assertFalse(
            $this->featureGate->hasFeature($user, FeatureGateService::FEATURE_AI_IMAGE_DETECTION)
        );
    }

    public function testHasFeatureConfortUnlocksConfortAndBelow(): void
    {
        $this->mockSubscription(SubscriptionPlan::PLAN_CONFORT);
        $user = $this->createMock(User::class);

        // Confort features
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_PRIORITY_URGENCE));
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_EXPORT_PDF));
        // Essentiel feature still accessible
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_DISCOUNT));
        // Premium locked
        $this->assertFalse($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_TECHNICIEN_DEDIE));
    }

    public function testHasFeaturePremiumUnlocksAll(): void
    {
        $this->mockSubscription(SubscriptionPlan::PLAN_PREMIUM);
        $user = $this->createMock(User::class);

        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_DISCOUNT));
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_PRIORITY_URGENCE));
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_EXPORT_PDF));
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_TECHNICIEN_DEDIE));
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_PAIEMENT_LIGNE));
        $this->assertTrue($this->featureGate->hasFeature($user, FeatureGateService::FEATURE_AI_IMAGE_DETECTION));
    }

    public function testHasFeatureUnknownFeatureReturnsTrue(): void
    {
        $this->mockSubscription(SubscriptionPlan::PLAN_ESSENTIEL);
        $user = $this->createMock(User::class);

        $this->assertTrue(
            $this->featureGate->hasFeature($user, 'nonexistent_feature')
        );
    }

    // ─── getRequiredPlan ─────────────────────────────────────────────────

    public function testGetRequiredPlanReturnsCorrectPlans(): void
    {
        $this->assertSame('essentiel', $this->featureGate->getRequiredPlan(FeatureGateService::FEATURE_DISCOUNT));
        $this->assertSame('confort', $this->featureGate->getRequiredPlan(FeatureGateService::FEATURE_PRIORITY_URGENCE));
        $this->assertSame('confort', $this->featureGate->getRequiredPlan(FeatureGateService::FEATURE_EXPORT_PDF));
        $this->assertSame('premium', $this->featureGate->getRequiredPlan(FeatureGateService::FEATURE_TECHNICIEN_DEDIE));
        $this->assertSame('premium', $this->featureGate->getRequiredPlan(FeatureGateService::FEATURE_PAIEMENT_LIGNE));
        $this->assertSame('premium', $this->featureGate->getRequiredPlan(FeatureGateService::FEATURE_AI_IMAGE_DETECTION));
    }

    public function testGetRequiredPlanReturnsNullForUnknown(): void
    {
        $this->assertNull($this->featureGate->getRequiredPlan('nonexistent_feature'));
    }

    // ─── getRequiredPlanName ─────────────────────────────────────────────

    public function testGetRequiredPlanNameReturnsLabels(): void
    {
        $this->assertSame('Essentiel', $this->featureGate->getRequiredPlanName(FeatureGateService::FEATURE_DISCOUNT));
        $this->assertSame('Confort', $this->featureGate->getRequiredPlanName(FeatureGateService::FEATURE_PRIORITY_URGENCE));
        $this->assertSame('Premium', $this->featureGate->getRequiredPlanName(FeatureGateService::FEATURE_AI_IMAGE_DETECTION));
        $this->assertSame('—', $this->featureGate->getRequiredPlanName('nonexistent'));
    }

    // ─── getFeatureInfo ──────────────────────────────────────────────────

    public function testGetFeatureInfoReturnsLabelAndIcon(): void
    {
        $info = $this->featureGate->getFeatureInfo(FeatureGateService::FEATURE_AI_IMAGE_DETECTION);

        $this->assertSame('Détection IA par image', $info['label']);
        $this->assertArrayHasKey('description', $info);
        $this->assertArrayHasKey('icon', $info);
    }

    public function testGetFeatureInfoFallbackForUnknown(): void
    {
        $info = $this->featureGate->getFeatureInfo('xyz_unknown');

        $this->assertSame('xyz_unknown', $info['label']);
        $this->assertSame('🔒', $info['icon']);
    }

    // ─── getAllFeaturesStatus ─────────────────────────────────────────────

    public function testGetAllFeaturesStatusReturnsAllFeatures(): void
    {
        $this->mockSubscription(SubscriptionPlan::PLAN_CONFORT);
        $user = $this->createMock(User::class);

        $statuses = $this->featureGate->getAllFeaturesStatus($user);

        $this->assertCount(6, $statuses);

        // Each entry should have required keys
        foreach ($statuses as $status) {
            $this->assertArrayHasKey('id', $status);
            $this->assertArrayHasKey('label', $status);
            $this->assertArrayHasKey('unlocked', $status);
            $this->assertArrayHasKey('requiredPlan', $status);
            $this->assertArrayHasKey('requiredPlanName', $status);
        }
    }

    // ─── getFeaturesForPlan ──────────────────────────────────────────────

    public function testGetFeaturesForPlanEssentiel(): void
    {
        $features = $this->featureGate->getFeaturesForPlan(SubscriptionPlan::PLAN_ESSENTIEL);

        $ids = array_column($features, 'id');
        $this->assertContains('discount', $ids);
        // Premium features should NOT be included
        $this->assertNotContains('ai_image_detection', $ids);
    }

    public function testGetFeaturesForPlanPremiumIncludesAll(): void
    {
        $features = $this->featureGate->getFeaturesForPlan(SubscriptionPlan::PLAN_PREMIUM);

        $ids = array_column($features, 'id');
        $this->assertCount(6, $ids);
    }

    public function testGetFeaturesForPlanMarksIsNew(): void
    {
        $features = $this->featureGate->getFeaturesForPlan(SubscriptionPlan::PLAN_CONFORT);

        foreach ($features as $feature) {
            if ($feature['id'] === FeatureGateService::FEATURE_PRIORITY_URGENCE) {
                $this->assertTrue($feature['isNew'], 'priority_urgence should be isNew for confort');
            }
            if ($feature['id'] === FeatureGateService::FEATURE_DISCOUNT) {
                $this->assertFalse($feature['isNew'], 'discount should NOT be isNew for confort');
            }
        }
    }
}
