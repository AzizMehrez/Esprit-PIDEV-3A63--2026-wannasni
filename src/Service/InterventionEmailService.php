<?php

namespace App\Service;

use App\Entity\Intervention;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InterventionEmailService
{
    private MailerInterface $mailer;
    private DevisService $devisService;
    private SubscriptionService $subscriptionService;
    private string $senderEmail;
    private LoggerInterface $logger;
    private UrlGeneratorInterface $router;

    public function __construct(
        MailerInterface $mailer,
        DevisService $devisService,
        SubscriptionService $subscriptionService,
        LoggerInterface $logger,
        UrlGeneratorInterface $router,
        string $senderEmail = 'noreply@wannasni.com'
    ) {
        $this->mailer = $mailer;
        $this->devisService = $devisService;
        $this->subscriptionService = $subscriptionService;
        $this->logger = $logger;
        $this->router = $router;
        $this->senderEmail = $senderEmail;
    }

    /**
     * Send intervention quote/devis to the senior (client)
     */
    public function sendDevisToSenior(Intervention $intervention): void
    {
        $service = $intervention->getServiceRequest();
        if (!$service) {
            $this->logger->warning('No service request found for intervention ' . $intervention->getId());
            throw new \RuntimeException('Aucune demande de service associée');
        }

        $recipientEmails = [];
        
        // Add senior email from form
        if ($service->getSeniorEmail()) {
            $recipientEmails[] = $service->getSeniorEmail();
        }
        
        // Add user email if user is logged in and email is different
        if ($service->getUser() && $service->getUser()->getEmail()) {
            $userEmail = $service->getUser()->getEmail();
            if (!in_array($userEmail, $recipientEmails)) {
                $recipientEmails[] = $userEmail;
            }
        }

        if (empty($recipientEmails)) {
            $this->logger->warning('No recipient email found for service ' . $service->getId());
            throw new \RuntimeException('Aucune adresse email trouvée pour l\'envoi');
        }

        set_error_handler(function ($errno, $errstr) use (&$errors) {
            throw new \ErrorException($errstr, $errno);
        });

        try {
            $this->logger->info('Starting to send devis email to: ' . implode(', ', $recipientEmails) . ' for intervention ' . $intervention->getId());

            // Generate PDF devis
            $this->logger->info('Generating PDF devis...');
            $pdfContent = $this->devisService->generateQuote($intervention);
            $this->logger->info('PDF generated, size: ' . strlen($pdfContent) . ' bytes');

            $filename = $this->devisService->getQuoteFilename($intervention);
            $this->logger->info('Filename: ' . $filename);

            // Build email
            $email = new Email();
            $email
                ->from($this->senderEmail)
                ->to(...$recipientEmails)  // Send to all recipient emails
                ->subject('Votre devis d\'intervention - WANNASNI')
                ->html($this->buildDevisEmailHtml($intervention, $service));

            $this->logger->info('Email object created, attaching PDF...');

            // Attach PDF as string
            $email->attach($pdfContent, $filename, 'application/pdf');

            $this->logger->info('PDF attached, sending email...');

            // Send
            $this->mailer->send($email);
            $this->logger->info('Devis email sent successfully to: ' . implode(', ', $recipientEmails));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send devis email: ' . $e->getMessage() . ' (' . get_class($e) . ')', ['exception' => $e, 'trace' => $e->getTraceAsString()]);
            throw $e;  // Re-throw to let controller handle it
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Build HTML content for the devis email
     */
    private function buildDevisEmailHtml(Intervention $intervention, $service): string
    {
        $tarifTotal = ($intervention->getHeuresTravail() ?? 2) * ($intervention->getTarifHoraire() ?? 25.00);

        // ── Vérifier l'abonnement pour appliquer la réduction ──
        $discountSection = '';
        $finalAmount = $tarifTotal;
        $user = $service->getUser();
        if ($user) {
            $discountInfo = $this->subscriptionService->applyDiscount($user, $tarifTotal);
            if ($discountInfo['hasSubscription']) {
                $finalAmount = $discountInfo['final'];
                $discountSection = sprintf('
                    <div style="background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 2px solid #4CAF50; border-radius: 8px; padding: 15px; margin: 15px 0;">
                        <h3 style="color: #166534; margin: 0 0 10px;">💎 Réduction Abonné %s</h3>
                        <p style="margin: 5px 0; color: #15803d;"><strong>Montant original :</strong> <span style="text-decoration: line-through;">%.2f TND</span></p>
                        <p style="margin: 5px 0; color: #15803d;"><strong>Réduction (%d%%) :</strong> -%.2f TND</p>
                        <hr style="border: none; border-top: 1px solid #86efac; margin: 10px 0;">
                        <p style="font-size: 20px; font-weight: bold; color: #166534; margin: 5px 0;">✅ Montant après réduction : %.2f TND</p>
                    </div>',
                    htmlspecialchars($discountInfo['planName']),
                    $discountInfo['original'],
                    $discountInfo['discountPercent'],
                    $discountInfo['discount'],
                    $discountInfo['final']
                );
            }
        }

        // Try to generate payment link, fallback to contact info if route doesn't exist
        $paymentSection = '';
        try {
            $paymentUrl = $this->router->generate('payment_checkout', ['id' => $intervention->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            $paymentSection = sprintf('
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="%s" style="background-color: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;">Paiement en ligne</a>
                    </div>', $paymentUrl);
        } catch (\Exception $e) {
            // Payment route not configured yet
            $this->logger->info('Payment checkout route not available: ' . $e->getMessage());
        }

        return sprintf(
            '
            <html>
            <body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
                <div style="max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; padding: 20px;">
                    <h2 style="color: #2E7D32;">DEVIS D\'INTERVENTION</h2>
                    
                    <p>Bonjour,</p>
                    <p>Nous avons le plaisir de vous envoyer le devis pour votre demande de service.</p>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h3>Informations du Service</h3>
                        <p><strong>Type de service:</strong> %s</p>
                        <p><strong>Zone d\'intervention:</strong> %s</p>
                        <p><strong>Compétences requises:</strong> %s</p>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h3>Détails de l\'intervention</h3>
                        <p><strong>Technicien:</strong> %s</p>
                        <p><strong>Heures prévues:</strong> %d heures</p>
                        <p><strong>Tarif horaire:</strong> %.2f TND/h</p>
                        <hr style="border: none; border-top: 1px solid #ddd;">
                        <p style="font-size: 18px; font-weight: bold;"><strong>Montant total estimé: %.2f TND</strong></p>
                    </div>

                    %s

                    %s
                    
                    <p>Un technicien vous contactera pour confirmer l\'intervention.</p>
                    <p>Cordialement,<br><strong>L\'équipe WANNASNI</strong></p>
                </div>
            </body>
            </html>
            ',
            htmlspecialchars($service->getTypeService() ?? '—'),
            htmlspecialchars($intervention->getZoneIntervention() ?? '—'),
            htmlspecialchars($intervention->getCompetences() ?? '—'),
            htmlspecialchars($intervention->getTechnicienNom() ?? 'À assigner'),
            $intervention->getHeuresTravail() ?? 2,
            $intervention->getTarifHoraire() ?? 25.00,
            $tarifTotal,
            $discountSection,
            $paymentSection
        );
    }
}
