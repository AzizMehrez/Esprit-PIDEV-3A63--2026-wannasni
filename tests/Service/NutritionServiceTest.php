<?php

namespace App\Tests\Service;

use App\Entity\NutritionPlan;
use App\Service\NutritionService;
use PHPUnit\Framework\TestCase;

class NutritionServiceTest extends TestCase
{
    private NutritionService $nutritionService;

    protected function setUp(): void
    {
        $this->nutritionService = new NutritionService();
    }

    public function testLogWaterIntakeValid(): void
    {
        $result = $this->nutritionService->logWaterIntake(1, 500);

        $this->assertEquals(500, $result['logged']);
        $this->assertEquals(1300, $result['total_today']); // 800 (mock) + 500
        $this->assertEquals(2000, $result['target']);
        $this->assertFalse($result['target_reached']);
    }

    public function testLogWaterIntakeInvalid(): void
    {
        $this->expectException(\App\Exception\ValidationException::class);
        $this->nutritionService->logWaterIntake(1, 2500);
    }

    public function testGetDailySummary(): void
    {
        $summary = $this->nutritionService->getDailySummary(1);

        $this->assertArrayHasKey('total_calories', $summary);
        $this->assertEquals(1450, $summary['total_calories']);
        $this->assertCount(3, $summary['meals']);
    }

    public function testCreateNutritionPlan(): void
    {
        $data = [
            'calorie_target' => 1800,
            'dietary_restrictions' => ['low-sugar'],
            'allergies' => ['peanuts']
        ];

        $plan = $this->nutritionService->createNutritionPlan(1, $data);

        $this->assertInstanceOf(NutritionPlan::class, $plan);
        $this->assertEquals(1800, $plan->getDailyCalorieTarget());
        $this->assertContains('peanuts', $plan->getAllergies());
        $this->assertTrue($plan->isActive());
    }
}
