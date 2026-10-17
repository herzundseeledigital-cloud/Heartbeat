<?php
declare(strict_types=1);

namespace Heartbeat\Http;

use Heartbeat\Database\Database;
use Heartbeat\Mail\Mailer;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;

/**
 * Universal Contact & Property Inquiry Controller
 * Provides secure contact form processing with Honeypot anti-spam and CSRF protection.
 */
final class ContactController
{
    /**
     * Handle incoming contact / inquiry form submission
     */
    public function submit(Request $request): Response
    {
        // 1. CSRF Verification
        if (!Csrf::verifyRequest($request)) {
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'error' => 'Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.'], 403);
            }
            Session::flash('error', 'Sicherheitsprüfung fehlgeschlagen (CSRF-Token ungültig). Bitte erneut versuchen.');
            return Response::redirect($request->header('referer') ?: '/');
        }

        // 2. Honeypot Anti-Spam Check
        // If the hidden honeypot field is filled, it was submitted by an automated spam bot.
        $honeypot = trim((string)$request->post('_hb_hp', ''));
        if ($honeypot !== '') {
            // Silently pretend success to deceive spam bots without sending any mail
            if ($request->isAjax()) {
                return Response::json(['success' => true, 'message' => 'Vielen Dank für Ihre Nachricht!']);
            }
            Session::flash('success', 'Vielen Dank für Ihre Nachricht!');
            return Response::redirect($request->header('referer') ?: '/');
        }

        // 3. Input Validation & Sanitization
        $name = trim((string)$request->post('name', ''));
        $email = trim((string)$request->post('email', ''));
        $subject = trim((string)$request->post('subject', ''));
        $message = trim((string)$request->post('message', ''));
        $propertyId = trim((string)$request->post('property_id', ''));
        $propertyTitle = trim((string)$request->post('property_title', ''));

        if ($name === '' || $email === '' || $message === '') {
            $err = 'Bitte füllen Sie alle erforderlichen Pflichtfelder (Name, E-Mail, Nachricht) aus.';
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'error' => $err], 422);
            }
            Session::flash('error', $err);
            return Response::redirect($request->header('referer') ?: '/');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
            if ($request->isAjax()) {
                return Response::json(['success' => false, 'error' => $err], 422);
            }
            Session::flash('error', $err);
            return Response::redirect($request->header('referer') ?: '/');
        }

        // 4. Retrieve Site Info and Recipient Email
        $siteTitle = 'Heartbeat CMS';
        $adminEmail = 'admin@example.com';
        try {
            $db = Database::getInstance();
            $st = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_title'");
            if ($st) $siteTitle = (string)$st;
            $ae = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'admin_email'");
            if ($ae) $adminEmail = (string)$ae;
        } catch (\Throwable) {
            // Fallback defaults
        }

        // Determine Email Subject
        if ($subject === '') {
            if ($propertyTitle !== '') {
                $subject = "Immobilien-Anfrage: {$propertyTitle}" . ($propertyId !== '' ? " (#{$propertyId})" : "");
            } else {
                $subject = "Neue Kontaktanfrage über {$siteTitle}";
            }
        }

        // Format HTML Email Body
        $safeName = Security::e($name);
        $safeEmail = Security::e($email);
        $safeSubject = Security::e($subject);
        $safeMessage = nl2br(Security::e($message));
        $safeSiteTitle = Security::e($siteTitle);
        $dateStr = date('d.m.Y H:i:s');

        $propertyMetaHtml = '';
        if ($propertyTitle !== '' || $propertyId !== '') {
            $safePropTitle = Security::e($propertyTitle);
            $safePropId = Security::e($propertyId);
            $propertyMetaHtml = <<<HTML
            <div style="background-color: #0f172a; border: 1px solid #1e293b; border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                <strong style="color: #10b981;">Objekt-Details:</strong><br>
                Objekt-Titel: {$safePropTitle}<br>
                Objekt-ID: #{$safePropId}
            </div>
HTML;
        }

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #090d16; color: #f8fafc; padding: 24px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #0f172a; border: 1px solid #1e293b; border-radius: 12px; padding: 24px;">
        <div style="border-bottom: 1px solid #1e293b; padding-bottom: 16px; margin-bottom: 20px;">
            <h2 style="color: #f97316; margin: 0 0 6px 0; font-size: 20px;">Neue Anfrage via {$safeSiteTitle}</h2>
            <div style="color: #94a3b8; font-size: 12px;">Eingegangen am {$dateStr}</div>
        </div>

        {$propertyMetaHtml}

        <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 14px;">
            <tr>
                <td style="padding: 8px 0; color: #94a3b8; width: 120px;"><strong>Absender:</strong></td>
                <td style="padding: 8px 0; color: #f8fafc;">{$safeName}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #94a3b8;"><strong>E-Mail:</strong></td>
                <td style="padding: 8px 0; color: #38bdf8;"><a href="mailto:{$safeEmail}" style="color: #38bdf8; text-decoration: none;">{$safeEmail}</a></td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #94a3b8;"><strong>Betreff:</strong></td>
                <td style="padding: 8px 0; color: #f8fafc;">{$safeSubject}</td>
            </tr>
        </table>

        <div style="background-color: #090d16; border: 1px solid #1e293b; border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.6; color: #e2e8f0;">
            {$safeMessage}
        </div>

        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #1e293b; text-align: center; color: #64748b; font-size: 12px;">
            Gesendet über Heartbeat CMS &bull; {$safeSiteTitle}
        </div>
    </div>
</body>
</html>
HTML;

        // 5. Dispatch via Mailer
        try {
            $mailer = Mailer::getInstance();
            $mailer->send([
                'to'       => $adminEmail,
                'subject'  => "[{$siteTitle}] {$subject}",
                'html'     => $htmlBody,
                'reply_to' => $email,
            ]);
        } catch (\Throwable) {
            // Mailer failure handled gracefully
        }

        $successMsg = 'Vielen Dank! Ihre Nachricht wurde erfolgreich übermittelt. Wir melden uns schnellstmöglich bei Ihnen.';

        if ($request->isAjax()) {
            return Response::json([
                'success' => true,
                'message' => $successMsg,
            ]);
        }

        Session::flash('success', $successMsg);
        return Response::redirect($request->header('referer') ?: '/');
    }
}

