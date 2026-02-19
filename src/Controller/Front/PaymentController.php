<?php

namespace App\Controller\Front;

use App\Entity\Intervention;
use App\Repository\InterventionRepository;
use App\Service\LoyaltyService;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/payment')]
class PaymentController extends AbstractController
{
    public function __construct(
        private LoyaltyService $loyaltyService,
        private SubscriptionService $subscriptionService,
    ) {}
    /**
     * Display payment checkout page
     */
    #[Route('/checkout/{id}', name: 'payment_checkout', requirements: ['id' => '\d+'])]
    public function checkout(int $id, InterventionRepository $repo): Response
    {
        $intervention = $repo->find($id);
        
        // Validation
        if (!$intervention) {
            throw $this->createNotFoundException('Intervention non trouvée');
        }
        
        // If already paid, redirect to success page
        if ($intervention->getPaymentStatus() === 'paid') {
            return $this->redirectToRoute('payment_success', ['id' => $id, '_locale' => $this->getParameter('kernel.default_locale')]);
        }
        
        // Calculate total amount
        $total = ($intervention->getHeuresTravail() ?? 2) *
                 floatval($intervention->getTarifHoraire() ?? 25.00);

        // Apply subscription discount if the senior has an active plan
        $discountInfo = null;
        $senior = $intervention->getServiceRequest()?->getUser();
        if ($senior) {
            try {
                $discountInfo = $this->subscriptionService->previewDiscount($senior, $total);
            } catch (\Exception $e) {
                // non-blocking
            }
        }
        $amountToPay = ($discountInfo && $discountInfo['hasSubscription'] && $discountInfo['discountPercent'] > 0)
            ? $discountInfo['final']
            : $total;

        return $this->render('front/payment/checkout.html.twig', [
            'intervention' => $intervention,
            'total'        => $total,
            'discountInfo' => $discountInfo,
            'amountToPay'  => $amountToPay,
        ]);
    }

    /**
     * Process payment
     */
    #[Route('/process/{id}', name: 'payment_process', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function process(
        int $id,
        Request $request,
        InterventionRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $intervention = $repo->find($id);
        
        if (!$intervention) {
            throw $this->createNotFoundException('Intervention non trouvée');
        }
        
        // Check if already paid
        if ($intervention->getPaymentStatus() === 'paid') {
            return $this->redirectToRoute('payment_success', ['id' => $id, '_locale' => $this->getParameter('kernel.default_locale')]);
        }
        
        // TODO: Real Stripe payment processing will go here
        // For now, we'll simulate a successful payment
        
        try {
            // Simulate payment processing
            // In production, this would be replaced with actual Stripe API calls
            
            // Update intervention payment status
            $intervention->setPaymentStatus('paid');
            $intervention->setPaymentDate(new \DateTime());
            $intervention->setPaymentMethod('online');
            
            $em->flush();

            // ── Auto-award loyalty points on successful payment ──
            if ($intervention->getServiceRequest() && $intervention->getServiceRequest()->getUser()) {
                $senior = $intervention->getServiceRequest()->getUser();
                try {
                    $this->loyaltyService->awardInterventionPoints($senior, $intervention);
                } catch (\Exception $e) {
                    // Loyalty error should not block payment success
                }
            }
            
            $this->addFlash('success', 'Paiement effectué avec succès!');
            
            return $this->redirectToRoute('payment_success', ['id' => $id, '_locale' => $this->getParameter('kernel.default_locale')]);
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors du traitement du paiement. Veuillez réessayer.');
            return $this->redirectToRoute('payment_checkout', ['id' => $id, '_locale' => $this->getParameter('kernel.default_locale')]);
        }
    }

    /**
     * Payment success page
     */
    #[Route('/success/{id}', name: 'payment_success', requirements: ['id' => '\d+'])]
    public function success(int $id, InterventionRepository $repo): Response
    {
        $intervention = $repo->find($id);
        
        if (!$intervention) {
            throw $this->createNotFoundException('Intervention non trouvée');
        }
        
        // If not paid yet, redirect to checkout
        if ($intervention->getPaymentStatus() !== 'paid') {
            return $this->redirectToRoute('payment_checkout', ['id' => $id, '_locale' => $this->getParameter('kernel.default_locale')]);
        }
        
        // Calculate total for display
        $total = ($intervention->getHeuresTravail() ?? 2) *
                 floatval($intervention->getTarifHoraire() ?? 25.00);

        $discountInfo = null;
        $senior = $intervention->getServiceRequest()?->getUser();
        if ($senior) {
            try {
                $discountInfo = $this->subscriptionService->previewDiscount($senior, $total);
            } catch (\Exception $e) {
                // non-blocking
            }
        }
        $amountPaid = ($discountInfo && $discountInfo['hasSubscription'] && $discountInfo['discountPercent'] > 0)
            ? $discountInfo['final']
            : $total;

        return $this->render('front/payment/success.html.twig', [
            'intervention' => $intervention,
            'total'        => $total,
            'discountInfo' => $discountInfo,
            'amountPaid'   => $amountPaid,
        ]);
    }

    /**
     * Generate and download invoice PDF
     */
    #[Route('/invoice/{id}', name: 'payment_invoice', requirements: ['id' => '\d+'])]
    public function invoice(int $id, InterventionRepository $repo): Response
    {
        $intervention = $repo->find($id);
        
        if (!$intervention || $intervention->getPaymentStatus() !== 'paid') {
            throw $this->createNotFoundException('Facture non disponible');
        }
        
        // TODO: Generate actual PDF invoice
        // For now, return a simple PDF or redirect
        // You can use InterventionPdfGeneratorService or similar
        
        // Temporary: return a text response
        return new Response(
            'Invoice for Intervention #' . $intervention->getId() . ' - PDF generation to be implemented',
            200,
            ['Content-Type' => 'text/plain']
        );
    }
}

