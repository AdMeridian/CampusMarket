<?php

/**
 * Shared URL/email placeholders for policy page translations.
 */
function policyI18nParams(string $supportEmail): array
{
    $b = BASE_URL;

    return [
        'privacy_url' => $b . 'pages/privacy.php',
        'terms_url' => $b . 'pages/terms.php',
        'rules_url' => $b . 'pages/rules.php',
        'cookies_url' => $b . 'pages/cookies.php',
        'safety_url' => $b . 'pages/safety.php',
        'report_url' => $b . 'pages/report.php',
        'my_reports_url' => $b . 'pages/my_reports.php',
        'support_email' => htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'),
        'date' => __('policy.last_updated_date'),
    ];
}
