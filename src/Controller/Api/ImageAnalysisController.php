<?php

namespace App\Controller\Api;

use App\Service\ServiceImageAnalyzerService;
use App\Service\FeatureGateService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

/**
 * API Controller for AI-powered image analysis of service problems
 */
#[Route('/api/ai')]
class ImageAnalysisController extends AbstractController
{
    public function __construct(
        private ServiceImageAnalyzerService $imageAnalyzer,
        private FeatureGateService $featureGate,
        private LoggerInterface $logger
    ) {}

    /**
     * Analyze an uploaded image and detect the service type, description, and urgency
     */
    #[Route('/analyze-problem', name: 'api_ai_analyze_problem', methods: ['POST'])]
    public function analyzeProblem(Request $request): JsonResponse
    {
        try {
            // Vérifier l'abonnement Premium
            $user = $this->getUser();
            if (!$user instanceof User || !$this->featureGate->hasFeature($user, FeatureGateService::FEATURE_AI_IMAGE_DETECTION)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Cette fonctionnalité est réservée aux abonnés Premium. Souscrivez un abonnement Premium pour accéder à la détection IA.',
                    'locked' => true,
                ], 403);
            }

            /** @var UploadedFile|null $file */
            $file = $request->files->get('image');

            if (!$file) {
                return $this->json([
                    'success' => false,
                    'error' => 'Aucune image fournie. Veuillez uploader une image du problème.'
                ], 400);
            }

            // Validate file
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WEBP.'
                ], 400);
            }

            // Max 10MB
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->json([
                    'success' => false,
                    'error' => 'L\'image est trop volumineuse. Maximum 10 Mo.'
                ], 400);
            }

            // Save file temporarily for analysis
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/problems/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = uniqid('problem_') . '.' . $file->guessExtension();
            $filePath = $uploadDir . $filename;
            $file->move($uploadDir, $filename);

            // Analyze the image
            $analysis = $this->imageAnalyzer->analyzeImage($filePath);

            if (!$analysis['success']) {
                return $this->json([
                    'success' => false,
                    'error' => 'Impossible d\'analyser l\'image. Veuillez réessayer.'
                ], 500);
            }

            // Return analysis results
            return $this->json([
                'success' => true,
                'analysis' => [
                    'type_service' => $analysis['type_service'],
                    'type_service_label' => $this->getServiceLabel($analysis['type_service']),
                    'description' => $analysis['description'],
                    'niveau_urgence' => $analysis['niveau_urgence'],
                    'confidence' => round($analysis['confidence'] * 100),
                    'confidence_label' => $this->getConfidenceLabel($analysis['confidence']),
                    'details' => $analysis['details'] ?? '',
                    'ai_provider' => $analysis['ai_provider'] ?? 'unknown'
                ],
                'image_path' => '/uploads/problems/' . $filename,
                'message' => 'Analyse terminée avec succès!'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Image analysis error: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'error' => 'Une erreur est survenue lors de l\'analyse. Veuillez réessayer.'
            ], 500);
        }
    }

    /**
     * Get service type label
     */
    private function getServiceLabel(string $type): string
    {
        $labels = [
            'electricite' => '⚡ Électricité',
            'plomberie' => '🔧 Plomberie',
            'transport' => '🚗 Transport Médical',
            'menage' => '🏠 Ménage',
            'courses' => '🛒 Courses',
            'compagnie' => '👋 Compagnie',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Get confidence label
     */
    private function getConfidenceLabel(float $confidence): string
    {
        if ($confidence >= 0.9) return 'Très haute confiance';
        if ($confidence >= 0.75) return 'Haute confiance';
        if ($confidence >= 0.6) return 'Confiance moyenne';
        if ($confidence >= 0.4) return 'Confiance faible';
        return 'Très faible confiance';
    }

    /**
     * Health check for the AI service
     */
    #[Route('/health', name: 'api_ai_health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'AI Image Analysis',
            'available_types' => ['electricite', 'plomberie', 'transport', 'menage', 'courses', 'compagnie'],
            'max_file_size' => '10MB',
            'supported_formats' => ['JPEG', 'PNG', 'GIF', 'WEBP']
        ]);
    }
}
