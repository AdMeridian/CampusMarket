<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/policy_i18n.php';
$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (getenv('SUPPORT_EMAIL') ?: 'support@campusmarketplace.site');
$p = policyI18nParams($supportEmail);
$page_title = __('policy.privacy.page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 page-content-offset">
    <div class="card p-5" style="max-width: 800px; margin: 0 auto; background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);">
        <h1 class="mb-4" style="color: var(--text-main); font-weight: 800; font-size: 2.2rem; text-align: center;"><?= __('policy.privacy.title') ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;"><?= __('policy.last_updated', $p) ?></p>

        <div style="line-height: 1.8; color: var(--text-main);">
            <p><?= __('policy.privacy.intro') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s1_title') ?></h3>
            <ul style="padding-left: 1.25rem;">
                <li><?= __('policy.privacy.s1_li_account') ?></li>
                <li><?= __('policy.privacy.s1_li_listing') ?></li>
                <li><?= __('policy.privacy.s1_li_messages') ?></li>
                <li><?= __('policy.privacy.s1_li_reports') ?></li>
                <li><?= __('policy.privacy.s1_li_reviews') ?></li>
                <li><?= __('policy.privacy.s1_li_notifications') ?></li>
                <li><?= __('policy.privacy.s1_li_payment') ?></li>
                <li><?= __('policy.privacy.s1_li_technical', $p) ?></li>
            </ul>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s2_title') ?></h3>
            <ul style="padding-left: 1.25rem;">
                <li><?= __('policy.privacy.s2_li_1') ?></li>
                <li><?= __('policy.privacy.s2_li_2') ?></li>
                <li><?= __('policy.privacy.s2_li_3') ?></li>
                <li><?= __('policy.privacy.s2_li_4') ?></li>
                <li><?= __('policy.privacy.s2_li_5') ?></li>
                <li><?= __('policy.privacy.s2_li_6') ?></li>
                <li><?= __('policy.privacy.s2_li_7') ?></li>
            </ul>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s3_title') ?></h3>
            <p><?= __('policy.privacy.s3_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s4_title') ?></h3>
            <p><?= __('policy.privacy.s4_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s5_title') ?></h3>
            <p><?= __('policy.privacy.s5_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s6_title') ?></h3>
            <p><?= __('policy.privacy.s6_intro') ?></p>
            <ul style="padding-left: 1.25rem;">
                <li><?= __('policy.privacy.s6_li_supabase') ?></li>
                <li><?= __('policy.privacy.s6_li_vercel') ?></li>
                <li><?= __('policy.privacy.s6_li_stripe') ?></li>
                <li><?= __('policy.privacy.s6_li_resend') ?></li>
                <li><?= __('policy.privacy.s6_li_google') ?></li>
                <li><?= __('policy.privacy.s6_li_openrouter') ?></li>
            </ul>
            <p><?= __('policy.privacy.s6_outro') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s7_title') ?></h3>
            <p><?= __('policy.privacy.s7_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s8_title') ?></h3>
            <p><?= __('policy.privacy.s8_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s9_title') ?></h3>
            <p><?= __('policy.privacy.s9_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s10_title') ?></h3>
            <ul style="padding-left: 1.25rem;">
                <li><?= __('policy.privacy.s10_li_1') ?></li>
                <li><?= __('policy.privacy.s10_li_2') ?></li>
                <li><?= __('policy.privacy.s10_li_3') ?></li>
            </ul>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.privacy.s11_title') ?></h3>
            <p><?= __('policy.privacy.s11_body', $p) ?></p>

            <p style="margin-top: 2rem; padding: 1rem; background: var(--bg-main); border-radius: var(--radius-md); font-size: 0.9rem; color: var(--text-muted);">
                <?= __('policy.privacy.legal_note') ?>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
