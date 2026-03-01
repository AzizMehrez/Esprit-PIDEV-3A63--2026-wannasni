<?php

namespace App\Tests\Service;

use App\Entity\Beverage;
use App\Entity\RegimePrescrit;
use App\Service\PythonMLService;
use App\Service\SommelierService;
use App\Service\GeminiService;
use App\Repository\BeverageRepository;
use App\Repository\BeverageLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AdvancedNutritionTest extends TestCase
{
    private $sommelierService;
    private $pythonMLService;
    private $httpClient;

    protected function setUp(): void
    {
        $gemini = $this->createMock(GeminiService::class);
        $bevRepo = $this->createMock(BeverageRepository::class);
        $logRepo = $this->createMock(BeverageLogRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        
        $this->sommelierService = new SommelierService($gemini, $bevRepo, $logRepo, $em);
        
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->pythonMLService = new PythonMLService($this->httpClient, 'http://localhost:8000');
    }

    public function testMealToMomentMapping(): void
    {
        $this->assertEquals('matin', $this->sommelierService->mealToMoment('petit-déjeuner'));
        $this->assertEquals('déjeuner', $this->sommelierService->mealToMoment('déjeuner'));
        $this->assertEquals('dîner', $this->sommelierService->mealToMoment('dîner'));
        $this->assertEquals('après-midi', $this->sommelierService->mealToMoment('collation'));
    }

    public function testPythonMLStep2NutritionMock(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode([
            'status' => 'success',
            'nutrition' => ['calories' => 500]
        ]));
        
        $this->httpClient->method('request')->willReturn($response);
        
        $result = $this->pythonMLService->step2Nutrition(['pomme'], 'Diabétique');
        $this->assertEquals('success', $result['status'], $result['message'] ?? 'No error message');
    }

    public function testPythonMLAnalyzeTrends(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode([
            'trend' => 'improving',
            'average_calories' => 1800
        ]));
        
        $this->httpClient->method('request')->willReturn($response);
        
        $result = $this->pythonMLService->analyzeTrends([['calories' => 2000], ['calories' => 1600]]);
        $this->assertEquals('improving', $result['trend']);
    }

    public function testSommelierScoreBeverage(): void
    {
        $bev = new Beverage();
        $bev->setCategory(Beverage::CATEGORY_WATER);
        $bev->setHydrationScore(90);
        $bev->setIsSugarFree(true);
        
        $regime = new RegimePrescrit();
        $regime->setTypeRegime('diabétique');
        
        // Score should be positive for water 
        $score = $this->sommelierService->scoreBeverage($bev, 'matin', $regime);
        $this->assertGreaterThan(20, $score);
    }

    public function testSommelierGetPartners(): void
    {
        $partners = $this->sommelierService->getPartners();
        $this->assertIsArray($partners);
        $this->assertNotEmpty($partners);
        $this->assertEquals('Palais des Thés', $partners[0]['name']);
    }

    public function testPythonMLCalculateRiskScore(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode([
            'risk_level' => 'low',
            'score' => 15
        ]));
        
        $this->httpClient->method('request')->willReturn($response);
        
        $result = $this->pythonMLService->calculateRiskScore(75.0, 180.0, 65, [], 'Standard', 2000);
        $this->assertEquals('low', $result['risk_level']);
    }

    public function testPythonMLStep3Recipes(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getContent')->willReturn(json_encode([
            'recommendations' => [
                ['title' => 'Salade César', 'calories' => 350]
            ]
        ]));
        
        $this->httpClient->method('request')->willReturn($response);
        
        $result = $this->pythonMLService->step3Recipes(350, 2000, 1500);
        $this->assertCount(1, $result['recommendations']);
    }
}
