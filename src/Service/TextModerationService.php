<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

/**
 * Text Content Moderation Service
 *
 * Calls the Python text_moderation_service.py script which runs:
 *   - unitary/multilingual-toxic-xlm-roberta  (toxicity classification)
 *   - joeddav/xlm-roberta-large-xnli          (zero-shot category detection)
 *   - Heuristic fallback patterns              (EN / FR / AR)
 *
 * Returns ['safe' => true] or ['safe' => false, 'reason' => '...', 'categories' => [...]]
 */
class TextModerationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
        private readonly string $pythonPath = 'python',
    ) {}

    /**
     * Check a text string for toxic / inappropriate content.
     *
     * @param  string $text  The raw user-submitted text (any language)
     * @return array{safe: bool, reason?: string, categories?: string[], confidence?: float}
     */
    public function checkText(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return ['safe' => true];
        }

        $scriptPath = $this->projectDir . '/text_moderation_service.py';

        if (!file_exists($scriptPath)) {
            $this->logger->warning('TextModerationService: script not found – skipping check', [
                'script' => $scriptPath,
            ]);
            return ['safe' => true, 'warning' => 'moderation_unavailable'];
        }

        // Write the text to a temporary file (avoids shell-escape issues with Unicode)
        $tmpFile = tempnam(sys_get_temp_dir(), 'txt_mod_');
        file_put_contents($tmpFile, $text);

        try {
            $result = $this->runPythonCheck($scriptPath, $tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        return $result;
    }

    private function runPythonCheck(string $scriptPath, string $textFilePath): array
    {
        $cmd = sprintf(
            '%s %s check %s',
            escapeshellarg($this->pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($textFilePath)
        );

        $sysEnv = getenv();
        $env = array_merge(is_array($sysEnv) ? $sysEnv : [], $_ENV ?? [], [
            'PYTHONIOENCODING'        => 'utf-8',
            'PYTHONDONTWRITEBYTECODE' => '1',
            'TRANSFORMERS_VERBOSITY'  => 'error',
            'HF_HUB_DISABLE_PROGRESS_BARS' => '1',
            'TF_CPP_MIN_LOG_LEVEL'    => '3',
            'TOKENIZERS_PARALLELISM'  => 'false',
        ]);

        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($cmd, $descriptors, $pipes, null, $env);

        if (!is_resource($process)) {
            $this->logger->error('TextModerationService: failed to start Python process');
            return ['safe' => true, 'warning' => 'moderation_unavailable'];
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timeout = 60; // seconds – first model load may take time
        $start   = time();

        while (!feof($pipes[1]) || !feof($pipes[2])) {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);

            if ((time() - $start) > $timeout) {
                $this->logger->warning('TextModerationService: timeout exceeded', [
                    'stderr' => trim($stderr),
                ]);
                proc_terminate($process);
                return ['safe' => true, 'warning' => 'moderation_timeout'];
            }

            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }

            usleep(50_000); // 50 ms poll
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (!mb_check_encoding($stdout, 'UTF-8')) {
            $stdout = mb_convert_encoding($stdout, 'UTF-8', 'Windows-1252');
        }

        $stdout = trim($stdout);

        if (empty($stdout)) {
            $this->logger->warning('TextModerationService: empty output from Python', [
                'stderr' => trim($stderr),
            ]);
            return ['safe' => true, 'warning' => 'moderation_unavailable'];
        }

        $result = json_decode($stdout, true);

        if (!is_array($result)) {
            $this->logger->warning('TextModerationService: invalid JSON from Python', [
                'stdout' => $stdout,
                'stderr' => trim($stderr),
            ]);
            return ['safe' => true, 'warning' => 'moderation_parse_error'];
        }

        $this->logger->info('TextModerationService: moderation result', [
            'safe'       => $result['safe'] ?? true,
            'categories' => $result['categories'] ?? [],
        ]);

        return $result;
    }
}
