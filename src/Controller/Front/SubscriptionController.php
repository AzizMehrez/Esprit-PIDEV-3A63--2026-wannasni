<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\SubscriptionPlanRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Entity\LoyaltyPoint;
use App\Service\LoyaltyService;
use App\Service\SubscriptionService;
use App\Service\FeatureGateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/subscription', requirements: ['_locale' => 'fr|en|ar'])]
class SubscriptionController extends AbstractController
{
    public function __construct(
        private SubscriptionService $subscriptionService,
        private FeatureGateService $featureGateService,
        private SubscriptionRepository $subscriptionRepo,
        private SubscriptionPlanRepository $planRepo,
        private UserRepository $userRepo,
        private LoyaltyService $loyaltyService,
    ) {}

    // ─── Page principale : Mon Abonnement ───────────────────────────────

    #[Route('/', name: 'app_subscription')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $isFamily = in_array('ROLE_FAMILY', $user->getRoles());
        $isSenior = !$isFamily; // Tout utilisateur non-famille est considéré comme bénéficiaire

        // Bénéficiaire : voir son propre abonnement
        $subscription = null;
        $summary = ['hasSubscription' => false];
        if ($isSenior) {
            $subscription = $this->subscriptionRepo->findCurrentBySenior($user);
            $summary = $this->subscriptionService->getMonthlySummary($user);
        }

        // Famille : voir les abonnements souscrits
        $familySubscriptions = [];
        if ($isFamily) {
            $familySubscriptions = $this->subscriptionRepo->findBySubscriber($user);
        }

        $plans = $this->subscriptionService->getAvailablePlans();

        // Features status for current user
        $featuresStatus = $this->featureGateService->getAllFeaturesStatus($user);

        return $this->render('front/subscription/index.html.twig', [
            'subscription' => $subscription,
            'summary' => $summary,
            'plans' => $plans,
            'isSenior' => $isSenior,
            'isFamily' => $isFamily,
            'familySubscriptions' => $familySubscriptions,
            'featuresStatus' => $featuresStatus,
        ]);
    }

    // ─── Choisir un plan ────────────────────────────────────────────────

    #[Route('/plans', name: 'app_subscription_plans')]
    public function plans(): Response
    {
        $plans = $this->subscriptionService->getAvailablePlans();

        /** @var User $user */
        $user = $this->getUser();
        $isFamily = in_array('ROLE_FAMILY', $user->getRoles());
        $isSenior = !$isFamily;

        // Current plan si le bénéficiaire est déjà abonné
        $currentPlan = null;
        if ($isSenior) {
            $currentSub = $this->subscriptionRepo->findCurrentBySenior($user);
            if ($currentSub) {
                $currentPlan = $currentSub->getPlan();
            }
        }

        // Trouver les seniors pour la famille
        $seniors = [];
        if ($isFamily) {
            $seniors = $this->userRepo->findByRole('ROLE_SENIOR');
        }

        // Build features map per plan
        $planFeatures = [];
        foreach ($plans as $plan) {
            $planFeatures[$plan->getSlug()] = $this->featureGateService->getFeaturesForPlan($plan->getSlug());
        }

        return $this->render('front/subscription/plans.html.twig', [
            'plans' => $plans,
            'isFamily' => $isFamily,
            'seniors' => $seniors,
            'currentPlan' => $currentPlan,
            'planFeatures' => $planFeatures,
        ]);
    }

    // ─── Souscrire ──────────────────────────────────────────────────────

    // Cartes Stripe de test acceptées (numéros sans espaces)
    private const VALID_TEST_CARDS = [
        '4242424242424242', // Visa
        '4000056655665556', // Visa (debit)
        '5555555555554444', // Mastercard
        '5200828282828210', // Mastercard (debit)
        '378282246310005',  // American Express
        '371449635398431',  // American Express
        '6011111111111117', // Discover
        '3056930009020004', // Diners Club
        '3566002020360505', // JCB
        '6200000000000005', // UnionPay
    ];

    #[Route('/subscribe/{planSlug}', name: 'app_subscription_subscribe', methods: ['POST'])]
    public function subscribe(string $planSlug, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $plan = $this->planRepo->findBySlug($planSlug);
        if (!$plan) {
            $this->addFlash('error', 'Plan introuvable.');
            return $this->redirectToRoute('app_subscription_plans', ['_locale' => $request->getLocale()]);
        }

        // ── Validation carte bancaire Stripe ──
        $cardNumber = preg_replace('/\s+/', '', $request->request->get('card_number', ''));
        $cardExpiry = $request->request->get('card_expiry', '');
        $cardCvv = $request->request->get('card_cvv', '');
        $cardHolder = trim($request->request->get('card_holder', ''));

        $cardErrors = [];

        // Nom du titulaire
        if (empty($cardHolder) || strlen($cardHolder) < 3) {
            $cardErrors[] = 'Le nom du titulaire est requis (minimum 3 caractères).';
        }

        // Numéro de carte — doit être un numéro Stripe test valide
        if (empty($cardNumber)) {
            $cardErrors[] = 'Le numéro de carte est requis.';
        } elseif (!in_array($cardNumber, self::VALID_TEST_CARDS)) {
            $cardErrors[] = 'Numéro de carte invalide. Utilisez une carte de test Stripe (ex: 4242 4242 4242 4242).';
        }

        // Expiration — format MM/AA, non expirée
        if (empty($cardExpiry) || !preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $cardExpiry, $expiryMatch)) {
            $cardErrors[] = 'Date d\'expiration invalide (format MM/AA requis).';
        } else {
            $expiryMonth = (int)$expiryMatch[1];
            $expiryYear = 2000 + (int)$expiryMatch[2];
            $now = new \DateTime();
            $expiry = \DateTime::createFromFormat('Y-m-d', "$expiryYear-$expiryMonth-01");
            $expiry->modify('last day of this month');
            if ($expiry < $now) {
                $cardErrors[] = 'La carte est expirée.';
            }
        }

        // CVV — 3 ou 4 chiffres
        if (empty($cardCvv) || !preg_match('/^[0-9]{3,4}$/', $cardCvv)) {
            $cardErrors[] = 'CVV invalide (3 ou 4 chiffres requis).';
        }

        if (!empty($cardErrors)) {
            foreach ($cardErrors as $err) {
                $this->addFlash('error', $err);
            }
            return $this->redirectToRoute('app_subscription_checkout', [
                '_locale' => $request->getLocale(),
                'planSlug' => $planSlug,
            ]);
        }

        // Déterminer le senior bénéficiaire
        $isFamily = in_array('ROLE_FAMILY', $user->getRoles());
        $senior = $user; // Par défaut, le senior est l'utilisateur

        if ($isFamily) {
            $seniorId = $request->request->get('senior_id');
            if (!$seniorId) {
                $this->addFlash('error', 'Veuillez sélectionner un senior bénéficiaire.');
                return $this->redirectToRoute('app_subscription_checkout', [
                    '_locale' => $request->getLocale(),
                    'planSlug' => $planSlug,
                ]);
            }
            $senior = $this->userRepo->find($seniorId);
            if (!$senior) {
                $this->addFlash('error', 'Senior introuvable.');
                return $this->redirectToRoute('app_subscription_plans', ['_locale' => $request->getLocale()]);
            }
        }

        // Vérifier si le senior a déjà un abonnement actif
        $existingActive = $this->subscriptionRepo->findActiveBySenior($senior);
        if ($existingActive) {
            $this->addFlash('error', sprintf(
                'Un abonnement %s est déjà actif. Annulez-le ou changez de plan depuis votre espace abonnement.',
                $existingActive->getPlan()->getName()
            ));
            return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
        }

        try {
            $subscription = $this->subscriptionService->subscribe($senior, $user, $plan);

            // Simuler un ID Stripe pour le paiement
            $stripeSimId = 'sub_sim_' . bin2hex(random_bytes(12));
            $subscription->setStripeSubscriptionId($stripeSimId);
            $subscription->setStripeCustomerId('cus_sim_' . bin2hex(random_bytes(8)));
            $this->subscriptionRepo->getEntityManager()->flush();

            // ── Auto-award loyalty points on subscription ──
            try {
                $this->loyaltyService->awardBonusPoints(
                    $senior,
                    100,
                    LoyaltyPoint::SOURCE_SUBSCRIPTION,
                    $subscription->getId(),
                    sprintf('Bonus fidélité : souscription abonnement %s', $plan->getName())
                );
            } catch (\Exception $e) {
                // Loyalty error should not block subscription
            }

            $this->addFlash('success', sprintf(
                '✅ Paiement accepté ! Abonnement %s activé. Vous bénéficiez de %d%% de réduction sur toutes vos interventions.',
                $plan->getName(),
                $plan->getDiscountPercent()
            ));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
    }

    // ─── Changer de plan ────────────────────────────────────────────────

    #[Route('/change-plan/{planSlug}', name: 'app_subscription_change', methods: ['POST'])]
    public function changePlan(string $planSlug, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $newPlan = $this->planRepo->findBySlug($planSlug);
        if (!$newPlan) {
            $this->addFlash('error', 'Plan introuvable.');
            return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
        }

        // Pour la famille, récupérer le senior depuis le paramètre
        $isFamily = in_array('ROLE_FAMILY', $user->getRoles());
        $senior = $user;
        if ($isFamily) {
            $seniorId = $request->request->get('senior_id');
            $senior = $seniorId ? $this->userRepo->find($seniorId) : $user;
        }

        $subscription = $this->subscriptionRepo->findActiveBySenior($senior);
        if (!$subscription) {
            $this->addFlash('error', 'Aucun abonnement actif trouvé.');
            return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
        }

        try {
            $this->subscriptionService->changePlan($subscription, $newPlan);
            $this->addFlash('success', sprintf('Plan changé en %s avec succès.', $newPlan->getName()));
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
    }

    // ─── Suspendre ──────────────────────────────────────────────────────

    #[Route('/suspend', name: 'app_subscription_suspend', methods: ['POST'])]
    public function suspend(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $senior = $user;
        if (in_array('ROLE_FAMILY', $user->getRoles())) {
            $seniorId = $request->request->get('senior_id');
            $senior = $seniorId ? $this->userRepo->find($seniorId) : $user;
        }

        $subscription = $this->subscriptionRepo->findActiveBySenior($senior);
        if ($subscription) {
            $this->subscriptionService->suspendSubscription($subscription);
            $this->addFlash('info', 'Abonnement suspendu.');
        }

        return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
    }

    // ─── Annuler ────────────────────────────────────────────────────────

    #[Route('/cancel', name: 'app_subscription_cancel', methods: ['POST'])]
    public function cancel(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $senior = $user;
        if (in_array('ROLE_FAMILY', $user->getRoles())) {
            $seniorId = $request->request->get('senior_id');
            $senior = $seniorId ? $this->userRepo->find($seniorId) : $user;
        }

        $subscription = $this->subscriptionRepo->findCurrentBySenior($senior);
        if ($subscription) {
            $this->subscriptionService->cancelSubscription($subscription);
            $this->addFlash('info', 'Abonnement annulé. Vos réductions ne seront plus appliquées.');
        }

        return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
    }

    // ─── Réactiver ──────────────────────────────────────────────────────

    #[Route('/reactivate', name: 'app_subscription_reactivate', methods: ['POST'])]
    public function reactivate(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $senior = $user;
        if (in_array('ROLE_FAMILY', $user->getRoles())) {
            $seniorId = $request->request->get('senior_id');
            $senior = $seniorId ? $this->userRepo->find($seniorId) : $user;
        }

        $subscription = $this->subscriptionRepo->findCurrentBySenior($senior);
        if ($subscription && $subscription->isSuspended()) {
            $this->subscriptionService->reactivateSubscription($subscription);
            $this->addFlash('success', 'Abonnement réactivé avec succès !');
        }

        return $this->redirectToRoute('app_subscription', ['_locale' => $request->getLocale()]);
    }

    // ─── Stripe Checkout (simulation) ───────────────────────────────────

    #[Route('/checkout/{planSlug}', name: 'app_subscription_checkout')]
    public function checkout(string $planSlug): Response
    {
        $plan = $this->planRepo->findBySlug($planSlug);
        if (!$plan) {
            throw $this->createNotFoundException('Plan introuvable');
        }

        /** @var User $user */
        $user = $this->getUser();
        $isFamily = in_array('ROLE_FAMILY', $user->getRoles());
        $isSenior = !$isFamily;
        $seniors = $isFamily ? $this->userRepo->findByRole('ROLE_SENIOR') : [];

        // Vérifier si le bénéficiaire a déjà un abonnement actif
        $currentSubscription = null;
        if ($isSenior) {
            $currentSubscription = $this->subscriptionRepo->findActiveBySenior($user);
        }

        return $this->render('front/subscription/checkout.html.twig', [
            'plan' => $plan,
            'isFamily' => $isFamily,
            'seniors' => $seniors,
            'currentSubscription' => $currentSubscription,
        ]);
    }

    // ─── Webhook Stripe ─────────────────────────────────────────────────

    #[Route('/webhook/stripe', name: 'app_subscription_stripe_webhook', methods: ['POST'])]
    public function stripeWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $data = json_decode($payload, true);

        if (!$data || !isset($data['type'])) {
            return new JsonResponse(['error' => 'Invalid payload'], 400);
        }

        $stripeSubId = $data['data']['object']['subscription'] ?? ($data['data']['object']['id'] ?? null);

        switch ($data['type']) {
            case 'invoice.payment_succeeded':
                if ($stripeSubId) {
                    $this->subscriptionService->handlePaymentSuccess($stripeSubId);
                }
                break;

            case 'invoice.payment_failed':
                if ($stripeSubId) {
                    $this->subscriptionService->handlePaymentFailed($stripeSubId);
                }
                break;

            case 'customer.subscription.deleted':
                $subId = $data['data']['object']['id'] ?? null;
                if ($subId) {
                    $sub = $this->subscriptionRepo->findByStripeSubscriptionId($subId);
                    if ($sub) {
                        $this->subscriptionService->cancelSubscription($sub);
                    }
                }
                break;
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
