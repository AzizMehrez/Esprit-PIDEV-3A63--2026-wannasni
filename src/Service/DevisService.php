<?php

namespace App\Service;

use App\Entity\Intervention;

class DevisService
{
    private InterventionPdfGeneratorService $pdfGenerator;

    public function __construct(InterventionPdfGeneratorService $pdfGenerator)
    {
        $this->pdfGenerator = $pdfGenerator;
    }

    /**
     * Generate a quote/estimate PDF for an intervention
     */
    public function generateQuote(Intervention $intervention): string
    {
        // Reuse the PDF generator to create a quote PDF
        return $this->pdfGenerator->generatePdf($intervention);
    }

    /**
     * Get quote filename
     */
    public function getQuoteFilename(Intervention $intervention): string
    {
        $serviceId = $intervention->getServiceRequest()?->getId() ?? $intervention->getId();
        return sprintf('devis_intervention_%d.pdf', $serviceId);
    }
}
