<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

class OllamaManager
{
    private const START_LOCK_KEY = 'ollama.autostart.lock';
    private const START_LOCK_SECONDS = 20;

    public function ensureRunning(?string $baseUrl = null): void
    {
        if ($this->isRunning($baseUrl)) {
            return;
        }

        if (!Cache::add(self::START_LOCK_KEY, true, self::START_LOCK_SECONDS)) {
            return;
        }

        $this->start();
    }

    private function isRunning(?string $baseUrl = null): bool
    {
        $fallback = (string) config('services.ollama.url', config('llm.base_url', 'http://127.0.0.1:11434'));
        $base = rtrim((string) ($baseUrl ?: $fallback), '/');
        if ($base === '') {
            $base = 'http://127.0.0.1:11434';
        }

        try {
            $resp = Http::timeout(1)->get($base . '/api/tags');
            return $resp->ok();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function start(): void
    {
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $process = Process::fromShellCommandline('cmd /c start "" ollama serve');
            } else {
                $process = Process::fromShellCommandline('nohup ollama serve >/dev/null 2>&1 &');
            }
            $process->disableOutput();
            $process->run();
        } catch (\Throwable $e) {
            // Best-effort only.
        }
    }
}
