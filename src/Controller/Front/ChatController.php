<?php

namespace App\Controller\Front;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{_locale}/api/chat', requirements: ['_locale' => 'fr|en|ar'])]
class ChatController extends AbstractController
{
    private string $openRouterApiKey = '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';
    private string $openRouterUrl = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(
        private Connection $connection,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {
    }

    #[Route('/proxy', name: 'app_chat_proxy', methods: ['POST'])]
    public function proxy(Request $request): JsonResponse
    {
        // CORS headers
        $response = new JsonResponse();
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'POST');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');

        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON input'], 400);
        }

        // Add user context if user is logged in
        $userInterface = $this->getUser();
        if ($userInterface && isset($data['messages'])) {
            // Fetch actual user data from database
            $userData = $this->connection->executeQuery(
                'SELECT first_name, last_name, email FROM user WHERE email = ?',
                [$userInterface->getUserIdentifier()]
            )->fetchAssociative();
            
            if ($userData) {
                $userContext = "User Context: " . $userData['first_name'] . " " . $userData['last_name'] . 
                              " (Email: " . $userData['email'] . ")";
                
                // Add user context to the first system message or create one
                $systemMessage = [
                    'role' => 'system',
                    'content' => $userContext . "\n\nYou are a helpful assistant for the WANNASNI health platform. Help users with their health, activities, services, and nutrition questions."
                ];
                
                array_unshift($data['messages'], $systemMessage);
            }
        }

        $ch = curl_init($this->openRouterUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openRouterApiKey
        ]);

        $response_data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            return new JsonResponse(['error' => 'API connection failed: ' . curl_error($ch)], 500);
        }

        curl_close($ch);
        
        return new JsonResponse(json_decode($response_data, true), $httpCode);
    }

    /** Fields that must never be returned to the chat client */
    private const SENSITIVE_FIELDS = [
        'password', 'reset_token', 'reset_token_expires_at',
        'verification_code', 'face_encoding', 'face_image_path', 'face_consent_at',
    ];

    private function sanitizeRows(array $rows): array
    {
        return array_map(function (array $row) {
            foreach (self::SENSITIVE_FIELDS as $field) {
                unset($row[$field]);
            }
            return $row;
        }, $rows);
    }

    #[Route('/db-query', name: 'app_chat_db_query', methods: ['POST'])]
    public function databaseQuery(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? '';
        
        try {
            switch ($action) {
                case 'get_tables':
                    $result = $this->connection->executeQuery(
                        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()"
                    )->fetchAllAssociative();
                    $tables = array_column($result, 'table_name');
                    return new JsonResponse(['success' => true, 'data' => $tables]);
                
                case 'get_schema':
                    $tables = $this->connection->executeQuery(
                        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()"
                    )->fetchAllAssociative();
                    $schema = [];
                    foreach ($tables as $table) {
                        $columns = $this->connection->executeQuery(
                            "SELECT column_name as name, column_type as type, column_key as `key` 
                             FROM information_schema.columns 
                             WHERE table_schema = DATABASE() AND table_name = ?",
                            [$table['table_name']]
                        )->fetchAllAssociative();
                        $schema[$table['table_name']] = $columns;
                    }
                    return new JsonResponse(['success' => true, 'data' => $schema]);
                
                case 'get_table_data':
                    $table = $data['table'] ?? '';
                    if (!preg_match('/^[a-z_]+$/', $table)) {
                        throw new \Exception('Invalid table name');
                    }
                    $result = $this->connection->executeQuery("SELECT * FROM `{$table}` LIMIT 100")->fetchAllAssociative();
                    return new JsonResponse(['success' => true, 'data' => $this->sanitizeRows($result)]);
                
                case 'query':
                case 'execute':
                    $sql = $data['sql'] ?? '';
                    $sqlLower = strtolower(trim($sql));
                    
                    // Security: Only allow SELECT queries
                    if (!str_contains($sqlLower, 'select')) {
                        throw new \Exception('Only SELECT queries are allowed');
                    }
                    if (str_contains($sqlLower, 'drop') || str_contains($sqlLower, 'delete') || 
                        str_contains($sqlLower, 'insert') || str_contains($sqlLower, 'update') ||
                        str_contains($sqlLower, 'truncate')) {
                        throw new \Exception('Destructive queries are not allowed');
                    }
                    
                    $result = $this->connection->executeQuery($sql)->fetchAllAssociative();
                    return new JsonResponse(['success' => true, 'data' => $this->sanitizeRows($result)]);
                
                default:
                    throw new \Exception('Unknown action: ' . $action);
            }
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    #[Route('/profile-update', name: 'app_chat_profile_update', methods: ['POST'])]
    public function profileUpdate(Request $request): JsonResponse
    {
        $userInterface = $this->getUser();
        if (!$userInterface) {
            return new JsonResponse(['success' => false, 'error' => 'Not authenticated'], 401);
        }

        /** @var User $user */
        $user = $this->userRepository->findOneBy(['email' => $userInterface->getUserIdentifier()]);
        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['fields']) || !is_array($data['fields'])) {
            return new JsonResponse(['success' => false, 'error' => 'Invalid payload'], 400);
        }

        $fields = $data['fields'];
        $updated = [];

        $allowedFields = [
            'firstName', 'lastName', 'phone', 'dateNaissance',
            'adresse', 'ville', 'codePostal', 'pays', 'location'
        ];

        foreach ($allowedFields as $field) {
            if (!array_key_exists($field, $fields)) continue;
            $value = trim((string) $fields[$field]);
            if ($value === '') continue;

            switch ($field) {
                case 'firstName':    $user->setFirstName($value);    break;
                case 'lastName':     $user->setLastName($value);     break;
                case 'phone':        $user->setPhone($value);        break;
                case 'dateNaissance':
                    try {
                        $user->setDateNaissance(new \DateTime($value));
                    } catch (\Exception $e) { continue 2; }
                    break;
                case 'adresse':      $user->setAdresse($value);      break;
                case 'ville':        $user->setVille($value);        break;
                case 'codePostal':   $user->setCodePostal($value);   break;
                case 'pays':         $user->setPays($value);         break;
                case 'location':     $user->setLocation($value);     break;
            }
            $updated[] = $field;
        }

        if (empty($updated)) {
            return new JsonResponse(['success' => false, 'error' => 'No valid fields provided']);
        }

        $this->entityManager->flush();

        // Compute remaining missing fields after update
        $profileFields = [
            'firstName'    => $user->getFirstName(),
            'lastName'     => $user->getLastName(),
            'phone'        => $user->getPhone(),
            'dateNaissance'=> $user->getDateNaissance()?->format('Y-m-d'),
            'adresse'      => $user->getAdresse(),
            'ville'        => $user->getVille(),
            'codePostal'   => $user->getCodePostal(),
            'pays'         => $user->getPays(),
            'location'     => $user->getLocation(),
        ];
        $missing = array_keys(array_filter($profileFields, fn($v) => empty($v)));
        $complete = empty($missing);

        return new JsonResponse([
            'success'  => true,
            'updated'  => $updated,
            'missing'  => $missing,
            'complete' => $complete,
        ]);
    }

    #[Route('/user-context', name: 'app_chat_user_context', methods: ['GET'])]
    public function getUserContext(): JsonResponse
    {
        $userInterface = $this->getUser();
        
        if (!$userInterface) {
            return new JsonResponse([
                'success' => false,
                'error' => 'User not logged in'
            ]);
        }

        // Get actual User entity from database
        $user = $this->connection->executeQuery(
            'SELECT * FROM user WHERE email = ?',
            [$userInterface->getUserIdentifier()]
        )->fetchAssociative();

        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'User not found']);
        }

        // Get user activity summary
        $userId = $user['id'];
        
        $healthEntries = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM health_journal WHERE senior_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $participations = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM participations WHERE senior_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $dietRequests = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM demande_regime WHERE user_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $prescribedDiets = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM regime_prescrit WHERE user_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $serviceRequests = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM service_request WHERE user_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $treatments = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM treatment WHERE senior_id = ?',
            [$userId]
        )->fetchOne() ?? 0;
        
        $unreadNotifications = $this->connection->executeQuery(
            'SELECT COUNT(*) as count FROM notification WHERE is_read = 0'
        )->fetchOne() ?? 0;

        return new JsonResponse([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'roles' => json_decode($user['roles'], true),
                'status' => $user['status'],
                'ville' => $user['ville'],
                'pays' => $user['pays'],
                'adresse' => $user['adresse'],
                'code_postal' => $user['code_postal'],
                'location' => $user['location'],
                'date_naissance' => $user['date_naissance'],
                'user_domain' => $user['user_domain'],
                'created_at' => $user['created_at'],
            ],
            'summary' => [
                'health_entries' => (int)$healthEntries,
                'participations' => (int)$participations,
                'diet_requests' => (int)$dietRequests,
                'prescribed_diets' => (int)$prescribedDiets,
                'service_requests' => (int)$serviceRequests,
                'treatments' => (int)$treatments,
                'unread_notifications' => (int)$unreadNotifications,
            ]
        ]);
    }
}