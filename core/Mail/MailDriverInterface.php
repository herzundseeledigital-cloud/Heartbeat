<?php
declare(strict_types=1);

namespace Heartbeat\Mail;

/**
 * Interface for Transactional Mail Drivers
 */
interface MailDriverInterface
{
    /**
     * Send an email
     *
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $htmlBody HTML content
     * @param string $textBody Plain text content
     * @param array<string, string> $headers Additional custom headers
     * @return bool True if successfully dispatched
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody = '', array $headers = []): bool;

    /**
     * Get the last error message from this driver
     */
    public function getLastError(): string;
}

