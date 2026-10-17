<?php
declare(strict_types=1);

namespace Heartbeat\Mail;

/**
 * Log Mail Driver for Local Development & Testing
 * Writes dispatched emails to storage/logs/mail.log.
 */
final class LogDriver implements MailDriverInterface
{
    private string $logFile;
    private string $lastError = '';

    public function __construct(string $logFile = '')
    {
        $this->logFile = $logFile !== '' ? $logFile : HEARTBEAT_STORAGE . '/logs/mail.log';
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody = '', array $headers = []): bool
    {
        $this->lastError = '';

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $entry = sprintf(
            "[%s] TO: %s | SUBJECT: %s\nHEADERS: %s\nBODY (HTML):\n%s\nBODY (TEXT):\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $to,
            $subject,
            json_encode($headers),
            $htmlBody,
            $textBody !== '' ? $textBody : strip_tags($htmlBody),
            str_repeat('-', 60)
        );

        $result = @file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            $this->lastError = "Konnte E-Mail nicht in {$this->logFile} schreiben.";
            return false;
        }

        return true;
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}

