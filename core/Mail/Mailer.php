<?php
declare(strict_types=1);

namespace Heartbeat\Mail;

use Heartbeat\Database\Database;

/**
 * Central Transactional Mail Engine
 * Supports Resend REST API (primary) and Native Pure-PHP SMTP (fallback).
 */
final class Mailer
{
    private static ?Mailer $instance = null;
    private Database $db;
    private ?MailDriverInterface $driver = null;
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
     * Retrieve all mail settings from options table
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $rows = $this->db->fetchAll("SELECT option_name, option_value FROM {prefix}options WHERE option_name LIKE 'mail_%' OR option_name LIKE 'resend_%' OR option_name LIKE 'smtp_%'");
        $settings = [
            'mail_driver'     => 'resend',
            'mail_from_name'  => 'Heartbeat CMS',
            'mail_from_email' => 'info@example.com',
            'mail_reply_to'   => '',
            'resend_api_key'  => '',
            'smtp_host'       => '',
            'smtp_port'       => 587,
            'smtp_encryption' => 'tls',
            'smtp_user'       => '',
            'smtp_pass'       => '',
        ];

        foreach ($rows as $r) {
            $key = (string)$r['option_name'];
            $val = (string)($r['option_value'] ?? '');
            if ($key === 'smtp_port') {
                $settings[$key] = (int)$val ?: 587;
            } else {
                $settings[$key] = $val;
            }
        }

        return $settings;
    }

    /**
     * Save mail configuration options
     * @param array<string, mixed> $settings
     */
    public function saveSettings(array $settings): void
    {
        $allowed = [
            'mail_driver', 'mail_from_name', 'mail_from_email', 'mail_reply_to',
            'resend_api_key', 'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass'
        ];

        foreach ($allowed as $key) {
            if (isset($settings[$key])) {
                $val = (string)$settings[$key];
                $existing = $this->db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :k LIMIT 1", ['k' => $key]);
                if ($existing) {
                    $this->db->update('{prefix}options', ['option_value' => $val], 'option_name = :k', ['k' => $key]);
                } else {
                    $this->db->insert('{prefix}options', [
                        'option_name'  => $key,
                        'option_value' => $val,
                        'autoload'     => 1,
                    ]);
                }
            }
        }

        $this->driver = null; // Reset driver so next call uses updated settings
    }

    /**
     * Resolve the active mail driver
     */
    public function getDriver(): MailDriverInterface
    {
        if ($this->driver !== null) {
            return $this->driver;
        }

        $settings = $this->getSettings();
        $driverName = (string)($settings['mail_driver'] ?? 'resend');

        $this->driver = $this->createDriver($driverName, $settings);
        return $this->driver;
    }

    /**
     * Factory for mail drivers
     */
    public function createDriver(string $driverName, array $settings): MailDriverInterface
    {
        $fromEmail = (string)($settings['mail_from_email'] ?? 'info@example.com');
        $fromName = (string)($settings['mail_from_name'] ?? 'Heartbeat CMS');
        $replyTo = (string)($settings['mail_reply_to'] ?? '');

        return match ($driverName) {
            'smtp' => new SmtpDriver(
                (string)($settings['smtp_host'] ?? ''),
                (int)($settings['smtp_port'] ?? 587),
                (string)($settings['smtp_encryption'] ?? 'tls'),
                (string)($settings['smtp_user'] ?? ''),
                (string)($settings['smtp_pass'] ?? ''),
                $fromEmail,
                $fromName,
                $replyTo
            ),
            'log' => new LogDriver(),
            default => new ResendDriver(
                (string)($settings['resend_api_key'] ?? ''),
                $fromEmail,
                $fromName,
                $replyTo
            ),
        };
    }

    /**
     * Send an email using the active configured driver
     */
    public function send(string $to, string $subject, string $htmlBody, string $textBody = '', array $headers = []): bool
    {
        $driver = $this->getDriver();
        $success = $driver->send($to, $subject, $htmlBody, $textBody, $headers);
        $this->lastError = $driver->getLastError();
        return $success;
    }

    /**
     * Send a diagnostic test email and return detailed result
     * @return array{success: bool, message: string, driver: string}
     */
    public function sendTest(string $to): array
    {
        $settings = $this->getSettings();
        $driverName = (string)($settings['mail_driver'] ?? 'resend');
        $siteTitle = (string)($this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_title'") ?: 'Heartbeat CMS');

        $subject = "🧪 Test-Nachricht von {$siteTitle}";
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #090d16; color: #f8fafc; padding: 40px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 16px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        <div style="display: flex; align-items: center; margin-bottom: 24px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: linear-gradient(135deg, #fb326f, #e01e5a); color: #fff; font-weight: 900; line-height: 40px; text-align: center; margin-right: 12px;">HB</div>
            <h2 style="margin: 0; color: #ffffff; font-size: 20px;">Heartbeat CMS Mail-Test</h2>
        </div>
        <p style="color: #94a3b8; font-size: 14px; line-height: 1.6;">
            Glückwunsch! Ihr E-Mail-Versand ist erfolgreich eingerichtet und funktionsfähig.
        </p>
        <div style="background-color: #090d16; border: 1px solid #1e293b; border-radius: 12px; padding: 16px; margin: 24px 0; font-size: 12px; color: #cbd5e1;">
            <div><strong>Verwendeter Treiber:</strong> {$driverName}</div>
            <div><strong>Absender:</strong> {$settings['mail_from_name']} &lt;{$settings['mail_from_email']}&gt;</div>
            <div><strong>Empfänger:</strong> {$to}</div>
            <div><strong>Zeitstempel:</strong> Sat, 17 Oct 2026 20:41:00 +0200</div>
        </div>
        <p style="color: #64748b; font-size: 11px; margin-top: 24px; border-top: 1px solid #1e293b; padding-top: 16px;">
            Diese E-Mail wurde automatisch vom Heartbeat CMS Transactional Mail Engine generiert.
        </p>
    </div>
</body>
</html>
HTML;

        $text = "Heartbeat CMS Mail-Test\n\nGlückwunsch! Ihr E-Mail-Versand ({$driverName}) funktioniert einwandfrei.\nZeitstempel: " . date('Y-m-d H:i:s');

        $success = $this->send($to, $subject, $html, $text);

        if ($success) {
            return [
                'success' => true,
                'message' => "Test-E-Mail wurde erfolgreich via '{$driverName}' an {$to} versendet.",
                'driver'  => $driverName,
            ];
        }

        return [
            'success' => false,
            'message' => "Versand via '{$driverName}' fehlgeschlagen: " . ($this->lastError ?: 'Unbekannter Fehler'),
            'driver'  => $driverName,
        ];
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}

