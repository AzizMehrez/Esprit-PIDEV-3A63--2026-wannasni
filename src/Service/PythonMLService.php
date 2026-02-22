<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PythonMLService
{
    private $httpClient;
    private $pythonApiUrl;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        // Port ML remis à 8001 (serveur ML)
        $this->pythonApiUrl = 'http://127.0.0.1:8001';
    }

    public function step1Detect(string $imagePath): array
    {
        try {
            // Validate file exists
            if (!file_exists($imagePath)) {
                return ['status' => 'error', 'message' => 'Fichier image introuvable: ' . $imagePath];
            }

            // Use cURL for proper multipart/form-data file upload
            // Symfony HttpClient 'body' array sends URL-encoded data, not multipart,
            // which FastAPI UploadFile requires.
            $ch = curl_init($this->pythonApiUrl . '/analyze/step1-detect');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => new \CURLFile($imagePath)]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_FAILONERROR, false); // Don't fail on HTTP errors
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['status' => 'error', 'message' => 'cURL error: ' . $curlError];
            }

            // Check for HTTP errors
            if ($httpCode >= 400) {
                return ['status' => 'error', 'message' => "FastAPI error (HTTP $httpCode): " . substr($content, 0, 200)];
            }

            // Parse JSON response
            $decoded = json_decode($content, true);
            if ($decoded === null) {
                return ['status' => 'error', 'message' => 'Format JSON invalide: ' . substr($content, 0, 100)];
            }

            return $decoded;
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Exception: ' . $e->getMessage()];
        }
    }

    public function step2Nutrition(array $foods, string $regime): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/step2-nutrition', [
                'body' => [
                    'foods' => json_encode($foods),
                    'regime' => $regime,
                ],
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide : ' . substr($content, 0, 100)];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function step3Recipes(float $calories, int $dailyLimit, int $consumedToday): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/step3-recipes', [
                'body' => [
                    'total_calories' => $calories,
                    'daily_limit' => $dailyLimit,
                    'consumed_today' => $consumedToday,
                ],
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide : ' . substr($content, 0, 100)];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function step4Alerts(float $calories, array $compliance, int $dailyLimit, int $consumedToday): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/step4-alerts', [
                'body' => [
                    'total_calories' => $calories,
                    'compliance_json' => json_encode($compliance),
                    'daily_limit' => $dailyLimit,
                    'consumed_today' => $consumedToday,
                ],
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide : ' . substr($content, 0, 100)];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get intelligent meal reminders from the ML service.
     * Returns notifications based on regime, meals eaten today, and remaining calories.
     */
    public function getMealReminders(
        string $regimeType,
        int $repasParJour,
        int $repasConsommes,
        int $caloriesConsommees,
        int $caloriesLimite,
        array $alimentsRecommandes = [],
        array $alimentsInterdits = []
    ): array {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/meal-reminders', [
                'body' => [
                    'regime' => $regimeType,
                    'repas_par_jour' => $repasParJour,
                    'repas_consommes' => $repasConsommes,
                    'calories_consommees' => $caloriesConsommees,
                    'calories_limite' => $caloriesLimite,
                    'aliments_recommandes' => json_encode($alimentsRecommandes),
                    'aliments_interdits' => json_encode($alimentsInterdits),
                ],
                'timeout' => 15,
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Advanced meal analysis with all ML features:
     * Multi-food detection, portion estimation, cooking method, risk score.
     */
    public function advancedAnalysis(
        string $imagePath,
        string $regime = 'Standard',
        int $dailyLimit = 2000,
        int $consumedToday = 0,
        ?float $poids = null,
        ?float $taille = null,
        ?int $age = null
    ): array {
        try {
            // Use cURL with CURLFile for proper multipart/form-data with filename
            $postFields = [
                'file'          => new \CURLFile($imagePath),
                'regime'        => $regime,
                'daily_limit'   => $dailyLimit,
                'consumed_today' => $consumedToday,
            ];
            if ($poids) $postFields['poids'] = $poids;
            if ($taille) $postFields['taille'] = $taille;
            if ($age) $postFields['age'] = $age;

            $ch = curl_init($this->pythonApiUrl . '/analyze/advanced-analysis');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            $content = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['status' => 'error', 'message' => 'cURL error: ' . $curlError];
            }

            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Analyze food trends over multiple days.
     */
    public function analyzeTrends(array $mealHistory): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/trends', [
                'body' => [
                    'meal_history' => json_encode($mealHistory),
                ],
                'timeout' => 20,
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Analyze meal rhythm and timing patterns.
     */
    public function analyzeMealRhythm(array $mealHistory, int $repasParJour = 3): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/meal-rhythm', [
                'body' => [
                    'meal_history' => json_encode($mealHistory),
                    'repas_par_jour' => $repasParJour,
                ],
                'timeout' => 20,
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Calculate personalized nutritional risk score.
     */
    public function calculateRiskScore(
        ?float $poids = null,
        ?float $taille = null,
        ?int $age = null,
        array $mealHistory = [],
        string $regime = 'Standard',
        int $dailyLimit = 2000
    ): array {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/risk-score', [
                'body' => [
                    'poids' => $poids ?? 0,
                    'taille' => $taille ?? 0,
                    'age' => $age ?? 0,
                    'meal_history' => json_encode($mealHistory),
                    'regime' => $regime,
                    'daily_limit' => $dailyLimit,
                ],
                'timeout' => 20,
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate comprehensive nutritionist summary report.
     */
    public function generateNutritionistSummary(
        array $mealHistory,
        string $regime = 'Standard',
        int $dailyLimit = 2000,
        ?float $poids = null,
        ?float $taille = null,
        ?int $age = null,
        array $alimentsRecommandes = [],
        array $alimentsInterdits = []
    ): array {
        try {
            $response = $this->httpClient->request('POST', $this->pythonApiUrl . '/analyze/nutritionist-summary', [
                'body' => [
                    'meal_history' => json_encode($mealHistory),
                    'regime' => $regime,
                    'daily_limit' => $dailyLimit,
                    'poids' => $poids ?? 0,
                    'taille' => $taille ?? 0,
                    'age' => $age ?? 0,
                    'aliments_recommandes' => json_encode($alimentsRecommandes),
                    'aliments_interdits' => json_encode($alimentsInterdits),
                ],
                'timeout' => 25,
            ]);
            $content = $response->getContent(false);
            return json_decode($content, true) ?? ['status' => 'error', 'message' => 'Format invalide'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}