<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Captcha Service
 *
 * Calls the Python captcha_service.py script to generate image captchas.
 * Stores expected answers in the session and validates user input.
 * Tracks failed login attempts to conditionally require captcha (≥3 failures).
 */
class CaptchaService
{
    private const SESSION_ATTEMPTS   = 'login_failed_attempts';
    private const SESSION_ANSWER     = 'captcha_answer';
    private const SESSION_CREATED_AT = 'captcha_generated_at';
    private const MAX_ATTEMPTS_BEFORE_CAPTCHA = 3;
    private const CAPTCHA_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly RequestStack    $requestStack,
        private readonly string          $projectDir,
        private readonly string          $pythonPath = 'python',
    ) {}

    /* ------------------------------------------------------------------ */
    /*  Public API                                                         */
    /* ------------------------------------------------------------------ */

    /**
     * Whether the current session must solve a captcha before login.
     */
    public function isCaptchaRequired(): bool
    {
        return $this->getFailedAttempts() >= self::MAX_ATTEMPTS_BEFORE_CAPTCHA;
    }

    public function getFailedAttempts(): int
    {
        return $this->requestStack->getSession()->get(self::SESSION_ATTEMPTS, 0);
    }

    /**
     * Generate a new captcha, store the answer in the session,
     * and return ['image' => 'data:image/png;base64,...'] or null on error.
     */
    public function generateCaptcha(): ?array
    {
        $scriptPath = $this->projectDir . '/captcha_service.py';

        if (!file_exists($scriptPath)) {
            $this->logger->warning('CaptchaService: Python script not found', [
                'script' => $scriptPath,
            ]);
            return null;
        }

        $result = $this->runPython($scriptPath);

        if ($result && isset($result['answer'], $result['image_base64'])) {
            $session = $this->requestStack->getSession();
            $session->set(self::SESSION_ANSWER, strtoupper($result['answer']));
            $session->set(self::SESSION_CREATED_AT, time());

            return ['image' => $result['image_base64']];
        }

        return null;
    }

    /**
     * Validate user-submitted captcha input against the session answer.
     * The answer is consumed (single-use) regardless of correctness.
     */
    public function validateCaptcha(string $userInput): bool
    {
        $session        = $this->requestStack->getSession();
        $expectedAnswer = $session->get(self::SESSION_ANSWER);
        $generatedAt    = $session->get(self::SESSION_CREATED_AT, 0);

        // Consume — single use
        $session->remove(self::SESSION_ANSWER);
        $session->remove(self::SESSION_CREATED_AT);

        if (!$expectedAnswer) {
            return false;
        }

        // Expired
        if ((time() - $generatedAt) > self::CAPTCHA_TTL_SECONDS) {
            return false;
        }

        return strtoupper(trim($userInput)) === $expectedAnswer;
    }

    public function incrementFailedAttempts(): void
    {
        $session  = $this->requestStack->getSession();
        $attempts = $session->get(self::SESSION_ATTEMPTS, 0);
        $session->set(self::SESSION_ATTEMPTS, $attempts + 1);
    }

    public function resetFailedAttempts(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_ATTEMPTS);
        $session->remove(self::SESSION_ANSWER);
        $session->remove(self::SESSION_CREATED_AT);
    }

    /* ------------------------------------------------------------------ */
    /*  Python subprocess runner (matches existing project pattern)        */
    /* ------------------------------------------------------------------ */

    private function runPython(string $scriptPath): ?array
    {
        $cmd = sprintf(
            '%s %s generate',
            escapeshellarg($this->pythonPath),
            escapeshellarg($scriptPath)
        );

        $sysEnv = getenv();
        $env = array_merge(is_array($sysEnv) ? $sysEnv : [], $_ENV ?? [], [
            'PYTHONIOENCODING'        => 'utf-8',
            'PYTHONDONTWRITEBYTECODE' => '1',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            $this->logger->error('CaptchaService: failed to start Python process');
            return null;
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->logger->error('CaptchaService: Python script exited with error', [
                'exitCode' => $exitCode,
                'stderr'   => mb_substr($stderr, 0, 500),
            ]);
            return null;
        }

        $data = json_decode(trim($stdout), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('CaptchaService: invalid JSON from Python', [
                'stdout' => mb_substr($stdout, 0, 500),
            ]);
            return null;
        }

        return $data;
    }
}
