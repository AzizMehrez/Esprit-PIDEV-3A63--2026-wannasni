<?php

namespace App\Service;

use App\Entity\Intervention;
use App\Service\SubscriptionService;
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
    private string $senderEmail;
    private LoggerInterface $logger;
    private UrlGeneratorInterface $router;
    private ?SubscriptionService $subscriptionService;

    public function __construct(
        MailerInterface $mailer,
        DevisService $devisService,
        LoggerInterface $logger,
        UrlGeneratorInterface $router,
        ?SubscriptionService $subscriptionService = null,
        string $senderEmail = 'noreply@wannasni.com'
    ) {
        $this->mailer = $mailer;
        $this->devisService = $devisService;
        $this->logger = $logger;
        $this->router = $router;
        $this->subscriptionService = $subscriptionService;
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

        // Try to generate payment link (include _locale required by route)
        $paymentSection = '';
        try {
            $paymentUrl = $this->router->generate(
                'payment_checkout',
                ['_locale' => 'fr', 'id' => $intervention->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            $paymentSection = sprintf('
                    <div style="text-align: center; margin: 30px 0;">
                        <a href="%s" style="background-color: #28a745; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-size: 16px; font-weight: bold; display: inline-block;">💳 Paiement en ligne</a>
                        <p style="color: #666; font-size: 13px; margin-top: 8px;">Cliquez pour régler votre intervention en toute sécurité</p>
                    </div>', $paymentUrl);
        } catch (\Exception $e) {
            $this->logger->info('Payment checkout route not available: ' . $e->getMessage());
        }

        // Build subscription discount section
        $discountSection = '';
        $finalAmount = $tarifTotal;
        $senior = $service->getUser();
        if ($senior && $this->subscriptionService) {
            try {
                $discountInfo = $this->subscriptionService->previewDiscount($senior, $tarifTotal);
                if ($discountInfo['hasSubscription'] && $discountInfo['discountPercent'] > 0) {
                    $finalAmount = $discountInfo['final'];
                    $discountSection = sprintf('
                    <div style="background: #e8f5e9; border-left: 4px solid #28a745; padding: 15px; border-radius: 5px; margin: 20px 0;">
                        <h3 style="color: #2E7D32; margin-top: 0;">🎉 Réduction Abonnement appliquée</h3>
                        <p><strong>Plan:</strong> %s</p>
                        <p><strong>Remise:</strong> %d%%</p>
                        <table style="width: 100%%; border-collapse: collapse;">
                            <tr><td>Montant original</td><td style="text-align:right;">%.2f TND</td></tr>
                            <tr><td>Réduction (-%d%%)</td><td style="text-align:right; color:#e53935;">-%.2f TND</td></tr>
                            <tr style="font-weight:bold; font-size:16px; border-top: 2px solid #28a745;">
                                <td>Montant final</td>
                                <td style="text-align:right; color:#28a745;">%.2f TND</td>
                            </tr>
                        </table>
                    </div>',
                        htmlspecialchars($discountInfo['planName']),
                        $discountInfo['discountPercent'],
                        $discountInfo['original'],
                        $discountInfo['discountPercent'],
                        $discountInfo['discount'],
                        $discountInfo['final']
                    );
                }
            } catch (\Exception $e) {
                $this->logger->info('Could not compute subscription discount: ' . $e->getMessage());
            }
        }

        $totalLabel = $discountSection
            ? sprintf('<p style="color:#666; text-decoration:line-through;">Montant brut: %.2f TND</p><p style="font-size: 18px; font-weight: bold; color:#28a745;"><strong>Montant à payer: %.2f TND</strong></p>', $tarifTotal, $finalAmount)
            : sprintf('<p style="font-size: 18px; font-weight: bold;"><strong>Montant total estimé: %.2f TND</strong></p>', $tarifTotal);

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
                        %s
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
            $totalLabel,
            $discountSection,
            $paymentSection
        );
    }
}
