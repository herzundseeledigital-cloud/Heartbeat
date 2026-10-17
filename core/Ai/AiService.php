<?php
declare(strict_types=1);

namespace Heartbeat\Ai;

use Heartbeat\Database\Database;

/**
 * Universal AI Assistant Service
 * Integrates Google Gemini, OpenAI, and Anthropic Claude via lightweight native cURL.
 */
final class AiService
{
    private static ?AiService $instance = null;
    private Database $db;
    private string $lastError = '';

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retrieve AI configuration from options
     * @return array<string, string>
     */
    public function getSettings(): array
    {
        $provider = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'ai_provider'") ?: 'gemini';
        $apiKey = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'ai_api_key'") ?: '';
        $model = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'ai_model'") ?: '';

        if ($model === '') {
            $model = match ($provider) {
                'openai'    => 'gpt-4o-mini',
                'anthropic' => 'claude-3-haiku-20240307',
                default     => 'gemini-1.5-flash',
            };
        }

        return [
            'provider' => $provider,
            'api_key'  => $apiKey,
            'model'    => $model,
        ];
    }

    /**
     * Save AI configuration
     * @param array<string, string> $settings
     */
    public function saveSettings(array $settings): void
    {
        $keys = ['ai_provider' => 'provider', 'ai_api_key' => 'api_key', 'ai_model' => 'model'];
        foreach ($keys as $optKey => $inputKey) {
            if (isset($settings[$inputKey])) {
                $val = trim((string)$settings[$inputKey]);
                $existing = $this->db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :k LIMIT 1", ['k' => $optKey]);
                if ($existing) {
                    $this->db->update('{prefix}options', ['option_value' => $val], 'option_name = :k', ['k' => $optKey]);
                } else {
                    $this->db->insert('{prefix}options', ['option_name' => $optKey, 'option_value' => $val, 'autoload' => 1]);
                }
            }
        }
    }

    /**
     * Generate SEO Title & Meta Description from page title and content
     * @return array{title: string, description: string, keywords: string}
     */
    public function generateSeoMeta(string $contentTitle, string $contentText): array
    {
        $cleanContent = mb_substr(trim(strip_tags($contentText)), 0, 2000);

        $prompt = <<<PROMPT
Du bist ein professioneller SEO-Experte für Webseiten.
Erstelle für den folgenden Beitrag einen optimierten SEO-Meta-Titel (max. 60 Zeichen), eine ansprechende Meta-Description (max. 155 Zeichen) und 4-6 relevante Keywords (kommagetrennt).

Beitragstitel: {$contentTitle}
Inhalt:
{$cleanContent}

Antworte AUSSCHLIESSLICH im folgenden JSON-Format ohne Markdown-Code-Blöcke:
{"title": "...", "description": "...", "keywords": "..."}
PROMPT;

        $rawResponse = $this->queryModel($prompt);
        if ($rawResponse === '') {
            return [
                'title'       => $contentTitle,
                'description' => mb_substr($cleanContent, 0, 155),
                'keywords'    => '',
            ];
        }

        // Clean potential markdown fences ```json ... ```
        $jsonStr = trim($rawResponse);
        if (str_starts_with($jsonStr, '```')) {
            $jsonStr = preg_replace('/^```(?:json)?\s*/i', '', $jsonStr) ?? $jsonStr;
            $jsonStr = preg_replace('/\s*```$/', '', $jsonStr) ?? $jsonStr;
        }

        $decoded = json_decode(trim($jsonStr), true);
        if (is_array($decoded) && !empty($decoded['title'])) {
            return [
                'title'       => (string)($decoded['title'] ?? $contentTitle),
                'description' => (string)($decoded['description'] ?? ''),
                'keywords'    => (string)($decoded['keywords'] ?? ''),
            ];
        }

        return [
            'title'       => $contentTitle,
            'description' => mb_substr($rawResponse, 0, 155),
            'keywords'    => '',
        ];
    }

    /**
     * Generate copy / marketing text based on a user prompt and optional context
     */
    public function generateCopy(string $userPrompt, string $context = ''): string
    {
        $systemInstructions = "Du bist ein erfahrener Web-Texter für moderne Corporate- und Startup-Websites. Schreibe klare, überzeugende und moderne deutsche Texte.";
        $fullPrompt = "{$systemInstructions}\n\n";
        if ($context !== '') {
            $fullPrompt .= "Kontext: {$context}\n\n";
        }
        $fullPrompt .= "Aufgabe: {$userPrompt}";

        return $this->queryModel($fullPrompt);
    }

    /**
     * Send a raw prompt to the configured LLM provider
     */
    public function queryModel(string $prompt): string
    {
        $this->lastError = '';
        $settings = $this->getSettings();
        $provider = $settings['provider'];
        $apiKey = $settings['api_key'];
        $model = $settings['model'];

        if ($apiKey === '') {
            $this->lastError = 'Kein AI API-Schlüssel konfiguriert.';
            return '';
        }

        return match ($provider) {
            'openai'    => $this->callOpenAi($apiKey, $model, $prompt),
            'anthropic' => $this->callAnthropic($apiKey, $model, $prompt),
            default     => $this->callGemini($apiKey, $model, $prompt),
        };
    }

    private function callGemini(string $apiKey, string $model, string $prompt): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $payload = json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'temperature'     => 0.4,
                'maxOutputTokens' => 1024,
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);

        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || $code !== 200 || !is_string($res)) {
            $this->lastError = "Gemini API Fehler (HTTP {$code}): " . ($err ?: $res);
            return '';
        }

        $data = json_decode($res, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        return trim((string)$text);
    }

    private function callOpenAi(string $apiKey, string $model, string $prompt): string
    {
        $url = 'https://api.openai.com/v1/chat/completions';

        $payload = json_encode([
            'model'       => $model,
            'messages'    => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.4,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || $code !== 200 || !is_string($res)) {
            $this->lastError = "OpenAI API Fehler (HTTP {$code}): " . ($err ?: $res);
            return '';
        }

        $data = json_decode($res, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        return trim((string)$text);
    }

    private function callAnthropic(string $apiKey, string $model, string $prompt): string
    {
        $url = 'https://api.anthropic.com/v1/messages';

        $payload = json_encode([
            'model'      => $model,
            'max_tokens' => 1024,
            'messages'   => [['role' => 'user', 'content' => $prompt]],
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);

        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || $code !== 200 || !is_string($res)) {
            $this->lastError = "Anthropic API Fehler (HTTP {$code}): " . ($err ?: $res);
            return '';
        }

        $data = json_decode($res, true);
        $text = $data['content'][0]['text'] ?? '';
        return trim((string)$text);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}

