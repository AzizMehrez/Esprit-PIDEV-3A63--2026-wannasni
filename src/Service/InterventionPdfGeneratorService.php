<?php

namespace App\Service;

use App\Entity\Intervention;
use Dompdf\Dompdf;
use Dompdf\Options;

class InterventionPdfGeneratorService
{
    private SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    public function generatePdf(Intervention $intervention): string
    {
        // Couleurs cohérentes avec le backend
        $colorAssignee = '#3b82f6';  // Bleu
        $colorEnCours = '#f59e0b';   // Orange/Ambre
        $colorTerminee = '#10b981';  // Vert
        $colorPending = '#6b7280';   // Gris

        // Déterminer la couleur selon le statut
        $statusColor = match ($intervention->getStatutActuel()) {
            'assignee' => $colorAssignee,
            'en_cours' => $colorEnCours,
            'terminee' => $colorTerminee,
            default => $colorPending
        };

        // Calculer le coût total
        $coutTotal = ($intervention->getHeuresTravail() ?? 0) * ($intervention->getTarifHoraire() ?? 0);

        // Vérifier l'abonnement pour calculer la réduction
        $discountInfo = null;
        $user = $intervention->getServiceRequest()?->getUser();
        if ($user) {
            $discountInfo = $this->subscriptionService->previewDiscount($user, $coutTotal);
            if (!$discountInfo['hasSubscription']) {
                $discountInfo = null;
            }
        }

        $html = $this->generateHtml($intervention, $statusColor, $coutTotal, $discountInfo);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function generateHtml(Intervention $intervention, string $statusColor, float $coutTotal, ?array $discountInfo): string
    {
        $statut = $this->getStatutLabel($intervention->getStatutActuel());
        $dateCreation = $intervention->getDateCreation()
            ? $intervention->getDateCreation()->format('d/m/Y H:i')
            : 'Non défini';
        $dateDebut = $intervention->getDateDebut()
            ? $intervention->getDateDebut()->format('d/m/Y H:i')
            : '-';
        $dateFin = $intervention->getDateFin()
            ? $intervention->getDateFin()->format('d/m/Y H:i')
            : '-';

        $technicienEmail = $intervention->getTechnicienEmail() ?? '-';
        $technicienTelephone = $intervention->getTechnicienTelephone() ?? '-';

        // Construire la section réduction abonné si applicable
        $discountHtml = '';
        if ($discountInfo) {
            $planName = htmlspecialchars($discountInfo['planName']);
            $original = number_format($discountInfo['original'], 2, '.', '');
            $percent = $discountInfo['discountPercent'];
            $discount = number_format($discountInfo['discount'], 2, '.', '');
            $final = number_format($discountInfo['final'], 2, '.', '');
            $discountHtml = "
            <div style='background-color: #f0fdf4; border: 2px solid #4CAF50; border-radius: 8px; padding: 15px; margin: 10px 0;'>
                <div style='font-size: 14px; font-weight: bold; color: #166534; margin-bottom: 8px;'>REDUCTION ABONNE {$planName}</div>
                <table style='width: 100%; border: none;'>
                    <tr style='border: none;'><td style='color: #15803d; font-weight: normal;'>Montant original :</td><td style='color: #15803d; text-decoration: line-through;'>{$original} DT</td></tr>
                    <tr style='border: none;'><td style='color: #15803d; font-weight: normal;'>Reduction ({$percent}%) :</td><td style='color: #dc2626;'>-{$discount} DT</td></tr>
                </table>
                <div style='background-color: #dcfce7; padding: 10px; border-radius: 5px; text-align: center; margin-top: 10px;'>
                    <div style='font-size: 12px; color: #166534;'>MONTANT APRES REDUCTION</div>
                    <div style='font-size: 24px; font-weight: bold; color: #166534;'>{$final} DT</div>
                </div>
            </div>
            ";
        }

        $serviceInfo = '';
        if ($intervention->getServiceRequest()) {
            $service = $intervention->getServiceRequest();
            $serviceInfo = "
                <tr>
                    <td style='font-weight: bold; color: #1f2937;'>Service lié:</td>
                    <td>{$service->getTypeService()}</td>
                </tr>
                <tr>
                    <td style='font-weight: bold; color: #1f2937;'>Description:</td>
                    <td>{$service->getDescription()}</td>
                </tr>
                <tr>
                    <td style='font-weight: bold; color: #1f2937;'>Localisation:</td>
                    <td>{$service->getVille()} {$service->getCodePostal()}</td>
                </tr>
            ";
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #000;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 5px solid #1f2937;
            min-height: 90vh;
        }
        .header {
            background-color: #1f2937;
            color: white;
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
            border-bottom: 2px solid #374151;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-badge {
            display: inline-block;
            background-color: {$statusColor};
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
            margin-top: 10px;
        }
        .section {
            margin-bottom: 25px;
            padding: 0 15px;
        }
        .section-title {
            background-color: #f3f4f6;
            padding: 12px 15px;
            font-weight: bold;
            color: #1f2937;
            border-left: 4px solid {$statusColor};
            margin-bottom: 15px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        tr {
            border-bottom: 1px solid #e5e7eb;
        }
        td {
            padding: 12px 8px;
            color: #000;
        }
        td:first-child {
            width: 35%;
            font-weight: bold;
            color: #1f2937;
        }
        .cost-highlight {
            background-color: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
        }
        .cost-highlight .amount {
            font-size: 28px;
            font-weight: bold;
            color: #d97706;
        }
        .timeline {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .timeline-item {
            display: flex;
            margin-bottom: 12px;
        }
        .timeline-icon {
            width: 30px;
            height: 30px;
            background-color: {$statusColor};
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .timeline-content {
            flex: 1;
        }
        .timeline-label {
            font-size: 12px;
            color: #6b7280;
            font-weight: bold;
            text-transform: uppercase;
        }
        .timeline-value {
            font-size: 14px;
            color: #000;
            font-weight: 500;
        }
        .notes {
            background-color: #fff3cd;
            padding: 12px;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            color: #000;
            font-size: 13px;
            margin-top: 10px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            padding-bottom: 10px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>WANNASNI</h1>
            <div class="subtitle">Contrat de Travail</div>
            <div class="status-badge">{$statut}</div>
        </div>

        <!-- Informations de base -->
        <div class="section">
            <div class="section-title">📊 INFORMATIONS PRINCIPALES</div>
            <table>
                <tr>
                    <td>Numéro d'intervention:</td>
                    <td>#{$intervention->getId()}</td>
                </tr>
                <tr>
                    <td>Type de service:</td>
                    <td>{$intervention->getTypesServices()}</td>
                </tr>
                <tr>
                    <td>Zone d'intervention:</td>
                    <td>{$intervention->getZoneIntervention()}</td>
                </tr>
                <tr>
                    <td>Compétences requises:</td>
                    <td>{$intervention->getCompetences()}</td>
                </tr>
                {$serviceInfo}
            </table>
        </div>

        <!-- Technicien assigné -->
        <div class="section">
            <div class="section-title">👤 TECHNICIEN ASSIGNÉ</div>
            <table>
                <tr>
                    <td>Nom:</td>
                    <td>{$intervention->getTechnicienNom()}</td>
                </tr>
                <tr>
                    <td>Email:</td>
                    <td>{$technicienEmail}</td>
                </tr>
                <tr>
                    <td>Téléphone:</td>
                    <td>{$technicienTelephone}</td>
                </tr>
            </table>
        </div>

        <!-- Facturation -->
        <div class="section">
            <div class="section-title">💰 DÉTAILS DE FACTURATION</div>
            <table>
                <tr>
                    <td>Heures de travail:</td>
                    <td>{$intervention->getHeuresTravail()} h</td>
                </tr>
                <tr>
                    <td>Tarif horaire:</td>
                    <td>{$intervention->getTarifHoraire()} DT/h</td>
                </tr>
            </table>
            <div class="cost-highlight">
                <div style="font-size: 12px; color: #7c2d12; margin-bottom: 5px;">COÛT TOTAL ESTIMÉ</div>
                <div class="amount">{$coutTotal} DT</div>
            </div>
            {$discountHtml}
        </div>

        <!-- Timeline -->
        <div class="section">
            <div class="section-title">📅 CHRONOLOGIE</div>
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon">📝</div>
                    <div class="timeline-content">
                        <div class="timeline-label">Créée le</div>
                        <div class="timeline-value">{$dateCreation}</div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">▶️</div>
                    <div class="timeline-content">
                        <div class="timeline-label">Débutée le</div>
                        <div class="timeline-value">{$dateDebut}</div>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">✅</div>
                    <div class="timeline-content">
                        <div class="timeline-label">Terminée le</div>
                        <div class="timeline-value">{$dateFin}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        {$this->getNotesSectionHtml($intervention)}

        <!-- Footer -->
        <div class="footer">
            <p>Document généré le {$this->getCurrentDate()}</p>
            <p>© 2026 WANNASNI - Plateforme de Services aux Seniors</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function getStatutLabel(string $statut): string
    {
        return match ($statut) {
            'en_attente' => '⏳ En attente',
            'assignee' => '👤 Assignée',
            'en_cours' => '🔄 En cours',
            'terminee' => '✅ Terminée',
            default => '❓ Inconnu'
        };
    }

    private function getNotesSectionHtml(Intervention $intervention): string
    {
        if (empty($intervention->getNotes())) {
            return '';
        }

        return <<<HTML
        <div class="section">
            <div class="section-title">📝 NOTES</div>
            <div class="notes">
                {$intervention->getNotes()}
            </div>
        </div>
HTML;
    }

    private function getCurrentDate(): string
    {
        return (new \DateTime())->format('d/m/Y H:i:s');
    }
}
