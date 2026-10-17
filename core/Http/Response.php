<?php
declare(strict_types=1);

namespace Heartbeat\Http;

/**
 * HTTP Response encapsulation
 */
final class Response
{
    private int $statusCode;
    private array $headers = [];
    private string $body;

    public function __construct(string $body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        $defaultHeaders = ['Content-Type' => 'text/html; charset=utf-8'];
        return new self($html, $status, array_merge($defaultHeaders, $headers));
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $defaultHeaders = ['Content-Type' => 'application/json; charset=utf-8'];
        return new self($json !== false ? $json : '{}', $status, array_merge($defaultHeaders, $headers));
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    public function status(int $status): self
    {
        $this->statusCode = $status;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getContent(): string
    {
        return $this->body;
    }

    public function getHeader(string $key): ?string
    {
        $lookup = strtolower($key);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $lookup) {
                return (string)$v;
            }
        }
        return null;
    }

    public function withHeader(string $key, string $value): self
    {
        return $this->header($key, $value);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        echo $this->body;
    }
}

