<?php

namespace App\Service;

use App\Entity\Subscription;
use App\Entity\SubscriptionPlan;
use App\Entity\User;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SubscriptionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private SubscriptionRepository $subscriptionRepo,
        private SubscriptionPlanRepository $planRepo,
        private LoggerInterface $logger,
    ) {}

    // ─── Plans ──────────────────────────────────────────────────────────

    /**
     * Retourne tous les plans actifs triés
     * @return SubscriptionPlan[]
     */
    public function getAvailablePlans(): array
    {
        return $this->planRepo->findAllActive();
    }

    /**
     * Initialise les 3 plans en base (à exécuter une fois)
     */
    public function seedPlans(): void
    {
        $plans = [
            [
                'slug' => SubscriptionPlan::PLAN_ESSENTIEL,
                'name' => 'Essentiel',
                'price' => '9.99',
                'discount' => 10,
                'maintenances' => 1,
                'priorite' => false,
                'technicien' => false,
                'description' => '10% de réduction sur toutes les interventions + 1 maintenance préventive par an.',
                'order' => 1,
            ],
            [
                'slug' => SubscriptionPlan::PLAN_CONFORT,
                'name' => 'Confort',
                'price' => '19.99',
                'discount' => 20,
                'maintenances' => 2,
                'priorite' => true,
                'technicien' => false,
                'description' => '20% de réduction + 2 maintenances/an + traitement prioritaire des urgences.',
                'order' => 2,
            ],
            [
                'slug' => SubscriptionPlan::PLAN_PREMIUM,
                'name' => 'Premium',
                'price' => '34.99',
                'discount' => 30,
                'maintenances' => 12,
                'priorite' => true,
                'technicien' => true,
                'description' => '30% de réduction + maintenance mensuelle + technicien dédié attitré.',
                'order' => 3,
            ],
        ];

        foreach ($plans as $data) {
            $existing = $this->planRepo->findBySlug($data['slug']);
            if ($existing) {
                continue;
            }
            $plan = new SubscriptionPlan();
            $plan->setSlug($data['slug'])
                ->setName($data['name'])
                ->setPriceMonthly($data['price'])
                ->setDiscountPercent($data['discount'])
                ->setMaintenancesPerYear($data['maintenances'])
                ->setPrioriteUrgences($data['priorite'])
                ->setTechnicienDedie($data['technicien'])
                ->setDescription($data['description'])
                ->setSortOrder($data['order']);

            $this->em->persist($plan);
        }
        $this->em->flush();
    }

    // ─── Souscription ───────────────────────────────────────────────────

    /**
     * Souscrit un abonnement pour un senior.
     * Le subscriber peut être le senior lui-même ou un membre de la famille.
     */
    public function subscribe(User $senior, User $subscriber, SubscriptionPlan $plan): Subscription
    {
        // Vérifier qu'il n'y a pas déjà un abonnement actif
        $existing = $this->subscriptionRepo->findCurrentBySenior($senior);
        if ($existing && $existing->isActive()) {
            throw new \LogicException('Ce senior a déjà un abonnement actif. Annulez-le d\'abord ou changez de plan.');
        }

        // Si abonnement en attente/past_due, l'annuler
        if ($existing) {
            $existing->cancel();
        }

        $subscription = new Subscription();
        $subscription->setSenior($senior)
            ->setSubscriber($subscriber)
            ->setPlan($plan)
            ->activate();

        $this->em->persist($subscription);
        $this->em->flush();

        $this->logger->info('Subscription created', [
            'senior' => $senior->getEmail(),
            'subscriber' => $subscriber->getEmail(),
            'plan' => $plan->getSlug(),
        ]);

        return $subscription;
    }

    /**
     * Change le plan d'un abonnement actif
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan): Subscription
    {
        if (!$subscription->isActive()) {
            throw new \LogicException('Impossible de changer le plan d\'un abonnement inactif.');
        }

        $oldPlan = $subscription->getPlan()->getSlug();
        $subscription->setPlan($newPlan);
        $this->em->flush();

        $this->logger->info('Subscription plan changed', [
            'id' => $subscription->getId(),
            'from' => $oldPlan,
            'to' => $newPlan->getSlug(),
        ]);

        return $subscription;
    }

    /**
     * Suspend un abonnement
     */
    public function suspendSubscription(Subscription $subscription): void
    {
        $subscription->suspend();
        $this->em->flush();

        $this->logger->info('Subscription suspended', ['id' => $subscription->getId()]);
    }

    /**
     * Annule un abonnement
     */
    public function cancelSubscription(Subscription $subscription): void
    {
        $subscription->cancel();
        $this->em->flush();

        $this->logger->info('Subscription cancelled', ['id' => $subscription->getId()]);
    }

    /**
     * Réactive un abonnement suspendu (après paiement réussi)
     */
    public function reactivateSubscription(Subscription $subscription): void
    {
        $subscription->activate();
        $this->em->flush();

        $this->logger->info('Subscription reactivated', ['id' => $subscription->getId()]);
    }

    // ─── Réductions ─────────────────────────────────────────────────────

    /**
     * Retourne l'abonnement actif d'un senior, ou null
     */
    public function getActiveSubscription(User $senior): ?Subscription
    {
        return $this->subscriptionRepo->findActiveBySenior($senior);
    }

    /**
     * Prévisualise la réduction sans enregistrer (pour affichage uniquement)
     */
    public function previewDiscount(User $senior, float $amount): array
    {
        $subscription = $this->getActiveSubscription($senior);
        if (!$subscription) {
            return [
                'original' => $amount,
                'discount' => 0,
                'discountPercent' => 0,
                'final' => $amount,
                'hasSubscription' => false,
                'planName' => null,
            ];
        }

        $discountAmount = $subscription->calculateDiscount($amount);
        $finalAmount = $amount - $discountAmount;

        return [
            'original' => $amount,
            'discount' => round($discountAmount, 2),
            'discountPercent' => $subscription->getPlan()->getDiscountPercent(),
            'final' => round($finalAmount, 2),
            'hasSubscription' => true,
            'planName' => $subscription->getPlan()->getName(),
        ];
    }

    /**
     * Calcule le montant après réduction abonné
     * Retourne [original, discount, final]
     */
    public function applyDiscount(User $senior, float $amount): array
    {
        $subscription = $this->getActiveSubscription($senior);
        if (!$subscription) {
            return [
                'original' => $amount,
                'discount' => 0,
                'discountPercent' => 0,
                'final' => $amount,
                'hasSubscription' => false,
                'planName' => null,
            ];
        }

        $discountAmount = $subscription->calculateDiscount($amount);
        $finalAmount = $amount - $discountAmount;

        // Enregistrer l'économie
        $subscription->addSaving($discountAmount);
        $this->em->flush();

        return [
            'original' => $amount,
            'discount' => round($discountAmount, 2),
            'discountPercent' => $subscription->getPlan()->getDiscountPercent(),
            'final' => round($finalAmount, 2),
            'hasSubscription' => true,
            'planName' => $subscription->getPlan()->getName(),
        ];
    }

    // ─── Stripe Webhook Handlers ────────────────────────────────────────

    /**
     * Gère un paiement réussi (webhook Stripe)
     */
    public function handlePaymentSuccess(string $stripeSubscriptionId): void
    {
        $subscription = $this->subscriptionRepo->findByStripeSubscriptionId($stripeSubscriptionId);
        if (!$subscription) {
            $this->logger->warning('Stripe payment success: subscription not found', ['stripe_id' => $stripeSubscriptionId]);
            return;
        }

        $subscription->setFailedPaymentAttempts(0);
        if ($subscription->getStatus() !== Subscription::STATUS_ACTIVE) {
            $subscription->activate();
        }
        $subscription->setNextBillingDate(new \DateTime('+1 month'));
        $this->em->flush();
    }

    /**
     * Gère un échec de paiement (webhook Stripe)
     */
    public function handlePaymentFailed(string $stripeSubscriptionId): void
    {
        $subscription = $this->subscriptionRepo->findByStripeSubscriptionId($stripeSubscriptionId);
        if (!$subscription) {
            return;
        }

        $subscription->incrementFailedPayments();
        $this->em->flush();

        $this->logger->warning('Stripe payment failed', [
            'id' => $subscription->getId(),
            'attempts' => $subscription->getFailedPaymentAttempts(),
            'suspended' => $subscription->isSuspended(),
        ]);
    }

    // ─── Stats ──────────────────────────────────────────────────────────

    /**
     * Récapitulatif mensuel pour un senior
     */
    public function getMonthlySummary(User $senior): array
    {
        $subscription = $this->getActiveSubscription($senior);
        if (!$subscription) {
            return ['hasSubscription' => false];
        }

        return [
            'hasSubscription' => true,
            'planName' => $subscription->getPlan()->getName(),
            'planSlug' => $subscription->getPlan()->getSlug(),
            'discountPercent' => $subscription->getPlan()->getDiscountPercent(),
            'totalSaved' => (float) $subscription->getTotalSaved(),
            'maintenancesUsed' => $subscription->getMaintenancesUsed(),
            'maintenancesTotal' => $subscription->getPlan()->getMaintenancesPerYear(),
            'maintenancesRemaining' => $subscription->getMaintenancesRemaining(),
            'nextBilling' => $subscription->getNextBillingDate(),
            'startDate' => $subscription->getStartDate(),
            'status' => $subscription->getStatus(),
        ];
    }
}
