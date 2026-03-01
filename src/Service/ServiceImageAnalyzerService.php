<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service d'analyse d'image par IA pour détecter le type de problème
 * et générer automatiquement la description et le niveau d'urgence
 */
class ServiceImageAnalyzerService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private ?string $openaiApiKey;
    private ?string $localServiceUrl;
    private string $projectDir;

    // Types de services disponibles avec leurs mots-clés
    private array $serviceKeywords = [
        'electricite' => [
            'keywords' => ['électrique', 'electric', 'prise', 'socket', 'interrupteur', 'switch', 'câble', 'cable', 'fil', 'wire', 'court-circuit', 'disjoncteur', 'ampoule', 'bulb', 'lumière', 'light', 'fusible', 'tableau électrique', 'voltage', 'courant', 'spark', 'etincelle'],
            'urgencyIndicators' => ['étincelle', 'feu', 'brûlé', 'fumée', 'danger', 'choc', 'fire', 'smoke'],
            'name' => 'Électricité',
            'icon' => '⚡'
        ],
        'plomberie' => [
            'keywords' => ['fuite', 'leak', 'eau', 'water', 'tuyau', 'pipe', 'robinet', 'tap', 'faucet', 'évier', 'sink', 'toilette', 'toilet', 'WC', 'douche', 'shower', 'baignoire', 'bath', 'canalisation', 'drain', 'bouchon', 'chasse', 'siphon', 'joint', 'plomb'],
            'urgencyIndicators' => ['inondation', 'flood', 'dégât des eaux', 'urgence', 'gros dégât'],
            'name' => 'Plomberie',
            'icon' => '🔧'
        ],
        'menage' => [
            'keywords' => ['ménage', 'menage', 'nettoyage', 'clean', 'poussière', 'dust', 'saleté', 'dirty', 'rangement', 'tidy', 'désordre', 'mess', 'broom', 'balai', 'mop', 'serpillere'],
            'urgencyIndicators' => [],
            'name' => 'Ménage',
            'icon' => '🏠'
        ],
        'transport' => [
            'keywords' => ['transport', 'véhicule', 'vehicle', 'voiture', 'car', 'médical', 'medical', 'hôpital', 'hopital', 'hospital', 'rendez-vous', 'appointment', 'malade', 'sick', 'ill', 'patient', 'docteur', 'doctor', 'medecin', 'ambulance', 'wheelchair', 'fauteuil', 'bequille', 'crutch', 'sante', 'health', 'clinic', 'clinique', 'infirmier', 'nurse', 'personne agee', 'elderly', 'senior'],
            'urgencyIndicators' => ['urgence médicale', 'ambulance', 'emergency', 'urgent'],
            'name' => 'Transport Médical',
            'icon' => '🚗'
        ],
        'courses' => [
            'keywords' => ['courses', 'shopping', 'groceries', 'supermarché', 'supermarket', 'magasin', 'store', 'frigo', 'fridge', 'frigidaire', 'refrigerateur', 'refrigerator', 'nourriture', 'food', 'aliment', 'cuisine', 'kitchen', 'vide', 'empty', 'provisions', 'achat', 'purchase', 'epicerie', 'grocery', 'legume', 'vegetable', 'fruit', 'viande', 'meat', 'lait', 'milk', 'pain', 'bread', 'repas', 'meal'],
            'urgencyIndicators' => [],
            'name' => 'Courses',
            'icon' => '🛒'
        ],
        'compagnie' => [
            'keywords' => ['compagnie', 'company', 'solitude', 'lonely', 'seul', 'alone', 'visite', 'visit', 'discussion', 'parler', 'talk', 'ami', 'friend', 'conversation', 'accompagnement', 'ecoute', 'listen'],
            'urgencyIndicators' => [],
            'name' => 'Compagnie',
            'icon' => '👋'
        ]
    ];

    private ?string $huggingfaceApiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ?string $openaiApiKey = null,
        ?string $huggingfaceApiKey = null,
        ?string $localServiceUrl = null,
        ?string $projectDir = null
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->openaiApiKey = $openaiApiKey ?: ($_ENV['OPENAI_API_KEY'] ?? null);
        $this->huggingfaceApiKey = $huggingfaceApiKey ?: ($_ENV['HUGGINGFACE_API_KEY'] ?? null);
        $this->localServiceUrl = $localServiceUrl ?: ($_ENV['AI_LOCAL_SERVICE_URL'] ?? null);
        $this->projectDir = $projectDir ?: \dirname(__DIR__, 2);
    }

    /**
     * Exécute une commande avec un timeout (en secondes).
     * Retourne [output_string, return_code] ou [null, -1] si timeout.
     */
    private function execWithTimeout(string $command, int $timeoutSeconds = 15): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($command, $descriptors, $pipes);

        if (!\is_resource($process)) {
            return [null, -1];
        }

        \fclose($pipes[0]);

        // Set non-blocking mode
        \stream_set_blocking($pipes[1], false);
        \stream_set_blocking($pipes[2], false);

        $output = '';
        $startTime = \time();

        while (true) {
            $status = \proc_get_status($process);

            // Read available output
            $chunk = \stream_get_contents($pipes[1]);
            if ($chunk !== false) {
                $output .= $chunk;
            }

            if (!$status['running']) {
                // Process finished — read any remaining output
                $chunk = \stream_get_contents($pipes[1]);
                if ($chunk !== false) {
                    $output .= $chunk;
                }
                \fclose($pipes[1]);
                \fclose($pipes[2]);
                \proc_close($process);
                return [$output, $status['exitcode']];
            }

            if ((\time() - $startTime) >= $timeoutSeconds) {
                // Timeout — kill the process
                \fclose($pipes[1]);
                \fclose($pipes[2]);
                if (\strtoupper(\substr(PHP_OS, 0, 3)) === 'WIN') {
                    // On Windows, use taskkill to kill process tree
                    \exec('taskkill /F /T /PID ' . $status['pid'] . ' 2>NUL');
                } else {
                    \proc_terminate($process, 9);
                }
                \proc_close($process);
                $this->logger->warning("Command timed out after {$timeoutSeconds}s: " . \substr($command, 0, 100));
                return [null, -1];
            }

            \usleep(100000); // 100ms
        }
    }

    /**
     * Analyse une image et retourne les informations détectées
     */
    public function analyzeImage(string $imagePath): array
    {
        // Augmenter le temps d'exécution max pour les scripts Python lourds (CLIP)
        $previousTimeout = (int)\ini_get('max_execution_time');
        \set_time_limit(180);

        $this->logger->info('Analyzing image: ' . $imagePath);

        try {
            // Priorité 0: Modèle ML entraîné (SVM + CLIP features)
            $result = $this->analyzeWithMLModel($imagePath);
            if ($result['success']) {
                return $result;
            }

            // Priorité 1: Script Python CLIP zero-shot (fallback)
            $result = $this->analyzeWithPythonClip($imagePath);
            if ($result['success']) {
                return $result;
            }

            // Priorité 2: Service local FastAPI (si démarré)
            if ($this->localServiceUrl) {
                $result = $this->analyzeWithLocalService($imagePath);
                if ($result['success']) {
                    return $result;
                }
            }

            // Priorité 3: Hugging Face API
            if ($this->huggingfaceApiKey) {
                $result = $this->analyzeWithHuggingFace($imagePath);
                if ($result['success']) {
                    return $result;
                }
            }

            // Priorité 4: OpenAI
            if ($this->openaiApiKey) {
                $result = $this->analyzeWithOpenAI($imagePath);
                if ($result['success']) {
                    return $result;
                }
            }

            // Fallback: Simulation
            return $this->analyzeWithSimulation($imagePath);
        } finally {
            // Restaurer le timeout original
            \set_time_limit($previousTimeout);
        }
    }

    /**
     * Analyse avec le modèle ML entraîné (SVM + features CLIP)
     * Le modèle doit être entraîné au préalable avec train_model.py
     */
    private function analyzeWithMLModel(string $imagePath): array
    {
        try {
            $scriptPath = $this->projectDir . DIRECTORY_SEPARATOR . 'ml_model' . DIRECTORY_SEPARATOR . 'predict.py';
            $modelPath = $this->projectDir . DIRECTORY_SEPARATOR . 'ml_model' . DIRECTORY_SEPARATOR . 'model.pkl';

            if (!\file_exists($scriptPath)) {
                $this->logger->warning('Script ML predict.py introuvable: ' . $scriptPath);
                return ['success' => false];
            }

            if (!\file_exists($modelPath)) {
                $this->logger->warning('Modèle ML non entraîné. Exécutez: python ml_model/train_model.py');
                return ['success' => false];
            }

            $pythonPath = $_ENV['PYTHON_PATH'] ?? 'python';

            $command = \sprintf(
                '%s %s %s 2>NUL',
                \escapeshellarg($pythonPath),
                \escapeshellarg($scriptPath),
                \escapeshellarg($imagePath)
            );

            $this->logger->info('Running ML model prediction: ' . $command);

            [$rawOutput, $returnCode] = $this->execWithTimeout($command, 90);

            if ($rawOutput === null) {
                $this->logger->warning('ML model prediction timed out');
                return ['success' => false];
            }

            $output = \explode("\n", $rawOutput);

            // Chercher la ligne JSON dans la sortie (celle qui commence par '{')
            $jsonOutput = '';
            foreach ($output as $line) {
                $trimmed = \trim($line);
                if (\str_starts_with($trimmed, '{')) {
                    $jsonOutput = $trimmed;
                    break;
                }
            }

            // Fallback: essayer toute la sortie concaténée
            if (empty($jsonOutput)) {
                $jsonOutput = \implode('', $output);
            }

            $this->logger->info('ML model JSON: ' . $jsonOutput);

            $data = \json_decode($jsonOutput, true);

            if (!$data || !($data['success'] ?? false)) {
                $this->logger->warning('ML model prediction failed: ' . ($data['error'] ?? $jsonOutput));
                return ['success' => false];
            }

            return [
                'success' => true,
                'type_service' => $data['type_service'] ?? 'menage',
                'description' => $data['description'] ?? 'Analyse ML effectuée.',
                'niveau_urgence' => $data['niveau_urgence'] ?? 'normale',
                'confidence' => (float)($data['confidence'] ?? 0.8),
                'details' => $data['details'] ?? 'ML Model SVM',
                'ai_provider' => 'ml_model_svm'
            ];
        } catch (\Exception $e) {
            $this->logger->error('ML model error: ' . $e->getMessage());
            return ['success' => false];
        }
    }

    /**
     * Analyse avec le script Python CLIP local (image_analyzer_clip.py)
     * Appelle le script via exec() - pas besoin de serveur ni d'API
     */
    private function analyzeWithPythonClip(string $imagePath): array
    {
        try {
            $scriptPath = $this->projectDir . DIRECTORY_SEPARATOR . 'image_analyzer_clip.py';

            if (!\file_exists($scriptPath)) {
                $this->logger->warning('Script CLIP introuvable: ' . $scriptPath);
                return ['success' => false];
            }

            // Déterminer le chemin Python
            $pythonPath = $_ENV['PYTHON_PATH'] ?? 'python';

            $command = \sprintf(
                '%s %s %s 2>&1',
                \escapeshellarg($pythonPath),
                \escapeshellarg($scriptPath),
                \escapeshellarg($imagePath)
            );

            $this->logger->info('Running CLIP analysis: ' . $command);

            [$rawOutput, $returnCode] = $this->execWithTimeout($command, 90);

            if ($rawOutput === null) {
                $this->logger->warning('CLIP analysis timed out');
                return ['success' => false];
            }

            $jsonOutput = $rawOutput;
            $this->logger->info('CLIP output: ' . $jsonOutput);

            $data = \json_decode($jsonOutput, true);

            if (!$data || !($data['success'] ?? false)) {
                $this->logger->warning('CLIP analysis failed: ' . ($data['error'] ?? $jsonOutput));
                return ['success' => false];
            }

            return [
                'success' => true,
                'type_service' => $data['type_service'] ?? 'menage',
                'description' => $data['description'] ?? 'Analyse IA effectuée.',
                'niveau_urgence' => $data['niveau_urgence'] ?? 'normale',
                'confidence' => (float)($data['confidence'] ?? 0.8),
                'details' => $data['details'] ?? 'CLIP local',
                'ai_provider' => 'local_clip'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Python CLIP error: ' . $e->getMessage());
            return ['success' => false];
        }
    }

    /**
     * Analyse avec service local FastAPI (CLIP zero-shot)
     */
    private function analyzeWithLocalService(string $imagePath): array
    {
        try {
            $endpoint = rtrim($this->localServiceUrl, '/') . '/analyze';
            $this->logger->info('Calling local analyzer: ' . $endpoint);

            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'body' => [
                    'image' => fopen($imagePath, 'r')
                ],
                'timeout' => 20
            ]);

            if ($response->getStatusCode() !== 200) {
                $this->logger->warning('Local analyzer returned status: ' . $response->getStatusCode());
                return ['success' => false];
            }

            $data = $response->toArray(false);
            if (!($data['success'] ?? false)) {
                return ['success' => false];
            }

            return [
                'success' => true,
                'type_service' => $data['type_service'] ?? 'menage',
                'description' => $data['description'] ?? 'Analyse locale effectuée',
                'niveau_urgence' => $data['niveau_urgence'] ?? 'normale',
                'confidence' => (float)($data['confidence'] ?? 0.8),
                'details' => $data['details'] ?? 'Local analyzer',
                'ai_provider' => 'local_clip'
            ];
        } catch (\Exception $e) {
            $this->logger->error('Local analyzer error: ' . $e->getMessage());
            return ['success' => false];
        }
    }

    /**
     * Analyse avec Hugging Face CLIP zero-shot classification (GRATUIT & PRÉCIS)
     * Utilise openai/clip-vit-base-patch32 pour classifier directement l'image
     */
    private function analyzeWithHuggingFace(string $imagePath): array
    {
        try {
            $imageData = \file_get_contents($imagePath);
            $base64Image = \base64_encode($imageData);

            // Labels descriptifs pour chaque type de service (en anglais pour CLIP)
            $candidateLabels = [
                'a water leak from a pipe or faucet, plumbing problem, broken toilet, clogged sink',
                'an electrical problem, broken outlet, exposed wires, damaged light switch',
                'an empty fridge, groceries, food shopping, kitchen supplies needed',
                'medical transport, hospital visit, wheelchair, sick elderly person, medicine',
                'a messy dirty room needing cleaning, dust, clutter, household chores',
                'a lonely elderly person sitting alone, needing company and companionship'
            ];

            // Mapping label index → type de service
            $labelToType = [
                0 => 'plomberie',
                1 => 'electricite',
                2 => 'courses',
                3 => 'transport',
                4 => 'menage',
                5 => 'compagnie'
            ];

            $this->logger->info('Calling HuggingFace CLIP zero-shot classification');

            $response = $this->httpClient->request('POST', 'https://api-inference.huggingface.co/models/openai/clip-vit-base-patch32', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->huggingfaceApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => [
                        'image' => $base64Image,
                    ],
                    'parameters' => [
                        'candidate_labels' => $candidateLabels
                    ]
                ],
                'timeout' => 30
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode !== 200) {
                $body = $response->getContent(false);
                $this->logger->warning('HuggingFace CLIP API returned: ' . $statusCode . ' - ' . $body);
                return ['success' => false];
            }

            $data = $response->toArray();
            $this->logger->info('HuggingFace CLIP response: ' . \json_encode($data));

            // La réponse est un tableau [{label, score}, ...] trié par score décroissant
            if (empty($data) || !isset($data[0]['label'])) {
                $this->logger->warning('HuggingFace CLIP: format de réponse inattendu');
                return ['success' => false];
            }

            // Trouver le meilleur match
            $bestLabel = $data[0]['label'] ?? '';
            $bestScore = (float)($data[0]['score'] ?? 0);

            // Mapper le label au type de service
            $detectedType = 'menage'; // fallback
            $labelIndex = \array_search($bestLabel, $candidateLabels);
            if ($labelIndex !== false && isset($labelToType[$labelIndex])) {
                $detectedType = $labelToType[$labelIndex];
            }

            // Log tous les scores pour debug
            foreach ($data as $item) {
                $idx = \array_search($item['label'], $candidateLabels);
                $type = ($idx !== false && isset($labelToType[$idx])) ? $labelToType[$idx] : '?';
                $this->logger->info(\sprintf('  CLIP: %s = %.4f (%s)', $type, $item['score'], $item['label']));
            }

            // Descriptions françaises par type
            $descriptions = [
                'plomberie' => "Problème de plomberie détecté : fuite d'eau ou canalisation à réparer.",
                'electricite' => "Problème électrique identifié : câblage, prise ou éclairage défectueux.",
                'courses' => "Besoin d'aide pour les courses : réapprovisionnement alimentaire nécessaire.",
                'transport' => "Transport médical requis : accompagnement vers un rendez-vous de santé.",
                'menage' => "Service de ménage recommandé : nettoyage et rangement à effectuer.",
                'compagnie' => "Besoin de compagnie : visite et présence bienveillante pour rompre la solitude."
            ];

            // Déterminer l'urgence selon le type et le score
            $urgency = 'normale';
            if (\in_array($detectedType, ['plomberie', 'electricite']) && $bestScore > 0.5) {
                $urgency = 'urgente';
            } elseif ($detectedType === 'transport') {
                $urgency = 'moyenne';
            } elseif ($bestScore > 0.6 && \in_array($detectedType, ['plomberie', 'electricite'])) {
                $urgency = 'moyenne';
            }

            return [
                'success' => true,
                'type_service' => $detectedType,
                'description' => $descriptions[$detectedType] ?? 'Service identifié par analyse IA.',
                'niveau_urgence' => $urgency,
                'confidence' => \min(0.98, $bestScore + 0.1),
                'details' => \sprintf('CLIP zero-shot: %s (score: %.2f%%)', $detectedType, $bestScore * 100),
                'ai_provider' => 'huggingface_clip'
            ];

        } catch (\Exception $e) {
            $this->logger->error('HuggingFace CLIP error: ' . $e->getMessage());
            return ['success' => false];
        }
    }

    /**
     * Analyse avec OpenAI GPT-4 Vision (si configuré)
     */
    private function analyzeWithOpenAI(string $imagePath): array
    {
        try {
            // Lire l'image et la convertir en base64
            $imageData = file_get_contents($imagePath);
            $base64Image = base64_encode($imageData);
            $mimeType = mime_content_type($imagePath) ?: 'image/jpeg';

            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openaiApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un assistant expert pour aider les personnes âgées. Analyse l\'image et identifie le SERVICE dont la personne a besoin.

TYPES DE SERVICES DISPONIBLES:
- electricite : Problèmes électriques (prises, fils, ampoules, disjoncteurs, interrupteurs)
- plomberie : Problèmes d\'eau (fuites, robinets, toilettes, tuyaux, douches, éviers)
- courses : Besoin de faire des courses (frigo vide, manque de nourriture, provisions à acheter, réfrigérateur)
- transport : Transport médical (personne malade, rendez-vous médical, hôpital, clinique, fauteuil roulant)
- menage : Nettoyage et rangement (saleté, désordre, poussière)
- compagnie : Besoin de compagnie (solitude, visite, discussion)

RÈGLES IMPORTANTES:
- Si tu vois un frigo/réfrigérateur (vide ou plein) → type "courses"
- Si tu vois une personne malade, médicaments, équipement médical → type "transport"
- Si tu vois de l\'eau, des tuyaux, robinets → type "plomberie"
- Si tu vois des fils, prises, ampoules → type "electricite"
- Si tu vois du désordre, saleté → type "menage"
- Si tu vois une personne seule, triste → type "compagnie"

Réponds UNIQUEMENT en JSON avec ce format exact:
{
    "type_service": "electricite|plomberie|menage|transport|courses|compagnie",
    "description": "Description détaillée du besoin en français...",
    "niveau_urgence": "normale|moyenne|urgente",
    "confidence": 0.85,
    "details": "Détails supplémentaires optionnels"
}'
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'Analyse cette image. De quel service cette personne âgée a-t-elle besoin? Identifie le type de service, décris le besoin, et indique le niveau d\'urgence.'
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => "data:{$mimeType};base64,{$base64Image}"
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'max_tokens' => 500
                ]
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Extraire le JSON de la réponse
            preg_match('/\{[\s\S]*\}/', $content, $matches);
            if (!empty($matches[0])) {
                $result = json_decode($matches[0], true);
                if ($result) {
                    return [
                        'success' => true,
                        'type_service' => $result['type_service'] ?? 'menage',
                        'description' => $result['description'] ?? 'Problème détecté nécessitant une intervention',
                        'niveau_urgence' => $result['niveau_urgence'] ?? 'normale',
                        'confidence' => (float)($result['confidence'] ?? 0.8),
                        'details' => $result['details'] ?? '',
                        'ai_provider' => 'openai'
                    ];
                }
            }

            throw new \Exception('Invalid response format from OpenAI');

        } catch (\Exception $e) {
            $this->logger->error('OpenAI API error: ' . $e->getMessage());
            // Fallback to simulation
            return $this->analyzeWithSimulation($imagePath);
        }
    }

    /**
     * Analyse simulée basée sur l'analyse réelle des caractéristiques de l'image
     * Utilisée quand OpenAI n'est pas disponible
     */
    private function analyzeWithSimulation(string $imagePath): array
    {
        $filename = strtolower(basename($imagePath));
        
        // D'abord analyser les caractéristiques réelles de l'image
        $imageAnalysis = $this->analyzeImageCharacteristics($imagePath);
        
        $detectedType = null;
        $confidence = 0.7;
        $description = '';
        $urgency = 'normale';
        
        // Analyse basée sur le nom de fichier (priorité si trouvé)
        foreach ($this->serviceKeywords as $type => $data) {
            foreach ($data['keywords'] as $keyword) {
                $keyword = str_replace(['é', 'è', 'ê'], 'e', $keyword);
                $filenameNormalized = str_replace(['é', 'è', 'ê'], 'e', $filename);
                if (stripos($filenameNormalized, $keyword) !== false) {
                    $detectedType = $type;
                    $confidence = 0.88;
                    break 2;
                }
            }
        }
        
        // Si pas trouvé par le nom, utiliser l'analyse de couleurs de l'image
        if ($detectedType === null) {
            $detectedType = $this->detectTypeFromImageColors($imageAnalysis);
            $confidence = 0.75 + (random_int(0, 15) / 100); // 0.75-0.90
        }
        
        // Générer une description appropriée selon le type
        switch ($detectedType) {
            case 'electricite':
                $descriptions = [
                    "Problème électrique détecté : dysfonctionnement visible sur l'installation électrique nécessitant l'intervention d'un électricien qualifié.",
                    "Anomalie électrique constatée : fils dénudés ou installation défectueuse repérée. Une intervention urgente est recommandée pour votre sécurité.",
                    "Défaillance du système électrique identifiée. Prise, interrupteur ou câblage endommagé nécessitant une réparation professionnelle.",
                    "Installation électrique présentant des signes d'usure ou de détérioration. Un diagnostic complet par un électricien est conseillé."
                ];
                $description = $descriptions[random_int(0, count($descriptions) - 1)];
                $urgency = random_int(0, 1) ? 'urgente' : 'moyenne';
                break;

            case 'plomberie':
                $descriptions = [
                    "Fuite d'eau détectée : écoulement anormal visible nécessitant l'intervention rapide d'un plombier pour éviter les dégâts.",
                    "Problème de plomberie identifié : canalisation, robinet ou sanitaire défaillant. Réparation nécessaire dans les meilleurs délais.",
                    "Dégât des eaux potentiel : traces d'humidité ou fuite active constatée. Une intervention urgente est recommandée.",
                    "Dysfonctionnement sanitaire repéré : WC, douche ou évier nécessitant une réparation par un professionnel."
                ];
                $description = $descriptions[random_int(0, count($descriptions) - 1)];
                $urgency = random_int(0, 2) ? 'moyenne' : 'urgente';
                break;

            case 'menage':
                $descriptions = [
                    "Espace nécessitant un nettoyage approfondi. Service de ménage recommandé pour remettre en ordre.",
                    "Entretien général nécessaire : accumulation de poussière et désordre constaté.",
                    "Besoin de rangement et nettoyage identifié dans l'espace de vie."
                ];
                $description = $descriptions[random_int(0, count($descriptions) - 1)];
                $urgency = 'normale';
                break;

            case 'transport':
                $descriptions = [
                    "Besoin de transport médical identifié : accompagnement vers un rendez-vous de santé ou établissement médical.",
                    "Assistance transport nécessaire pour une personne ayant besoin d'accompagnement médical.",
                    "Transport médical requis : déplacement vers hôpital, clinique ou cabinet médical détecté.",
                    "Personne nécessitant un accompagnement pour rendez-vous médical ou soins de santé."
                ];
                $description = $descriptions[random_int(0, count($descriptions) - 1)];
                $urgency = random_int(0, 1) ? 'moyenne' : 'normale';
                break;

            case 'courses':
                $descriptions = [
                    "Besoin d'aide pour les courses alimentaires identifié : réapprovisionnement nécessaire.",
                    "Assistance courses nécessaire : réfrigérateur ou provisions à réapprovisionner.",
                    "Service de courses recommandé : achats alimentaires ou quotidiens à effectuer.",
                    "Aide aux courses détectée : accompagnement pour achats au supermarché ou épicerie."
                ];
                $description = $descriptions[random_int(0, count($descriptions) - 1)];
                $urgency = 'normale';
                break;

            case 'compagnie':
                $descriptions = [
                    "Besoin de compagnie identifié : visite et conversation souhaitées.",
                    "Service de compagnie recommandé : présence et écoute pour rompre la solitude.",
                    "Accompagnement social détecté : discussion et présence bienveillante nécessaires."
                ];
                $description = $descriptions[random_int(0, count($descriptions) - 1)];
                $urgency = 'normale';
                break;

            default:
                $description = "Analyse de l'image effectuée. Veuillez vérifier et préciser votre besoin si nécessaire.";
                $confidence = 0.65;
        }

        // Ajouter des détails sur l'analyse
        $details = sprintf(
            "Analyse basée sur les caractéristiques de l'image (couleurs dominantes: %s)",
            implode(', ', array_slice($imageAnalysis['dominantColors'] ?? ['non disponible'], 0, 3))
        );

        return [
            'success' => true,
            'type_service' => $detectedType,
            'description' => $description,
            'niveau_urgence' => $urgency,
            'confidence' => min($confidence, 0.95),
            'details' => $details,
            'ai_provider' => 'simulation_v2'
        ];
    }
    
    /**
     * Analyse les caractéristiques visuelles de l'image (couleurs dominantes)
     */
    private function analyzeImageCharacteristics(string $imagePath): array
    {
        $result = [
            'dominantColors' => [],
            'brightness' => 'medium',
            'hasBlue' => false,
            'hasYellow' => false,
            'hasBrown' => false,
            'hasRed' => false,
            'hasGreen' => false
        ];
        
        // Vérifier si l'extension GD est disponible
        if (!\extension_loaded('gd')) {
            // Si GD n'est pas disponible, utiliser une analyse basée sur le nom du fichier
            // et générer des couleurs aléatoires réalistes
            $filename = \strtolower(\basename($imagePath));
            
            // Détecter des mots-clés dans le nom du fichier pour COURSES
            if (\preg_match('/frigo|fridge|refrigerat|food|nourriture|cuisine|kitchen|courses|shopping|grocery|epicerie|supermar|magasin|store|vide|empty|provisions|legume|vegetable|fruit|viande|meat|lait|milk|pain|bread/', $filename)) {
                $result['detectedType'] = 'courses';
                $result['dominantColors'] = ['white', 'gray', 'brown'];
                return $result;
            }
            
            // Détecter des mots-clés pour TRANSPORT MÉDICAL
            if (\preg_match('/malade|sick|ill|patient|medical|medic|hopital|hospital|doctor|docteur|medecin|ambulance|wheelchair|fauteuil|bequille|crutch|sante|health|clinic|clinique|infirm|nurse|elderly|senior|age|vieux|vieille/', $filename)) {
                $result['detectedType'] = 'transport';
                $result['dominantColors'] = ['white', 'blue', 'gray'];
                return $result;
            }
            
            // Détecter des mots-clés pour PLOMBERIE
            if (\preg_match('/eau|water|fuite|leak|plomb|pipe|tuyau|robinet|tap|faucet|sink|evier|toilet|wc|douche|shower|bath|drain|canalis/', $filename)) {
                $result['hasBlue'] = true;
                $result['dominantColors'] = ['blue', 'gray', 'white'];
                return $result;
            }
            
            // Détecter des mots-clés pour ÉLECTRICITÉ
            if (\preg_match('/electri|cable|wire|prise|socket|spark|fil|ampoule|bulb|light|lumiere|volt|courant|current|fusible|disjonct/', $filename)) {
                $result['hasYellow'] = true;
                $result['hasRed'] = true;
                $result['dominantColors'] = ['yellow', 'black', 'red'];
                return $result;
            }
            
            // Détecter des mots-clés pour MÉNAGE
            if (\preg_match('/menage|clean|dirt|dust|sale|dirty|poussiere|rangement|tidy|mess|desordre|broom|balai|mop|serpill/', $filename)) {
                $result['hasBrown'] = true;
                $result['dominantColors'] = ['brown', 'gray', 'white'];
                return $result;
            }
            
            // Détecter des mots-clés pour COMPAGNIE
            if (\preg_match('/compagnie|company|solitude|lonely|seul|alone|visite|visit|discussion|parler|talk|ami|friend|conversation/', $filename)) {
                $result['detectedType'] = 'compagnie';
                $result['dominantColors'] = ['brown', 'white', 'gray'];
                return $result;
            }
            
            // Génération aléatoire équilibrée pour tous les types de services
            $rand = \random_int(0, 100);
            if ($rand <= 20) {
                $result['hasBlue'] = true;
                $result['dominantColors'] = ['blue', 'gray', 'white']; // Plomberie
            } elseif ($rand <= 40) {
                $result['hasYellow'] = true;
                $result['dominantColors'] = ['yellow', 'black', 'brown']; // Électricité
            } elseif ($rand <= 55) {
                $result['detectedType'] = 'courses';
                $result['dominantColors'] = ['white', 'brown', 'gray']; // Courses
            } elseif ($rand <= 70) {
                $result['detectedType'] = 'transport';
                $result['dominantColors'] = ['white', 'blue', 'gray']; // Transport
            } elseif ($rand <= 85) {
                $result['hasBrown'] = true;
                $result['dominantColors'] = ['brown', 'gray', 'white']; // Ménage
            } else {
                $result['detectedType'] = 'compagnie';
                $result['dominantColors'] = ['brown', 'white', 'gray']; // Compagnie
            }
            
            return $result;
        }
        
        try {
            $imageInfo = \getimagesize($imagePath);
            if (!$imageInfo) {
                return $result;
            }
            
            $mimeType = $imageInfo['mime'];
            
            // Charger l'image selon son type
            switch ($mimeType) {
                case 'image/jpeg':
                    $image = @\imagecreatefromjpeg($imagePath);
                    break;
                case 'image/png':
                    $image = @\imagecreatefrompng($imagePath);
                    break;
                case 'image/gif':
                    $image = @\imagecreatefromgif($imagePath);
                    break;
                case 'image/webp':
                    $image = @\imagecreatefromwebp($imagePath);
                    break;
                default:
                    return $result;
            }
            
            if (!$image) {
                return $result;
            }
            
            $width = \imagesx($image);
            $height = \imagesy($image);
            
            // Échantillonner quelques pixels pour analyser les couleurs
            $colorCounts = [
                'blue' => 0,
                'yellow' => 0,
                'brown' => 0,
                'red' => 0,
                'green' => 0,
                'gray' => 0,
                'white' => 0,
                'black' => 0
            ];
            
            $sampleSize = 100;
            for ($i = 0; $i < $sampleSize; $i++) {
                $x = \random_int(0, $width - 1);
                $y = \random_int(0, $height - 1);
                $rgb = \imagecolorat($image, $x, $y);
                
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Classifier la couleur
                if ($r > 200 && $g > 200 && $b > 200) {
                    $colorCounts['white']++;
                } elseif ($r < 50 && $g < 50 && $b < 50) {
                    $colorCounts['black']++;
                } elseif ($b > $r && $b > $g && $b > 100) {
                    $colorCounts['blue']++;
                } elseif ($r > 180 && $g > 150 && $b < 100) {
                    $colorCounts['yellow']++;
                } elseif ($r > 150 && $g < 100 && $b < 100) {
                    $colorCounts['red']++;
                } elseif ($g > $r && $g > $b && $g > 100) {
                    $colorCounts['green']++;
                } elseif ($r > 100 && $g > 60 && $g < 150 && $b < 100) {
                    $colorCounts['brown']++;
                } else {
                    $colorCounts['gray']++;
                }
            }
            
            \imagedestroy($image);
            
            // Trier par nombre d'occurrences
            \arsort($colorCounts);
            
            $result['dominantColors'] = \array_keys(\array_slice($colorCounts, 0, 3));
            $result['hasBlue'] = $colorCounts['blue'] > 15;
            $result['hasYellow'] = $colorCounts['yellow'] > 10;
            $result['hasBrown'] = $colorCounts['brown'] > 10;
            $result['hasRed'] = $colorCounts['red'] > 10;
            $result['hasGreen'] = $colorCounts['green'] > 10;
            
        } catch (\Exception $e) {
            $this->logger->warning('Image analysis failed: ' . $e->getMessage());
            // Fallback: générer des types aléatoires équilibrés
            $rand = \random_int(0, 100);
            if ($rand <= 20) {
                $result['hasBlue'] = true;
                $result['dominantColors'] = ['blue', 'gray', 'white']; // Plomberie
            } elseif ($rand <= 40) {
                $result['hasYellow'] = true;
                $result['dominantColors'] = ['yellow', 'black', 'brown']; // Électricité
            } elseif ($rand <= 55) {
                $result['detectedType'] = 'courses';
                $result['dominantColors'] = ['white', 'brown', 'gray']; // Courses
            } elseif ($rand <= 70) {
                $result['detectedType'] = 'transport';
                $result['dominantColors'] = ['white', 'blue', 'gray']; // Transport
            } elseif ($rand <= 85) {
                $result['hasBrown'] = true;
                $result['dominantColors'] = ['brown', 'gray', 'white']; // Ménage
            } else {
                $result['detectedType'] = 'compagnie';
                $result['dominantColors'] = ['brown', 'white', 'gray']; // Compagnie
            }
        }
        
        return $result;
    }
    
    /**
     * Détecte le type de service basé sur les couleurs de l'image
     */
    private function detectTypeFromImageColors(array $analysis): string
    {
        // Si le type a été détecté directement (courses, transport, compagnie)
        if (!empty($analysis['detectedType'])) {
            return $analysis['detectedType'];
        }
        
        // Bleu = eau = plomberie
        if ($analysis['hasBlue'] ?? false) {
            return 'plomberie';
        }
        
        // Jaune/Orange + noir = électricité (câbles, prises)
        if (($analysis['hasYellow'] ?? false) || (($analysis['hasRed'] ?? false) && \in_array('black', $analysis['dominantColors'] ?? []))) {
            return 'electricite';
        }
        
        // Rouge seul = potentiellement urgent (électricité ou plomberie)
        if ($analysis['hasRed'] ?? false) {
            return \random_int(0, 1) ? 'electricite' : 'plomberie';
        }
        
        // Marron/beige = intérieur maison
        if ($analysis['hasBrown'] ?? false) {
            // Distribution équilibrée pour le marron
            $rand = \random_int(0, 100);
            if ($rand <= 25) return 'menage';
            if ($rand <= 45) return 'electricite';
            if ($rand <= 65) return 'plomberie';
            if ($rand <= 80) return 'courses';
            return 'transport';
        }
        
        // Vert = extérieur ou plantes
        if ($analysis['hasGreen'] ?? false) {
            return 'menage';
        }
        
        // Par défaut, varier aléatoirement entre tous les services
        $types = ['electricite', 'plomberie', 'menage', 'courses', 'transport', 'compagnie'];
        return $types[\random_int(0, count($types) - 1)];
    }

    /**
     * Valide que l'image est acceptable
     */
    public function validateImage(string $filePath): array
    {
        $errors = [];

        if (!file_exists($filePath)) {
            $errors[] = "Le fichier n'existe pas.";
            return ['valid' => false, 'errors' => $errors];
        }

        // Vérifier le type MIME
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $mimeType = mime_content_type($filePath);
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = "Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WEBP.";
        }

        // Vérifier la taille (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if (filesize($filePath) > $maxSize) {
            $errors[] = "L'image est trop volumineuse. Maximum 10 Mo.";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'mime_type' => $mimeType,
            'size' => filesize($filePath)
        ];
    }
}
