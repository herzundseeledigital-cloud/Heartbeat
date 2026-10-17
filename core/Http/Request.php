<?php
declare(strict_types=1);

namespace Heartbeat\Http;

/**
 * HTTP Request encapsulation
 */
final class Request
{
    private string $method;
    private string $path;
    private string $uri;
    private array $queryParams;
    private array $postParams;
    private ?array $jsonParams = null;
    private array $serverParams;
    private array $headers;

    public function __construct(
        ?string $method = null,
        ?string $uri = null,
        ?array $query = null,
        ?array $post = null,
        ?array $server = null
    ) {
        $this->serverParams = $server ?? $_SERVER;
        $this->method = strtoupper($method ?? $this->serverParams['REQUEST_METHOD'] ?? 'GET');
        $rawUri = $uri ?? $this->serverParams['REQUEST_URI'] ?? '/';
        $this->uri = $rawUri;
        
        $parsedPath = parse_url($rawUri, PHP_URL_PATH) ?? '/';
        $this->path = '/' . trim($parsedPath, '/');
        if ($this->path === '') {
            $this->path = '/';
        }

        $this->queryParams = $query ?? $_GET;
        $this->postParams = $post ?? $_POST;
        $this->headers = $this->extractHeaders();
    }

    public static function capture(): self
    {
        return new self();
    }

    /**
     * Create a synthetic Request instance for testing or sub-requests
     * @param array<string, mixed> $params
     */
    public static function create(string $method = 'GET', string $uri = '/', array $params = []): self
    {
        $isPost = strtoupper($method) === 'POST';
        $query = $isPost ? [] : $params;
        $post = $isPost ? $params : [];
        return new self($method, $uri, $query, $post);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }
        return $this->queryParams[$key] ?? $default;
    }

    /**
     * Alias for query parameter lookup
     */
    public function get(?string $key = null, mixed $default = null): mixed
    {
        return $this->query($key, $default);
    }

    public function post(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->postParams;
        }
        return $this->postParams[$key] ?? $default;
    }

    /**
     * Retrieve parsed JSON body
     */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        if ($this->jsonParams === null) {
            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $decoded = json_decode($raw, true);
                $this->jsonParams = is_array($decoded) ? $decoded : [];
            } else {
                $this->jsonParams = [];
            }
        }

        if ($key === null) {
            return $this->jsonParams;
        }

        return $this->jsonParams[$key] ?? $default;
    }

    /**
     * Retrieve input value checking JSON body, then POST, then GET
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        $all = array_merge($this->queryParams, $this->postParams, $this->json() ?? []);
        if ($key === null) {
            return $all;
        }
        return $all[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        $normalizedKey = strtolower($key);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $normalizedKey) {
                return $v;
            }
        }
        return $default;
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest'
            || str_contains($this->header('Accept', ''), 'application/json');
    }

    public function isSecure(): bool
    {
        return (!empty($this->serverParams['HTTPS']) && $this->serverParams['HTTPS'] !== 'off')
            || (isset($this->serverParams['SERVER_PORT']) && $this->serverParams['SERVER_PORT'] == 443)
            || (isset($this->serverParams['HTTP_X_FORWARDED_PROTO']) && $this->serverParams['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    public function ip(): string
    {
        if (!empty($this->serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->serverParams['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $this->serverParams['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getHeader(string $key, ?string $default = null): ?string
    {
        return $this->header($key, $default);
    }

    public function getIp(): string
    {
        return $this->ip();
    }

    public function getHost(): string
    {
        return $this->serverParams['HTTP_HOST'] ?? 'localhost';
    }

    public function getRawBody(): string
    {
        return (string)file_get_contents('php://input');
    }

    public function baseUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->serverParams['HTTP_HOST'] ?? 'localhost:8000';
        return $scheme . '://' . $host;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl();
    }

    private function extractHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                return $headers;
            }
        }

        $headers = [];
        foreach ($this->serverParams as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = (string)$value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = str_replace('_', '-', $key);
                $headers[$headerName] = (string)$value;
            }
        }

        return $headers;
    }
}

