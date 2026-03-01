<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VerificationRequest;
use App\Repository\PostRepository;
use App\Repository\PostLikeRepository;
use App\Repository\PostCommentRepository;
use App\Repository\UserConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Bridges PHP to the Python verification_analyzer.py AI script.
 * Gathers user profile + activity data, calls Python, parses result.
 * Auto-rejects if AI score < 0.3 or content quality is bad.
 */
class VerificationAnalyzerService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $em,
        private readonly string $projectDir,
        private readonly string $pythonPath = 'python',
    ) {}

    /**
     * Run AI analysis on a verification request.
     * Returns the AI report array and updates the request entity.
     */
    public function analyze(VerificationRequest $request): array
    {
        $user = $request->getUser();

        // Gather user data for AI
        $userData = $this->gatherUserData($user);

        // Write to temp JSON file
        $tmpFile = tempnam(sys_get_temp_dir(), 'vr_');
        file_put_contents($tmpFile, json_encode($userData, JSON_UNESCAPED_UNICODE));

        try {
            $result = $this->callPython($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        // Update request with AI result
        $request->setAiReport($result);
        $request->setAiScore($result['score'] ?? null);

        // Auto-reject on bad AI result
        if (($result['decision'] ?? '') === 'reject') {
            $request->setStatus(VerificationRequest::STATUS_AI_REJECTED);
            $request->setReviewNote('Auto-rejected by AI: ' . ($result['decision_reason'] ?? 'Low score'));
            $request->setReviewedAt(new \DateTime());
        }

        $this->em->flush();

        return $result;
    }

    /**
     * Gather all relevant profile + activity data for the Python script.
     */
    private function gatherUserData(User $user): array
    {
        // Post data
        $postRepo = $this->em->getRepository(\App\Entity\Post::class);
        $posts = $postRepo->findBy(['author' => $user]);
        $postContents = [];
        foreach ($posts as $post) {
            if ($post->getContent()) {
                $postContents[] = $post->getContent();
            }
        }

        // Likes received across all posts
        $likesReceived = 0;
        $commentsReceived = 0;
        foreach ($posts as $post) {
            $likesReceived += $post->getLikesCount();
            $commentsReceived += $post->getCommentsCount();
        }

        // Connection count
        $connectionRepo = $this->em->getRepository(\App\Entity\UserConnection::class);
        $connectionCount = $connectionRepo->count(['userA' => $user])
                         + $connectionRepo->count(['userB' => $user]);

        return [
            'userId' => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'email' => $user->getEmail(),
            'imageProfil' => $user->getImageProfil(),
            'bio' => $user->getBio(),
            'phone' => $user->getPhone(),
            'dateNaissance' => $user->getDateNaissance()?->format('Y-m-d'),
            'location' => $user->getLocation(),
            'ville' => $user->getVille(),
            'createdAt' => $user->getCreatedAt()?->format('c'),
            'postCount' => count($posts),
            'postsContent' => $postContents,
            'likesReceived' => $likesReceived,
            'commentsReceived' => $commentsReceived,
            'connectionCount' => $connectionCount,
        ];
    }

    /**
     * Call Python verification_analyzer.py script.
     */
    private function callPython(string $jsonFilePath): array
    {
        $scriptPath = $this->projectDir . '/verification_analyzer.py';

        if (!file_exists($scriptPath)) {
            $this->logger->warning('VerificationAnalyzerService: script not found', ['script' => $scriptPath]);
            return ['score' => 0.5, 'decision' => 'review', 'decision_reason' => 'AI analyzer unavailable', 'warning' => 'script_not_found'];
        }

        $cmd = sprintf(
            '%s %s analyze %s',
            escapeshellarg($this->pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($jsonFilePath)
        );

        $sysEnv = getenv();
        $env = array_merge(is_array($sysEnv) ? $sysEnv : [], $_ENV ?? [], [
            'PYTHONIOENCODING' => 'utf-8',
            'PYTHONDONTWRITEBYTECODE' => '1',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            $this->logger->error('VerificationAnalyzerService: failed to start Python process');
            return ['score' => 0.5, 'decision' => 'review', 'decision_reason' => 'AI process failed to start'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timeout = 30;
        $start = time();

        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if ((time() - $start) > $timeout) {
                $this->logger->warning('VerificationAnalyzerService: timeout');
                proc_terminate($process);
                break;
            }

            if (feof($pipes[1]) && feof($pipes[2])) break;
            usleep(50_000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->logger->error('VerificationAnalyzerService: Python exited with code ' . $exitCode, ['stderr' => $stderr]);
            return ['score' => 0.5, 'decision' => 'review', 'decision_reason' => 'AI script error: ' . trim($stderr)];
        }

        // Extract JSON from stdout (skip any non-JSON lines)
        $lines = explode("\n", trim($stdout));
        $jsonLine = '';
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if (str_starts_with($line, '{')) {
                $jsonLine = $line;
                break;
            }
        }

        $result = json_decode($jsonLine, true);
        if (!is_array($result)) {
            $this->logger->error('VerificationAnalyzerService: invalid JSON output', ['stdout' => $stdout]);
            return ['score' => 0.5, 'decision' => 'review', 'decision_reason' => 'AI returned invalid output'];
        }

        return $result;
    }
}
