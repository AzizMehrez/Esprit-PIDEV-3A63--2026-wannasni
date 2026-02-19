<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Face Recognition Service using Python face_recognition library
 * 
 * This service handles:
 * - Face detection in images
 * - Face encoding computation (128-dimensional vector)
 * - Face matching against existing users (1:N)
 * - Face enrollment for new users
 */
class FaceService
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private string $projectDir;
    private string $pythonPath;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        string $projectDir,
        string $pythonPath = 'python'
    ) {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->projectDir = $projectDir;
        $this->pythonPath = $pythonPath;
    }

    /**
     * Execute Python face recognition script
     */
    private function executePython(string $command, array $args = []): array
    {
        $scriptPath = $this->projectDir . '/face_recognition_service.py';
        
        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Face recognition script not found: ' . $scriptPath);
        }
        
        $cmdArgs = array_map('escapeshellarg', $args);
        $cmd = sprintf(
            '%s %s %s %s',
            escapeshellarg($this->pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($command),
            implode(' ', $cmdArgs)
        );
        
        // Set proper encoding environment
        $sysEnv = getenv();
        $env = array_merge(is_array($sysEnv) ? $sysEnv : [], $_ENV ?? [], [
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONDONTWRITEBYTECODE' => '1',
            'OPENCV_LOG_LEVEL' => 'SILENT'
        ]);

        $descriptors = [
            0 => ["pipe", "r"],  // stdin
            1 => ["pipe", "w"],  // stdout  
            2 => ["pipe", "w"]   // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);
        
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start Python process');
        }

        // Close stdin
        fclose($pipes[0]);
        
        // Read stdout and stderr with UTF-8 handling
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        
        $stdout = '';
        $stderr = '';
        
        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
        }
        
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($stdout, 'UTF-8')) {
            $stdout = mb_convert_encoding($stdout, 'UTF-8', 'Windows-1252');
        }

        // Debug logging
        if (!empty($stderr)) {
            $this->logger->warning('Python stderr: ' . $stderr);
        }

        // Try to parse JSON output
        $result = json_decode($stdout, true);
        
        if ($result === null) {
            // Fallback: try to find JSON line in case of mixed output
            $lines = explode("\n", $stdout);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || !str_contains($line, '{')) continue;
                
                $decoded = json_decode($line, true);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
            
            $this->logger->error('Python script returned invalid JSON: ' . $stdout);
            throw new \RuntimeException('Face recognition failed: Invalid response from Python script');
        }
        
        return $result;
    }

    /**
     * Save base64 image to temporary file
     */
    private function saveImageToTemp(string $imageData): string
    {
        // Remove data URL prefix if present
        if (str_contains($imageData, ',')) {
            $imageData = substr($imageData, strpos($imageData, ',') + 1);
        }
        
        $binaryData = base64_decode($imageData);
        $tempFile = sys_get_temp_dir() . '/face_' . uniqid() . '.jpg';
        file_put_contents($tempFile, $binaryData);
        
        return $tempFile;
    }

    /**
     * Detect faces in an image
     * 
     * @param string $imageData Base64-encoded image data
     * @return array Detection result with faces_count
     */
    public function detectFaces(string $imageData): array
    {
        $tempFile = $this->saveImageToTemp($imageData);
        
        try {
            $result = $this->executePython('detect', [$tempFile]);
            return $result;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Encode a face and return 128-dimensional vector
     * 
     * @param string $imageData Base64-encoded image data
     * @return array Encoding result with 'encoding' array
     */
    public function encodeFace(string $imageData): array
    {
        $tempFile = $this->saveImageToTemp($imageData);
        
        try {
            $result = $this->executePython('encode', [$tempFile]);
            return $result;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Get all users with face encodings as JSON file for Python matching
     */
    private function getUsersJsonPath(): string
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();
        
        $usersData = [];
        foreach ($users as $user) {
            $encoding = $user->getFaceEncoding();
            if ($encoding) {
                $usersData[] = [
                    'id' => $user->getId(),
                    'name' => $user->getFullName(),
                    'email' => $user->getEmail(),
                    'encoding' => $encoding,
                ];
            }
        }
        
        $jsonPath = sys_get_temp_dir() . '/wannasni_users_' . uniqid() . '.json';
        file_put_contents($jsonPath, json_encode($usersData));
        
        return $jsonPath;
    }

    /**
     * Detect and identify a face against existing users
     * Returns matched user data if found, null otherwise
     * 
     * @param string $imageData Base64-encoded image data
     * @return array|null Match result with user info, or null if no match
     */
    public function detectAndIdentify(string $imageData): ?array
    {
        $tempFile = $this->saveImageToTemp($imageData);
        $usersJsonPath = $this->getUsersJsonPath();
        
        try {
            $result = $this->executePython('match', [$tempFile, $usersJsonPath]);
            
            if (!$result['success']) {
                throw new \RuntimeException($result['error'] ?? 'Face matching failed');
            }
            
            if ($result['matched']) {
                return [
                    'personId' => $result['user']['id'],
                    'name' => $result['user']['name'],
                    'email' => $result['user']['email'],
                    'confidence' => $result['confidence'],
                    'encoding' => $result['encoding'] ?? null,
                ];
            }
            
            // No match, but return encoding for enrollment
            return [
                'matched' => false,
                'encoding' => $result['encoding'] ?? null,
            ];
        } finally {
            @unlink($tempFile);
            @unlink($usersJsonPath);
        }
    }

    /**
     * Encode a face for enrollment
     * 
     * @param string $imageData Base64-encoded image data
     * @return array Face encoding (128-dimensional array)
     */
    public function getEncodingForEnrollment(string $imageData): array
    {
        $result = $this->encodeFace($imageData);
        
        if (!$result['success']) {
            throw new \RuntimeException($result['error'] ?? 'Face encoding failed');
        }
        
        return $result['encoding'];
    }

    /**
     * Verify a face image and check for matches
     * Used by the verification endpoint
     * 
     * @param string $imageData Base64-encoded image data
     * @return array Verification result
     */
    public function verifyFace(string $imageData): array
    {
        $tempFile = $this->saveImageToTemp($imageData);
        $usersJsonPath = $this->getUsersJsonPath();
        
        try {
            $result = $this->executePython('match', [$tempFile, $usersJsonPath]);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Face verification failed',
                    'code' => $result['code'] ?? 'ERROR',
                ];
            }
            
            if ($result['matched']) {
                // Face already exists
                return [
                    'success' => true,
                    'matched' => true,
                    'code' => 'FACE_EXISTS',
                    'existingUser' => [
                        'id' => $result['user']['id'],
                        'name' => $result['user']['name'],
                        'email' => $result['user']['email'],
                    ],
                    'confidence' => $result['confidence'],
                    'encoding' => $result['encoding'],
                ];
            }
            
            // Face is unique
            return [
                'success' => true,
                'matched' => false,
                'code' => 'FACE_OK',
                'faceDetected' => true,
                'encoding' => $result['encoding'],
                'message' => 'Face verified successfully. No existing match found.',
            ];
        } finally {
            @unlink($tempFile);
            @unlink($usersJsonPath);
        }
    }

    /**
     * Enroll a new face - returns the encoding to be stored
     * 
     * @param string $userName User's full name (not used, kept for compatibility)
     * @param string $userId User's database ID (not used, kept for compatibility)
     * @param string $imageData Base64-encoded face image
     * @return array The face encoding to store
     */
    public function enrollFace(string $userName, string $userId, string $imageData): array
    {
        return $this->getEncodingForEnrollment($imageData);
    }
}
