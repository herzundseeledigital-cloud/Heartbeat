<?php
declare(strict_types=1);

namespace Heartbeat\Payments;

use Heartbeat\Database\Database;
use Heartbeat\Security\Rbac;

/**
 * Native Stripe Integration Engine
 * Pure cURL REST API implementation with zero external Stripe SDK dependencies.
 * Handles Checkout Sessions, Webhooks, and Member Access Unlocking.
 */
final class StripeService
{
    private static ?StripeService $instance = null;
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
     * Get Stripe configuration from options table
     * @return array<string, mixed>
     */
    public function getSettings(): array
    {
        $enabled = (int)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'stripe_enabled'") === 1;
        $mode = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'stripe_mode'") ?: 'test';
        $secretKey = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'stripe_secret_key'") ?: '';
        $pubKey = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'stripe_publishable_key'") ?: '';
        $webhookSecret = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'stripe_webhook_secret'") ?: '';
        $currency = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'stripe_currency'") ?: 'eur';

        return [
            'enabled'          => $enabled,
            'mode'             => $mode,
            'secret_key'       => $secretKey,
            'publishable_key'  => $pubKey,
            'webhook_secret'   => $webhookSecret,
            'currency'         => strtolower($currency),
        ];
    }

    /**
     * Save Stripe configuration
     * @param array<string, mixed> $settings
     */
    public function saveSettings(array $settings): void
    {
        $keys = [
            'stripe_enabled'         => !empty($settings['enabled']) ? '1' : '0',
            'stripe_mode'            => ($settings['mode'] ?? 'test') === 'live' ? 'live' : 'test',
            'stripe_secret_key'      => trim((string)($settings['secret_key'] ?? '')),
            'stripe_publishable_key' => trim((string)($settings['publishable_key'] ?? '')),
            'stripe_webhook_secret'  => trim((string)($settings['webhook_secret'] ?? '')),
            'stripe_currency'        => strtolower(trim((string)($settings['currency'] ?? 'eur'))),
        ];

        foreach ($keys as $k => $v) {
            $existing = $this->db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :k LIMIT 1", ['k' => $k]);
            if ($existing) {
                $this->db->update('{prefix}options', ['option_value' => $v], 'option_name = :k', ['k' => $k]);
            } else {
                $this->db->insert('{prefix}options', ['option_name' => $k, 'option_value' => $v, 'autoload' => 1]);
            }
        }
    }

    /**
     * Create a Stripe Checkout Session
     *
     * @param array{
     *   title: string,
     *   amount_cents: int,
     *   currency?: string,
     *   customer_email?: string,
     *   user_id?: int,
     *   plan?: string,
     *   success_url?: string,
     *   cancel_url?: string
     * } $params
     * @return array{success: bool, session_id: string, checkout_url: string, error: string}
     */
    public function createCheckoutSession(array $params): array
    {
        $this->lastError = '';
        $settings = $this->getSettings();

        if (empty($settings['secret_key'])) {
            $this->lastError = 'Stripe Secret Key ist nicht hinterlegt.';
            return ['success' => false, 'session_id' => '', 'checkout_url' => '', 'error' => $this->lastError];
        }

        $siteUrl = rtrim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'"), '/');
        $currency = $params['currency'] ?? $settings['currency'] ?? 'eur';
        $amount = (int)($params['amount_cents'] ?? 1000);
        $title = $params['title'] ?? 'Mitgliedschaft & Premium-Zugang';
        $successUrl = $params['success_url'] ?? "{$siteUrl}/checkout/success?session_id={CHECKOUT_SESSION_ID}";
        $cancelUrl = $params['cancel_url'] ?? "{$siteUrl}/checkout/cancel";

        $postFields = [
            'payment_method_types[0]'                               => 'card',
            'line_items[0][price_data][currency]'                   => $currency,
            'line_items[0][price_data][unit_amount]'                 => (string)$amount,
            'line_items[0][price_data][product_data][name]'          => $title,
            'line_items[0][quantity]'                               => '1',
            'mode'                                                  => 'payment',
            'success_url'                                           => $successUrl,
            'cancel_url'                                            => $cancelUrl,
        ];

        if (!empty($params['customer_email'])) {
            $postFields['customer_email'] = $params['customer_email'];
        }

        if (!empty($params['user_id'])) {
            $postFields['metadata[user_id]'] = (string)$params['user_id'];
        }
        if (!empty($params['plan'])) {
            $postFields['metadata[plan]'] = (string)$params['plan'];
        }

        $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($postFields),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $settings['secret_key'],
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $res = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || $code !== 200 || !is_string($res)) {
            $errMsg = $err ?: $res;
            $this->lastError = "Stripe API Fehler (HTTP {$code}): {$errMsg}";
            return ['success' => false, 'session_id' => '', 'checkout_url' => '', 'error' => $this->lastError];
        }

        $data = json_decode($res, true);
        if (!is_array($data) || empty($data['id'])) {
            $this->lastError = 'Ungültige Antwort von Stripe.';
            return ['success' => false, 'session_id' => '', 'checkout_url' => '', 'error' => $this->lastError];
        }

        return [
            'success'      => true,
            'session_id'   => (string)$data['id'],
            'checkout_url' => (string)($data['url'] ?? ''),
            'error'        => '',
        ];
    }

    /**
     * Verify Stripe Webhook Signature (pure PHP implementation)
     */
    public function verifyWebhookSignature(string $payload, string $sigHeader, string $secret): bool
    {
        if ($sigHeader === '' || $secret === '') {
            return false;
        }

        $items = explode(',', $sigHeader);
        $timestamp = '';
        $signatures = [];

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if ($timestamp === '' || empty($signatures)) {
            return false;
        }

        // Validate timestamp within 5 minutes tolerance (prevent replay attacks)
        $tolerance = 300;
        if (abs(time() - (int)$timestamp) > $tolerance) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle incoming Stripe Webhook
     *
     * @return array{success: bool, event_type: string, message: string}
     */
    public function handleWebhook(string $payload, string $sigHeader): array
    {
        $settings = $this->getSettings();
        $webhookSecret = $settings['webhook_secret'];

        if ($webhookSecret !== '') {
            if (!$this->verifyWebhookSignature($payload, $sigHeader, $webhookSecret)) {
                return [
                    'success'    => false,
                    'event_type' => '',
                    'message'    => 'Ungültige Webhook-Signatur.',
                ];
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return [
                'success'    => false,
                'event_type' => '',
                'message'    => 'Ungültiges JSON-Event.',
            ];
        }

        $eventType = (string)$event['type'];

        if ($eventType === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $sessionId = (string)($session['id'] ?? '');
            $customerId = (string)($session['customer'] ?? '');
            $email = (string)($session['customer_details']['email'] ?? $session['customer_email'] ?? '');
            $amount = (int)($session['amount_total'] ?? 0);
            $currency = (string)($session['currency'] ?? 'eur');
            $metadata = (array)($session['metadata'] ?? []);
            $userId = isset($metadata['user_id']) ? (int)$metadata['user_id'] : null;
            $plan = (string)($metadata['plan'] ?? 'member_access');

            // Record transaction in orders table
            $existing = $this->db->fetchOne("SELECT id FROM {prefix}orders WHERE stripe_session_id = :sid LIMIT 1", ['sid' => $sessionId]);
            if (!$existing) {
                $this->db->insert('{prefix}orders', [
                    'user_id'            => $userId,
                    'stripe_session_id'  => $sessionId,
                    'stripe_customer_id' => $customerId,
                    'customer_email'     => $email,
                    'amount_cents'       => $amount,
                    'currency'           => $currency,
                    'plan_or_item'       => $plan,
                    'status'             => 'completed',
                    'created_at'         => date('Y-m-d H:i:s'),
                    'updated_at'         => date('Y-m-d H:i:s'),
                ]);
            }

            // Unlock member access if user identified
            if ($userId !== null && $userId > 0) {
                $this->db->update('{prefix}users', ['role' => Rbac::ROLE_MEMBER], 'id = :id', ['id' => $userId]);
            } elseif ($email !== '') {
                $userByEmail = $this->db->fetchOne("SELECT id FROM {prefix}users WHERE email = :e LIMIT 1", ['e' => $email]);
                if ($userByEmail) {
                    $this->db->update('{prefix}users', ['role' => Rbac::ROLE_MEMBER], 'id = :id', ['id' => $userByEmail['id']]);
                }
            }

            return [
                'success'    => true,
                'event_type' => $eventType,
                'message'    => "Checkout Session {$sessionId} erfolgreich abgewickelt und Mitgliedschaft freigeschaltet.",
            ];
        }

        return [
            'success'    => true,
            'event_type' => $eventType,
            'message'    => "Event {$eventType} ignoriert (keine Aktion erforderlich).",
        ];
    }

    /**
     * List recent transactions
     * @return array<int, array<string, mixed>>
     */
    public function getRecentOrders(int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM {prefix}orders ORDER BY id DESC LIMIT :lim",
            ['lim' => $limit]
        );
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }
}

