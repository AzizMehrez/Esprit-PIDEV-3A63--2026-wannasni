<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;

    public function __construct(MailerInterface $mailer, LoggerInterface $logger)
    {
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    public function sendInterventionQuote(string $toEmail, string $seniorName, string $pdfContent, int $interventionId): void
    {
        try {
            $this->logger->info("Starting email send to {$toEmail} for intervention {$interventionId}");
            
            $email = (new Email())
                ->from('WANNASNI <noreply@wannasni.com>')
                ->to($toEmail)
                ->subject('Devis de Travail - Intervention #' . $interventionId)
                ->html($this->getQuoteEmailHtml($seniorName, $interventionId))
                ->attach($pdfContent, 'devis_intervention_' . $interventionId . '.pdf', 'application/pdf');

            $this->logger->info("Email object created, sending now...");
            
            $result = $this->mailer->send($email);
            
            $this->logger->info("Email sent successfully to {$toEmail}. Result: " . json_encode($result));
        } catch (\Throwable $e) {
            $this->logger->error("Failed to send email to {$toEmail}: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function getQuoteEmailHtml(string $seniorName, int $interventionId): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .header { background-color: #f4f4f4; padding: 20px; text-align: center; }
        .content { padding: 20px; }
        .footer { background-color: #f4f4f4; padding: 10px; text-align: center; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>WANNASNI - Devis de Travail</h1>
    </div>
    <div class="content">
        <p>Cher(e) {$seniorName},</p>
        <p>Nous vous envoyons ci-joint le devis de travail pour l'intervention #{$interventionId}.</p>
        <p>Ce devis détaille les services à effectuer, les tarifs horaires et le coût total estimé.</p>
        <p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>
        <p>Cordialement,<br>L'équipe WANNASNI</p>
    </div>
    <div class="footer">
        <p>© 2026 WANNASNI - Plateforme de Services aux Seniors</p>
    </div>
</body>
</html>
HTML;
    }
}
