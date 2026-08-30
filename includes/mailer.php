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
    <p style="margin:20px 0 0 0; color:#94a3b8; font-size:12px; line-height:1.5; text-align:center;">
      CampusMarket · <a href="https://campusmarketplace.site" style="color:#94a3b8;">campusmarketplace.site</a>
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
    <p style="margin:12px 0 0 0;color:#94a3b8;font-size:12px;text-align:center;">{$appName} · <a href="https://campusmarketplace.site" style="color:#94a3b8;">campusmarketplace.site</a></p>
  </div>
</body>
</html>
HTML;

        return sendEmail($to, $subject, $html);
    }
}

if (!function_exists('generateUnsubscribeUrl')) {
    /**
     * Generate a tamper-proof unsubscribe link for a given email address.
     */
    function generateUnsubscribeUrl(string $email): string {
        $secret = function_exists('sessionStatelessSecret') ? sessionStatelessSecret() : 'campusmarket_secret_salt';
        $token = hash_hmac('sha256', strtolower(trim($email)), $secret);
        $base = defined('BASE_URL') ? BASE_URL : 'https://campusmarketplace.site/';
        return rtrim($base, '/') . '/pages/unsubscribe.php?email=' . urlencode($email) . '&token=' . $token;
    }
}

if (!function_exists('verifyUnsubscribeToken')) {
    /**
     * Verify the HMAC token on the unsubscribe page.
     */
    function verifyUnsubscribeToken(string $email, string $token): bool {
        $secret = function_exists('sessionStatelessSecret') ? sessionStatelessSecret() : 'campusmarket_secret_salt';
        $expected = hash_hmac('sha256', strtolower(trim($email)), $secret);
        return hash_equals($expected, $token);
    }
}

if (!function_exists('buildMarketingEmailHtml')) {
    /**
     * Wrap marketing content in a responsive, branded HTML email layout.
     */
    function buildMarketingEmailHtml(string $headline, string $bodyContent, string $ctaUrl = '', string $ctaText = 'Explore Marketplace', ?string $unsubscribeUrl = null): string {
        $appName = defined('APP_NAME') ? APP_NAME : 'CampusMarket';
        $themeColor = defined('APP_THEME_COLOR') ? APP_THEME_COLOR : '#1a7f64';
        $unsub = $unsubscribeUrl ?: '#';
        $base = defined('BASE_URL') ? BASE_URL : 'https://campusmarketplace.site/';
        $browseUrl = rtrim($base, '/') . '/pages/browse.php';
        $ctaTarget = !empty($ctaUrl) ? $ctaUrl : $browseUrl;

        $safeHeadline = htmlspecialchars($headline, ENT_QUOTES, 'UTF-8');
        
        $ctaBlock = '';
        if (!empty($ctaText)) {
            $safeCta = htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8');
            $safeTarget = htmlspecialchars($ctaTarget, ENT_QUOTES, 'UTF-8');
            $ctaBlock = <<<HTML
            <div style="text-align: center; margin: 32px 0 24px 0;">
                <a href="{$safeTarget}" style="display: inline-block; background: {$themeColor}; color: #ffffff; padding: 14px 30px; font-size: 15px; font-weight: 700; text-decoration: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(26, 127, 100, 0.25);">
                    {$safeCta} →
                </a>
            </div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeHeadline}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f1f5f9; margin: 0; padding: 32px 16px; color: #0f172a; -webkit-font-smoothing: antialiased;">
  <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 20px rgba(0,0,0,0.04);">
    
    <!-- Brand Header -->
    <tr>
      <td style="padding: 28px 36px; background: linear-gradient(135deg, #1a7f64 0%, #0d5440 100%); color: #ffffff; text-align: center;">
        <h1 style="margin: 0; font-size: 24px; font-weight: 800; letter-spacing: -0.02em;">{$appName}</h1>
        <p style="margin: 4px 0 0 0; font-size: 13px; opacity: 0.9; font-weight: 500;">Campus Student Marketplace</p>
      </td>
    </tr>

    <!-- Body Content -->
    <tr>
      <td style="padding: 36px 36px 28px 36px;">
        <h2 style="margin: 0 0 16px 0; font-size: 21px; font-weight: 700; color: #0f172a; line-height: 1.3;">{$safeHeadline}</h2>
        <div style="font-size: 15px; line-height: 1.65; color: #334155;">
          {$bodyContent}
        </div>
        {$ctaBlock}
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="padding: 24px 36px; background: #f8fafc; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 12px; line-height: 1.6;">
        <p style="margin: 0 0 6px 0; font-weight: 600; color: #475569;">
          {$appName} · Buy & Sell Securely Across Campus
        </p>
        <p style="margin: 0 0 12px 0;">
          Sent by <a href="mailto:marketing@campusmarketplace.site" style="color: #64748b; text-decoration: underline;">marketing@campusmarketplace.site</a>
        </p>
        <p style="margin: 0;">
          Prefer not to receive campus promo updates? <a href="{$unsub}" style="color: #1a7f64; text-decoration: underline; font-weight: 600;">Unsubscribe here</a>
        </p>
      </td>
    </tr>

  </table>
</body>
</html>
HTML;
    }
}

if (!function_exists('sendMarketingEmail')) {
    /**
     * Dispatch a promotional marketing campaign email via Resend with compliance headers.
     */
    function sendMarketingEmail(string $to, string $subject, string $htmlContent, ?string $unsubscribeUrl = null): array {
        $resendApiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : getenv('RESEND_API_KEY');
        if (empty($resendApiKey)) {
            error_log('[mailer] RESEND_API_KEY not set in config/secrets.php or environment');
            return ['ok' => false, 'error' => 'Mail service not configured.'];
        }

        $fromName  = defined('MARKETING_FROM_NAME') ? MARKETING_FROM_NAME : 'CampusMarket';
        $fromEmail = defined('MARKETING_FROM_EMAIL') ? MARKETING_FROM_EMAIL : 'marketing@campusmarketplace.site';
        $replyTo   = defined('MARKETING_REPLY_TO') ? MARKETING_REPLY_TO : 'marketing@campusmarketplace.site';

        $from = $fromName ? "{$fromName} <{$fromEmail}>" : $fromEmail;
        $unsub = $unsubscribeUrl ?: generateUnsubscribeUrl($to);

        $payload = [
            'from'     => $from,
            'to'       => [$to],
            'reply_to' => $replyTo,
            'subject'  => $subject,
            'html'     => $htmlContent,
            'headers'  => [
                'List-Unsubscribe'      => "<{$unsub}>",
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
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
            CURLOPT_TIMEOUT        => 12,
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'response' => json_decode($body, true)];
        }

        error_log("[mailer] Marketing Resend failed status={$status} curlErr={$err} body={$body}");
        return ['ok' => false, 'status' => $status, 'error' => $err ?: $body];
    }
}
