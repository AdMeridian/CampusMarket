<?php
/**
 * includes/mailer.php
 *
 * Thin wrapper around the Resend HTTP API. No Composer required —
 * uses PHP's built-in curl. Reads credentials from config/secrets.php
 * (which is gitignored; each teammate keeps their own).
 *
 * Public functions:
 *   sendEmail($to, $subject, $html)
 *   sendVerificationEmail($to, $username, $verifyUrl)
 */

$secretsFile = __DIR__ . '/../config/secrets.php';
if (file_exists($secretsFile)) {
    require_once $secretsFile;
}

if (!function_exists('sendEmail')) {
    /**
     * Low-level send. Returns ['ok' => bool, ...].
     * Errors are logged via error_log() so they show up in the XAMPP Apache log.
     */
    function sendEmail(string $to, string $subject, string $html): array {
        $resendApiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : getenv('RESEND_API_KEY');
        if (empty($resendApiKey)) {
            error_log('[mailer] RESEND_API_KEY not set in config/secrets.php or environment');
            return ['ok' => false, 'error' => 'Mail service not configured.'];
        }

        $resendFromName = defined('RESEND_FROM_NAME') ? RESEND_FROM_NAME : (getenv('RESEND_FROM_NAME') ?: '');
        $resendFromEmail = defined('RESEND_FROM_EMAIL') ? RESEND_FROM_EMAIL : (getenv('RESEND_FROM_EMAIL') ?: 'onboarding@resend.dev');

        if ($resendFromName) {
            $from = $resendFromName . ' <' . $resendFromEmail . '>';
        } else {
            $from = $resendFromEmail;
        }

        $payload = [
            'from'    => $from,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $html,
        ];

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $resendApiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 10,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'response' => json_decode($body, true)];
        }

        error_log("[mailer] Resend failed status={$status} curlErr={$err} body={$body}");
        return ['ok' => false, 'status' => $status, 'error' => $err ?: $body];
    }
}

if (!function_exists('sendVerificationEmail')) {
    /**
     * Build and send the "verify your account" email.
     * $verifyUrl should be the absolute http(s) link with the token.
     */
    function sendVerificationEmail(string $to, string $username, string $verifyUrl): array {
        $appName  = defined('APP_NAME') ? APP_NAME : 'CampusMarket';
        $safeName = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeUrl  = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family: -apple-system, Segoe UI, Roboto, sans-serif; background:#f8fafc; padding:24px; color:#0f172a; margin:0;">
  <div style="max-width:480px; margin:0 auto; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:32px;">
    <h2 style="margin:0 0 12px 0; font-size:20px;">Verify your {$appName} account</h2>
    <p style="margin:0 0 20px 0; color:#475569; line-height:1.5;">
      Hi {$safeName}, click the button below to confirm your email and activate your account.
    </p>
    <p style="text-align:center; margin:28px 0;">
      <a href="{$safeUrl}"
         style="display:inline-block; background:#0f172a; color:#fff; padding:12px 24px;
                text-decoration:none; border-radius:8px; font-weight:500;">
        Verify my email
      </a>
    </p>
    <p style="margin:20px 0 0 0; color:#64748b; font-size:13px; line-height:1.5;">
      Or paste this link into your browser:<br>
      <span style="word-break:break-all;">{$safeUrl}</span>
    </p>
    <p style="margin:24px 0 0 0; color:#94a3b8; font-size:12px; line-height:1.5;">
      This link expires in 24 hours. If you didn't create an account, you can safely ignore this email.
    </p>
  </div>
</body>
</html>
HTML;

        return sendEmail($to, "Verify your {$appName} account", $html);
    }
}

if (!function_exists('sendMarketplaceAlertEmail')) {
    /**
     * Send a compact marketplace activity email (message/order/system).
     */
    function sendMarketplaceAlertEmail(string $to, string $username, string $subject, string $headline, string $body, string $ctaUrl, string $ctaText = 'Open CampusMarket'): array {
        $appName = defined('APP_NAME') ? APP_NAME : 'CampusMarket';
        $safeName = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
        $safeBody = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $safeCta = htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#f8fafc;padding:24px;color:#0f172a;margin:0;">
  <div style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:28px;">
    <h2 style="margin:0 0 10px 0;font-size:20px;">{$safeHeadline}</h2>
    <p style="margin:0 0 14px 0;color:#475569;line-height:1.5;">Hi {$safeName}, {$safeBody}</p>
    <p style="text-align:center;margin:22px 0;">
      <a href="{$safeUrl}" style="display:inline-block;background:#0f172a;color:#fff;padding:12px 22px;text-decoration:none;border-radius:8px;font-weight:600;">{$safeCta}</a>
    </p>
    <p style="margin:12px 0 0 0;color:#94a3b8;font-size:12px;">You are receiving this because activity happened on your {$appName} account.</p>
  </div>
</body>
</html>
HTML;

        return sendEmail($to, $subject, $html);
    }
}
