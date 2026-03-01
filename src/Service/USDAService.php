<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;

class USDAService
{
    private $client;
    private $apiKey;

    public function __construct(
        HttpClientInterface $client,
        string $usdaApiKey = 'DEMO_KEY' // Fallback or injected via services.yaml if needed
    ) {
        $this->client = $client;
        $this->apiKey = $usdaApiKey;
    }

    public function searchFood(string $query): array
    {
        // Implementation for USDA API search
        // https://api.nal.usda.gov/fdc/v1/foods/search
        try {
            $response = $this->client->request('GET', 'https://api.nal.usda.gov/fdc/v1/foods/search', [
                'query' => [
                    'api_key' => $this->apiKey,
                    'query' => $query,
                    'pageSize' => 5
                ]
            ]);

            return $response->toArray()['foods'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getFoodNutrients(string $fdcId): array
    {
        try {
            $response = $this->client->request('GET', "https://api.nal.usda.gov/fdc/v1/food/{$fdcId}", [
                'query' => ['api_key' => $this->apiKey]
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
