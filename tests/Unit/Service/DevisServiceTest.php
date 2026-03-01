<?php

namespace App\Tests\Unit\Service;

use App\Entity\Intervention;
use App\Entity\ServiceRequest;
use App\Service\DevisService;
use App\Service\InterventionPdfGeneratorService;
use PHPUnit\Framework\TestCase;

class DevisServiceTest extends TestCase
{
    private DevisService $devisService;

    protected function setUp(): void
    {
        $pdfGenerator = $this->createMock(InterventionPdfGeneratorService::class);
        $this->devisService = new DevisService($pdfGenerator);
    }

    public function testGetQuoteFilenameWithServiceRequest(): void
    {
        // Create ServiceRequest and set its id via Reflection
        $serviceRequest = new ServiceRequest();
        $ref = new \ReflectionProperty(ServiceRequest::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($serviceRequest, 42);

        $intervention = new Intervention();
        $intervention->setServiceRequest($serviceRequest);

        $this->assertSame('devis_intervention_42.pdf', $this->devisService->getQuoteFilename($intervention));
    }

    public function testGetQuoteFilenameWithoutServiceRequest(): void
    {
        $intervention = new Intervention();
        // Set intervention id via Reflection
        $ref = new \ReflectionProperty(Intervention::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($intervention, 7);

        $this->assertSame('devis_intervention_7.pdf', $this->devisService->getQuoteFilename($intervention));
    }

    public function testGetQuoteFilenameServiceRequestWithNullId(): void
    {
        $serviceRequest = new ServiceRequest();
        // id stays null → null coalesce to intervention id
        $intervention = new Intervention();
        $intervention->setServiceRequest($serviceRequest);

        $ref = new \ReflectionProperty(Intervention::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($intervention, 99);

        $this->assertSame('devis_intervention_99.pdf', $this->devisService->getQuoteFilename($intervention));
    }
}
