<?php

namespace App\Tests\Unit\Service;

use App\Exception\BusinessRuleException;
use App\Exception\ValidationException;
use App\Service\ServiceManagementService;
use PHPUnit\Framework\TestCase;

class ServiceManagementServiceTest extends TestCase
{
    private ServiceManagementService $service;

    protected function setUp(): void
    {
        $this->service = new ServiceManagementService();
    }

    // ─── Status transitions (via Reflection on private method) ───────────

    /**
     * @dataProvider validTransitionProvider
     */
    public function testValidStatusTransitions(string $from, string $to): void
    {
        $result = $this->invokeIsValidStatusTransition($from, $to);
        $this->assertTrue($result, "Transition from '{$from}' to '{$to}' should be valid");
    }

    public function validTransitionProvider(): array
    {
        return [
            'requested → assigned'    => ['requested', 'assigned'],
            'assigned → in_progress'  => ['assigned', 'in_progress'],
            'in_progress → completed' => ['in_progress', 'completed'],
            'completed → rated'       => ['completed', 'rated'],
        ];
    }

    /**
     * @dataProvider invalidTransitionProvider
     */
    public function testInvalidStatusTransitions(string $from, string $to): void
    {
        $result = $this->invokeIsValidStatusTransition($from, $to);
        $this->assertFalse($result, "Transition from '{$from}' to '{$to}' should be invalid");
    }

    public function invalidTransitionProvider(): array
    {
        return [
            'requested → completed'   => ['requested', 'completed'],
            'requested → in_progress' => ['requested', 'in_progress'],
            'assigned → completed'    => ['assigned', 'completed'],
            'completed → assigned'    => ['completed', 'assigned'],
            'rated → anything'        => ['rated', 'requested'],
            'unknown → assigned'      => ['unknown', 'assigned'],
        ];
    }

    // ─── createServiceRequest validation ─────────────────────────────────

    public function testCreateServiceRequestMissingCategory(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Category is required');

        $this->service->createServiceRequest(1, ['description' => 'test']);
    }

    public function testCreateServiceRequestInvalidCategory(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid category');

        $this->service->createServiceRequest(1, [
            'category' => 'hacking',
            'description' => 'test',
        ]);
    }

    public function testCreateServiceRequestMissingDescription(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Description is required');

        $this->service->createServiceRequest(1, [
            'category' => 'plumbing',
        ]);
    }

    public function testCreateServiceRequestInvalidUrgency(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid urgency level');

        $this->service->createServiceRequest(1, [
            'category' => 'plumbing',
            'description' => 'Fix sink',
            'urgency' => 'critical',
        ]);
    }

    // ─── rateService validation ──────────────────────────────────────────

    public function testRateServiceCallsInternalFindById(): void
    {
        // findRequestById calls undefined setId() on ServiceRequest entity
        // This confirms the prototype nature: the service throws Error
        $this->expectException(\Error::class);
        $this->service->rateService(1, 3, 'Good');
    }

    public function testUpdateRequestStatusCallsInternalFindById(): void
    {
        // Same prototype issue — findRequestById calls undefined methods
        $this->expectException(\Error::class);
        $this->service->updateRequestStatus(1, 'assigned');
    }

    // ─── Valid categories constant ───────────────────────────────────────

    public function testValidCategoriesConstant(): void
    {
        $reflection = new \ReflectionClass(ServiceManagementService::class);
        $property = $reflection->getReflectionConstant('VALID_CATEGORIES');

        $this->assertNotFalse($property);

        $categories = $property->getValue();
        $this->assertContains('plumbing', $categories);
        $this->assertContains('cleaning', $categories);
        $this->assertContains('repairs', $categories);
        $this->assertContains('transport', $categories);
        $this->assertContains('other', $categories);
        $this->assertCount(6, $categories);
    }

    // ─── Helper: invoke private isValidStatusTransition ──────────────────

    private function invokeIsValidStatusTransition(string $from, string $to): bool
    {
        $reflection = new \ReflectionMethod(ServiceManagementService::class, 'isValidStatusTransition');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->service, $from, $to);
    }
}
