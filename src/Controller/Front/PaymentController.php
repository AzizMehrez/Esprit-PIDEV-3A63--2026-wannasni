<?php

namespace App\Controller\Front;

use App\Entity\Intervention;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payment')]
class PaymentController extends AbstractController
{
    #[Route('/checkout/{id}', name: 'payment_checkout')]
    public function checkout(Intervention $intervention): Response
    {
        if ($intervention->getPaymentStatus() === 'paid') {
            $this->addFlash('info', 'Cette intervention a déjà été payée.');
            return $this->redirectToRoute('app_home_redirect'); // Or dashboard
        }

        // Calculate total if not stored
        $total = ($intervention->getHeuresTravail() ?? 0) * ($intervention->getTarifHoraire() ?? 0);

        return $this->render('front/payment/checkout.html.twig', [
            'intervention' => $intervention,
            'total' => $total
        ]);
    }

    #[Route('/process/{id}', name: 'payment_process', methods: ['POST'])]
    public function process(Intervention $intervention, EntityManagerInterface $em): Response
    {
        if ($intervention->getPaymentStatus() === 'paid') {
            return $this->redirectToRoute('payment_checkout', ['id' => $intervention->getId()]);
        }

        // Simulate payment processing...

        $intervention->setPaymentStatus('paid');
        $intervention->setPaymentDate(new \DateTime());
        $intervention->setPaymentMethod('En ligne');
        $intervention->setStatutActuel('en_cours'); // Logic: payment confirms intervention? Or keep as assignee?
        // Let's keep it 'assignee' or whatever it was, or maybe 'confirmed'. 
        // For now just update payment status.

        $em->flush();

        $this->addFlash('success', 'Paiement effectué avec succès ! Merci de votre confiance.');

        return $this->render('front/payment/success.html.twig', [
            'intervention' => $intervention
        ]);
    }

    #[Route('/invoice/{id}', name: 'payment_invoice')]
    public function invoice(Intervention $intervention, \App\Service\InterventionPdfGeneratorService $pdfGenerator): Response
    {
        // Optional: Check if paid?
        if ($intervention->getPaymentStatus() !== 'paid') {
            $this->addFlash('warning', 'La facture est disponible uniquement après paiement.');
            return $this->redirectToRoute('payment_checkout', ['id' => $intervention->getId()]);
        }

        try {
            $pdfContent = $pdfGenerator->generatePdf($intervention);

            // Clean output buffer to prevent PDF corruption
            if (ob_get_length()) {
                ob_end_clean();
            }

            return new Response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="facture_' . $intervention->getId() . '.pdf"',
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération de la facture : ' . $e->getMessage());
            return $this->redirectToRoute('app_home_redirect');
        }
    }
}
