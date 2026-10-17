<?php
declare(strict_types=1);

namespace Heartbeat\Mail;

/**
 * Native Pure-PHP SMTP Mail Driver
 * Uses stream_socket_client without any external Composer packages.
 */
final class SmtpDriver implements MailDriverInterface
{
    private string $host;
    private int $port;
    private string $encryption;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private string $replyTo;
    private int $timeout;
    private string $lastError = '';

    public function __construct(
        string $host,
        int $port = 587,
        string $encryption = 'tls',
        string $username = '',
        string $password = '',
        string $fromEmail = '',
        string $fromName = 'Heartbeat CMS',
        string $replyTo = '',
        int $timeout = 10
    ) {
        $this->host = trim($host);
        $this->port = $port > 0 ? $port : 587;
        $this->encryption = strtolower(trim($encryption));
        $this->username = trim($username);
        $this->password = $password;
        $this->fromEmail = trim($fromEmail);
        $this->fromName = trim($fromName);
        $this->replyTo = trim($replyTo);
        $this->timeout = $timeout;
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody = '', array $headers = []): bool
    {
        $this->lastError = '';

        if ($this->host === '') {
            $this->lastError = 'SMTP-Host ist nicht konfiguriert.';
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

        $remoteSocket = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($remoteSocket, $errno, $errstr, (float)$this->timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            $this->lastError = "SMTP-Verbindungsfehler zu {$remoteSocket}: {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($socket, $this->timeout);

        try {
            // Read 220 greeting
            $greeting = $this->readResponse($socket);
            if (!str_starts_with($greeting, '220')) {
                throw new \RuntimeException("Unerwarteter SMTP-Greeting-Code: {$greeting}");
            }

            $clientHost = gethostname() ?: 'localhost';

            // Send EHLO
            $this->sendCommand($socket, "EHLO {$clientHost}");
            $ehloResponse = $this->readResponse($socket);

            // Handle STARTTLS
            if ($this->encryption === 'tls') {
                $this->sendCommand($socket, "STARTTLS");
                $tlsResp = $this->readResponse($socket);
                if (!str_starts_with($tlsResp, '220')) {
                    throw new \RuntimeException("STARTTLS abgelehnt: {$tlsResp}");
                }

                $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                }
                if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
                    $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
                }

                $crypto = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
                if ($crypto !== true) {
                    throw new \RuntimeException("TLS-Verschlüsselung mit SMTP-Server konnte nicht ausgehandelt werden.");
                }

                // Re-send EHLO after TLS handshake
                $this->sendCommand($socket, "EHLO {$clientHost}");
                $ehloResponse = $this->readResponse($socket);
            }

            // Authenticate if credentials provided
            if ($this->username !== '') {
                $this->sendCommand($socket, "AUTH LOGIN");
                $authResp = $this->readResponse($socket);
                if (!str_starts_with($authResp, '334')) {
                    throw new \RuntimeException("AUTH LOGIN nicht akzeptiert: {$authResp}");
                }

                $this->sendCommand($socket, base64_encode($this->username));
                $userResp = $this->readResponse($socket);
                if (!str_starts_with($userResp, '334')) {
                    throw new \RuntimeException("SMTP-Benutzername abgelehnt: {$userResp}");
                }

                $this->sendCommand($socket, base64_encode($this->password));
                $passResp = $this->readResponse($socket);
                if (!str_starts_with($passResp, '235')) {
                    throw new \RuntimeException("SMTP-Authentifizierung fehlgeschlagen: {$passResp}");
                }
            }

            // MAIL FROM
            $this->sendCommand($socket, "MAIL FROM: <{$this->fromEmail}>");
            $mailFromResp = $this->readResponse($socket);
            if (!str_starts_with($mailFromResp, '250')) {
                throw new \RuntimeException("MAIL FROM abgelehnt: {$mailFromResp}");
            }

            // RCPT TO
            $this->sendCommand($socket, "RCPT TO: <{$to}>");
            $rcptResp = $this->readResponse($socket);
            if (!str_starts_with($rcptResp, '250') && !str_starts_with($rcptResp, '251')) {
                throw new \RuntimeException("Empfänger abgelehnt: {$rcptResp}");
            }

            // DATA
            $this->sendCommand($socket, "DATA");
            $dataResp = $this->readResponse($socket);
            if (!str_starts_with($dataResp, '354')) {
                throw new \RuntimeException("DATA-Befehl abgelehnt: {$dataResp}");
            }

            // Build MIME message
            $message = $this->buildMimeMessage($to, $subject, $htmlBody, $textBody, $headers);
            $this->sendCommand($socket, $message . "\r\n.");

            $sendResp = $this->readResponse($socket);
            if (!str_starts_with($sendResp, '250')) {
                throw new \RuntimeException("E-Mail-Zustellung fehlgeschlagen: {$sendResp}");
            }

            $this->sendCommand($socket, "QUIT");
            $this->readResponse($socket);

            fclose($socket);
            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            if (is_resource($socket)) {
                @fclose($socket);
            }
            return false;
        }
    }

    private function buildMimeMessage(string $to, string $subject, string $htmlBody, string $textBody, array $customHeaders): string
    {
        $boundary = '=_hb_' . md5(uniqid((string)mt_rand(), true));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromFormatted = $this->fromName !== '' 
            ? '=?UTF-8?B?' . base64_encode($this->fromName) . "?= <{$this->fromEmail}>" 
            : $this->fromEmail;

        $msgHeaders = [
            "Date: " . date('r'),
            "From: {$fromFormatted}",
            "To: <{$to}>",
            "Subject: {$encodedSubject}",
            "Message-ID: <" . md5(uniqid((string)time(), true)) . "@{$this->host}>",
            "MIME-Version: 1.0",
            "X-Mailer: Heartbeat CMS Transactional Mailer",
        ];

        if ($this->replyTo !== '' && filter_var($this->replyTo, FILTER_VALIDATE_EMAIL)) {
            $msgHeaders[] = "Reply-To: <{$this->replyTo}>";
        }

        foreach ($customHeaders as $k => $v) {
            $msgHeaders[] = "{$k}: {$v}";
        }

        if ($textBody === '') {
            $textBody = strip_tags($htmlBody);
        }

        $msgHeaders[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        $body = implode("\r\n", $msgHeaders) . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($textBody)) . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $body .= "--{$boundary}--";

        return $body;
    }

    private function sendCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private function readResponse($socket): string
    {
        $response = '';
        while (!feof($socket)) {
            $line = fgets($socket, 512);
            if ($line === false) {
                break;
            }
            $response .= $line;
            // Multiline responses have a hyphen after the code: e.g. "250-SIZE" vs "250 OK"
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return trim($response);
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}

