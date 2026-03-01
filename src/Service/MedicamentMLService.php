<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

class MedicamentMLService
{
    private $httpClient;
    private $mlApiUrl;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        // URL de l'API FastAPI ML
        $this->mlApiUrl = 'http://127.0.0.1:8090';
    }

    public function getAlternatives(string $nom): array
    {
        try {
            $response = $this->httpClient->request('GET', $this->mlApiUrl . '/alternatives', [
                'query' => [
                    'nom' => trim($nom)
                ]
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            return $response->toArray();
        } catch (\Exception $e) {
            return ['error' => 'API ML indisponible ou erreur de connexion - ' . $e->getMessage()];
        }
    }

    public function analyzeImage(UploadedFile $file): array
    {
        try {
            $formData = new FormDataPart([
                'file' => DataPart::fromPath(
                    $file->getRealPath(),
                    $file->getClientOriginalName(),
                    $file->getMimeType()
                ),
            ]);

            $response = $this->httpClient->request('POST', $this->mlApiUrl . '/analyze-image', [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body'    => $formData->bodyToIterable(),
            ]);

            if ($response->getStatusCode() !== 200) {
                $content = $response->toArray(false);
                return ['error' => $content['detail'] ?? 'Erreur lors de l\'analyse de l\'image'];
            }

            return $response->toArray();
        } catch (\Exception $e) {
            return ['error' => 'API ML indisponible ou erreur de connexion - ' . $e->getMessage()];
        }
    }
}
