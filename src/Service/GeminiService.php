<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class GeminiService
{
    private $client;
    private $apiKey;

    public function __construct(
        HttpClientInterface $client,
        #[Autowire('%env(resolve:GEMINI_API_KEY)%')] string $apiKey = ''
    ) {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    public function analyzeImage(string $imagePath): array
    {
        // Check if image exists
        if (!file_exists($imagePath)) {
            return ['error' => 'Image file not found'];
        }

        $imageData = base64_encode(file_get_contents($imagePath));
        $prompt = AIPrompts::ANALYSE_PHOTO;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg', // Adjust based on file type if needed
                                'data' => $imageData
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => 1024,
            ]
        ];

        try {
            $response = $this->client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey, [
                'json' => $payload,
                'headers' => ['Content-Type' => 'application/json']
            ]);

            $content = $response->toArray();
            
            if (isset($content['candidates'][0]['content']['parts'][0]['text'])) {
                $rawText = $content['candidates'][0]['content']['parts'][0]['text'];
                
                // Clean markdown if present
                $jsonText = str_replace(['```json', '```'], '', $rawText);
                $data = json_decode($jsonText, true); // Decode to array
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }
                
                // Fallback if JSON is malformed
                return ['raw_text' => $rawText, 'error' => 'Invalid JSON definition from AI'];
            }

            return ['error' => 'No content in response'];

        } catch (\Exception $e) {
            return ['error' => 'API Error: ' . $e->getMessage()];
        }
    }

    /**
     * Analyze a beverage photo and return structured nutritional data
     */
    public function analyzeBeverageImage(string $imagePath): array
    {
        if (!file_exists($imagePath)) {
            return ['error' => 'Image file not found'];
        }

        $imageData = base64_encode(file_get_contents($imagePath));

        $prompt = "Tu es un expert en nutrition et en boissons santé. Analyse cette photo de boisson et identifie précisément ce que c'est.

Réponds UNIQUEMENT en JSON valide avec cette structure exacte :
{
  \"identified\": true,
  \"name\": \"Nom de la boisson identifiée\",
  \"category\": \"thé|café|infusion|eau|jus|smoothie|sirop|mocktail|soda|alcool|lait|autre\",
  \"estimated_volume_ml\": 250,
  \"calories_estimated\": 5,
  \"sugar_content\": \"sans_sucre|faible|moyen|élevé\",
  \"caffeine\": \"sans|faible|modéré|élevé\",
  \"hydration_score\": 85,
  \"is_healthy\": true,
  \"health_benefits\": [\"bienfait 1\", \"bienfait 2\"],
  \"health_warnings\": [\"avertissement si nécessaire\"],
  \"nutritional_info\": {
    \"calories\": 5,
    \"glucides_g\": 1,
    \"proteines_g\": 0,
    \"lipides_g\": 0,
    \"fibres_g\": 0,
    \"sodium_mg\": 0,
    \"sucres_g\": 0,
    \"vitamine_c_mg\": 0
  },
  \"regime_compatibility\": {
    \"diabétique\": true,
    \"cardioprotecteur\": true,
    \"hypo_sodé\": true,
    \"sans_gluten\": true,
    \"normal\": true
  },
  \"description\": \"Description courte de la boisson et de ses propriétés\",
  \"recommendation\": \"Conseil personnalisé pour le consommateur\",
  \"alternatives_healthier\": [\"Alternative plus saine 1\", \"Alternative plus saine 2\"]
}

Si la photo ne montre pas une boisson, mets identified à false et remplis uniquement name avec ce que tu vois.
Sois précis sur les valeurs nutritionnelles estimées.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => 'image/jpeg',
                                'data' => $imageData
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 2048,
            ]
        ];

        try {
            $response = $this->client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey, [
                'json' => $payload,
                'headers' => ['Content-Type' => 'application/json']
            ]);

            $content = $response->toArray();

            if (isset($content['candidates'][0]['content']['parts'][0]['text'])) {
                $rawText = $content['candidates'][0]['content']['parts'][0]['text'];
                $jsonText = str_replace(['```json', '```'], '', $rawText);
                $data = json_decode(trim($jsonText), true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $data;
                }

                return ['error' => 'Invalid JSON from AI', 'raw_text' => $rawText];
            }

            return ['error' => 'No content in response'];

        } catch (\Exception $e) {
            return ['error' => 'API Error: ' . $e->getMessage()];
        }
    }

    public function generateText(string $prompt, array $context = []): string
    {
        $finalPrompt = $prompt;
        
        // Simple template replacement
        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $finalPrompt = str_replace('{{' . $key . '}}', $value, $finalPrompt);
            }
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $finalPrompt]
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $this->apiKey, [
                'json' => $payload
            ]);

            $content = $response->toArray();
            return $content['candidates'][0]['content']['parts'][0]['text'] ?? 'Désolé, je n\'ai pas pu générer de réponse.';

        } catch (\Exception $e) {
            return 'Erreur lors de la génération: ' . $e->getMessage();
        }
    }
}
