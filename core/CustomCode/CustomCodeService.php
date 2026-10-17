<?php
declare(strict_types=1);

namespace Heartbeat\CustomCode;

use Heartbeat\Database\Database;
use Heartbeat\Http\Request;
use Heartbeat\Cache\Cache;

/**
 * Custom Code Injection & Safe-Mode Manager
 * Allows injecting snippets into <head>, opening <body>, and closing </body> with a disaster recovery bypass.
 */
final class CustomCodeService
{
    private static ?self $instance = null;
    private ?Database $db = null;
    /** @var array<string, string> */
    private array $memoryOptions = [];

    public function __construct(?Database $db = null)
    {
        try {
            $this->db = $db ?? Database::getInstance();
        } catch (\Throwable) {
            $this->db = null;
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getHeadCode(): string
    {
        return $this->getOption('custom_code_head', '');
    }

    public function getBodyStartCode(): string
    {
        return $this->getOption('custom_code_body_start', '');
    }

    public function getBodyEndCode(): string
    {
        return $this->getOption('custom_code_body_end', '');
    }

    public function isEnabled(): bool
    {
        return $this->getOption('custom_code_enabled', '1') === '1';
    }

    public function getSafeModeToken(): string
    {
        $token = $this->getOption('custom_code_safe_token', '');
        if ($token === '') {
            $token = bin2hex(random_bytes(16));
            $this->setOption('custom_code_safe_token', $token);
        }
        return $token;
    }

    /**
     * Check if Safe Mode is triggered for the current request
     */
    public function isSafeMode(Request $request): bool
    {
        // 1. Check generic safe mode flags
        if ($request->get('hb_safe_mode') === '1' || $request->get('safe_mode') === '1') {
            return true;
        }

        // 2. Check token match
        $tokenParam = (string)$request->get('safe_mode', '');
        if ($tokenParam !== '' && hash_equals($this->getSafeModeToken(), $tokenParam)) {
            return true;
        }

        return false;
    }

    /**
     * Save custom code configuration
     * @param array<string, mixed> $data
     */
    public function save(array $data): bool
    {
        try {
            if (isset($data['head_code'])) {
                $this->setOption('custom_code_head', (string)$data['head_code']);
            }
            if (isset($data['body_start_code'])) {
                $this->setOption('custom_code_body_start', (string)$data['body_start_code']);
            }
            if (isset($data['body_end_code'])) {
                $this->setOption('custom_code_body_end', (string)$data['body_end_code']);
            }
            if (isset($data['enabled'])) {
                $this->setOption('custom_code_enabled', $data['enabled'] ? '1' : '0');
            }

            // Invalidate Page Cache
            if (class_exists(Cache::class)) {
                Cache::page()->flush();
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Inject custom code into rendered HTML
     */
    public function inject(string $html, Request $request): string
    {
        // If disabled or in Safe Mode, skip all injections
        if (!$this->isEnabled() || $this->isSafeMode($request)) {
            return $html;
        }

        $headCode = trim($this->getHeadCode());
        $bodyStartCode = trim($this->getBodyStartCode());
        $bodyEndCode = trim($this->getBodyEndCode());

        // 1. Inject Head Code (before </head>)
        if ($headCode !== '') {
            $comment = "<!-- [Heartbeat Custom Code: Head] -->\n";
            if (stripos($html, '</head>') !== false) {
                $html = (string)preg_replace('/(<\/head>)/i', $comment . $headCode . "\n$1", $html, 1);
            }
        }

        // 2. Inject Body Start Code (after <body...>)
        if ($bodyStartCode !== '') {
            $comment = "\n<!-- [Heartbeat Custom Code: Body Start] -->\n";
            if (preg_match('/<body[^>]*>/i', $html, $m)) {
                $bodyTag = $m[0];
                $html = str_replace($bodyTag, $bodyTag . $comment . $bodyStartCode, $html);
            }
        }

        // 3. Inject Body End Code (before </body>)
        if ($bodyEndCode !== '') {
            $comment = "<!-- [Heartbeat Custom Code: Body End] -->\n";
            if (stripos($html, '</body>') !== false) {
                $html = (string)preg_replace('/(<\/body>)/i', $comment . $bodyEndCode . "\n$1", $html, 1);
            }
        }

        return $html;
    }

    private function getOption(string $key, string $default = ''): string
    {
        if ($this->db === null) {
            return $this->memoryOptions[$key] ?? $default;
        }

        try {
            $val = $this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = :k", ['k' => $key]);
            return $val !== false && $val !== null ? (string)$val : $default;
        } catch (\Throwable) {
            return $this->memoryOptions[$key] ?? $default;
        }
    }

    private function setOption(string $key, string $value): void
    {
        $this->memoryOptions[$key] = $value;

        if ($this->db === null) {
            return;
        }

        try {
            $existing = $this->db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :k", ['k' => $key]);
            if ($existing) {
                $this->db->update('{prefix}options', ['option_value' => $value], 'option_name = :k', ['k' => $key]);
            } else {
                $this->db->insert('{prefix}options', [
                    'option_name'  => $key,
                    'option_value' => $value,
                    'autoload'     => 1,
                ]);
            }
        } catch (\Throwable) {
            // Keep memoryOptions updated
        }
    }
}
