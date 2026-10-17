<?php
declare(strict_types=1);

namespace Heartbeat\Mail;

/**
 * Resend REST API Mail Driver
 * Lightweight cURL integration with zero external libraries.
 */
final class ResendDriver implements MailDriverInterface
{
    private string $apiKey;
    private string $fromEmail;
    private string $fromName;
    private string $replyTo;
    private string $lastError = '';

    public function __construct(string $apiKey, string $fromEmail, string $fromName = 'Heartbeat CMS', string $replyTo = '')
    {
        $this->apiKey = trim($apiKey);
        $this->fromEmail = trim($fromEmail);
        $this->fromName = trim($fromName);
        $this->replyTo = trim($replyTo);
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody = '', array $headers = []): bool
    {
        $this->lastError = '';

        if ($this->apiKey === '') {
            $this->lastError = 'Resend API-Schlüssel ist nicht konfiguriert.';
            return false;
        }

        if ($this->fromEmail === '' || !filter_var($this->fromEmail, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = 'Ungültige oder fehlende Absender-E-Mail-Adresse.';
            return false;
        }

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = "Ungültige Empfänger-E-Mail-Adresse: {$to}";
            return false;
        }

        $fromHeader = $this->fromName !== '' 
            ? "{$this->fromName} <{$this->fromEmail}>" 
            : $this->fromEmail;

        $payload = [
            'from'    => $fromHeader,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $htmlBody,
        ];

        if ($textBody !== '') {
            $payload['text'] = $textBody;
        } else {
            $payload['text'] = strip_tags($htmlBody);
        }

        if ($this->replyTo !== '' && filter_var($this->replyTo, FILTER_VALIDATE_EMAIL)) {
            $payload['reply_to'] = $this->replyTo;
        }

        if (!empty($headers)) {
            $payload['headers'] = $headers;
        }

        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            $this->lastError = 'Fehler beim JSON-Kodieren der E-Mail-Nutzlast.';
            return false;
        }

        $ch = curl_init('https://api.resend.com/emails');
        if ($ch === false) {
            $this->lastError = 'cURL-Initialisierung fehlgeschlagen.';
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'User-Agent: Heartbeat-CMS/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            $this->lastError = 'cURL-Verbindungsfehler zu Resend: ' . $curlError;
            return false;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        $body = is_string($response) ? json_decode($response, true) : null;
        $errMsg = $body['message'] ?? (is_string($response) ? $response : 'Unbekannter HTTP ' . $httpCode . ' Fehler');
        $this->lastError = "Resend API Fehler (HTTP {$httpCode}): {$errMsg}";
        return false;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}

