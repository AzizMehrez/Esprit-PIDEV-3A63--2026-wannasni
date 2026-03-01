<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Image Content Moderation Service
 *
 * Calls the Python image_moderation_service.py script which runs:
 *   - Falconsai/nsfw_image_detection  (nudity/NSFW)
 *   - OpenAI CLIP zero-shot           (violence, weapons, political, drugs)
 *
 * Returns ['safe' => true] or ['safe' => false, 'reason' => '...', 'categories' => [...]]
 */
class ImageModerationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly string $pythonPath = 'python',
    ) {}

    /**
     * Check an image file for sensitive content.
     *
     * @param  string $imagePath  Absolute filesystem path to the image
     * @return array{safe: bool, reason?: string, categories?: string[], confidence?: float}
     */
    public function checkImage(string $imagePath): array
    {
        $scriptPath = $this->projectDir . '/image_moderation_service.py';

        $this->logger->info('ImageModerationService: starting check', [
            'image' => basename($imagePath),
        ]);

        if (!file_exists($scriptPath)) {
            $this->logger->error('ImageModerationService: script not found – blocking upload (fail-closed)', [
                'script' => $scriptPath,
            ]);
            return [
                'safe' => false,
                'reason' => 'moderation_unavailable',
                'categories' => ['moderation_unavailable'],
            ];
        }

        if (!file_exists($imagePath)) {
            return ['safe' => false, 'reason' => 'Image file not found for moderation check.'];
        }

        $cmd = sprintf(
            '%s %s check %s',
            escapeshellarg($this->pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($imagePath)
        );

        // Build environment: inherit the FULL system environment, then overlay our vars.
        // On Windows, $_ENV is often empty (depends on variables_order in php.ini).
        // getenv() retrieves the real OS environment so Python can find its libraries.
        $sysEnv = getenv();
        $env = array_merge(is_array($sysEnv) ? $sysEnv : [], $_ENV ?? [], [
            'PYTHONIOENCODING'       => 'utf-8',
            'PYTHONDONTWRITEBYTECODE' => '1',
            'TRANSFORMERS_VERBOSITY' => 'error',
            'HF_HUB_DISABLE_PROGRESS_BARS' => '1',
            'TF_CPP_MIN_LOG_LEVEL'   => '3',
            'TOKENIZERS_PARALLELISM' => 'false',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            $this->logger->error('ImageModerationService: failed to start Python process – blocking upload (fail-closed)');
            return [
                'safe' => false,
                'reason' => 'moderation_unavailable',
                'categories' => ['moderation_unavailable'],
            ];
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timeout = 120; // seconds – ViT-L/14 needs time on first model load
        $start   = time();

        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if ((time() - $start) > $timeout) {
                $this->logger->warning('ImageModerationService: timeout exceeded');
                proc_terminate($process);
                break;
            }

            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }

            usleep(50_000); // 50 ms poll
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->logger->info('ImageModerationService: Python process completed', [
            'exitCode'  => $exitCode,
            'stdoutLen' => strlen($stdout),
            'image'     => basename($imagePath),
        ]);

        if (!mb_check_encoding($stdout, 'UTF-8')) {
            $stdout = mb_convert_encoding($stdout, 'UTF-8', 'Windows-1252');
        }

        $stdout = trim($stdout);

        if (empty($stdout)) {
            $this->logger->error('ImageModerationService: empty output from Python – blocking upload (fail-closed)', [
                'stderr' => trim($stderr),
            ]);
            return [
                'safe' => false,
                'reason' => 'moderation_unavailable',
                'categories' => ['moderation_unavailable'],
            ];
        }

        $result = json_decode($stdout, true);

        if (!is_array($result)) {
            $this->logger->error('ImageModerationService: invalid JSON from Python – blocking upload (fail-closed)', [
                'stdout' => $stdout,
                'stderr' => trim($stderr),
            ]);
            return [
                'safe' => false,
                'reason' => 'moderation_unavailable',
                'categories' => ['moderation_unavailable'],
            ];
        }

        if (isset($result['safe']) && !$result['safe']) {
            $this->logger->info('ImageModerationService: image rejected', [
                'categories' => $result['categories'] ?? [],
                'confidence' => $result['confidence'] ?? null,
                'image'      => basename($imagePath),
            ]);
        }

        return $result;
    }
}
