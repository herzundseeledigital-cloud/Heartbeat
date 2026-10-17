<?php
declare(strict_types=1);

namespace Heartbeat\Security;

/**
 * File-based Rate Limiter to prevent brute-force attacks on auth endpoints.
 */
final class RateLimiter
{
    private string $storageDir;

    public function __construct(?string $storageDir = null)
    {
        $this->storageDir = $storageDir ?? (defined('HEARTBEAT_STORAGE') ? HEARTBEAT_STORAGE . '/cache/rate_limits' : __DIR__ . '/../../storage/cache/rate_limits');

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * Check if key has exceeded maximum allowed attempts
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        $data = $this->get($key);
        if ($data === null) {
            return false;
        }

        if (time() > $data['expires_at']) {
            $this->clear($key);
            return false;
        }

        return $data['attempts'] >= $maxAttempts;
    }

    /**
     * Record an attempt for key
     *
     * @return int Total attempts so far
     */
    public function hit(string $key, int $decaySeconds = 900): int
    {
        $data = $this->get($key);
        $now = time();

        if ($data === null || $now > $data['expires_at']) {
            $data = [
                'attempts'   => 1,
                'expires_at' => $now + $decaySeconds,
                'created_at' => $now,
            ];
        } else {
            $data['attempts']++;
        }

        $this->save($key, $data);
        return $data['attempts'];
    }

    /**
     * Retrieve number of attempts made
     */
    public function attempts(string $key): int
    {
        $data = $this->get($key);
        if ($data === null || time() > $data['expires_at']) {
            return 0;
        }

        return $data['attempts'];
    }

    /**
     * Remaining attempts before hitting max
     */
    public function remaining(string $key, int $maxAttempts): int
    {
        $attempts = $this->attempts($key);
        return max(0, $maxAttempts - $attempts);
    }

    /**
     * Seconds until the rate limit resets
     */
    public function availableIn(string $key): int
    {
        $data = $this->get($key);
        if ($data === null) {
            return 0;
        }

        $remaining = $data['expires_at'] - time();
        return max(0, $remaining);
    }

    /**
     * Clear rate limit for key
     */
    public function clear(string $key): void
    {
        $file = $this->filePath($key);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /**
     * Clean up expired rate limit files
     */
    public function prune(): void
    {
        if (!is_dir($this->storageDir)) {
            return;
        }

        $now = time();
        $files = glob($this->storageDir . '/*.json');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data) && isset($data['expires_at']) && $now > $data['expires_at']) {
                    @unlink($file);
                }
            }
        }
    }

    private function get(string $key): ?array
    {
        $file = $this->filePath($key);
        if (!is_file($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    private function save(string $key, array $data): void
    {
        $file = $this->filePath($key);
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    private function filePath(string $key): string
    {
        $safeHash = hash('sha256', $key);
        return $this->storageDir . '/' . $safeHash . '.json';
    }
}

