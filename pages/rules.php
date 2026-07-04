<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/policy_i18n.php';
$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (getenv('SUPPORT_EMAIL') ?: 'support@campusmarketplace.site');
$p = policyI18nParams($supportEmail);
$pageTitle = __('policy.rules.page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 page-content-offset">
    <div class="card p-5" style="max-width: 800px; margin: 0 auto; background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);">
        <h1 class="page-hero-title page-hero-title--center mb-4 text-main"><?= __('policy.rules.title') ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;"><?= __('policy.last_updated', $p) ?></p>

        <div style="line-height: 1.8; color: var(--text-main);">
            <p><?= __('policy.rules.intro', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s1_title') ?></h3>
            <p><?= __('policy.rules.s1_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s2_title') ?></h3>
            <ul style="padding-left: 1.25rem;">
                <li><?= __('policy.rules.s2_li_1') ?></li>
                <li><?= __('policy.rules.s2_li_2') ?></li>
                <li><?= __('policy.rules.s2_li_3') ?></li>
            </ul>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s3_title') ?></h3>
            <p><?= __('policy.rules.s3_intro') ?></p>
            <ul style="padding-left: 1.25rem;">
                <li><?= __('policy.rules.s3_li_1') ?></li>
                <li><?= __('policy.rules.s3_li_2') ?></li>
                <li><?= __('policy.rules.s3_li_3') ?></li>
                <li><?= __('policy.rules.s3_li_4') ?></li>
                <li><?= __('policy.rules.s3_li_5') ?></li>
                <li><?= __('policy.rules.s3_li_6') ?></li>
                <li><?= __('policy.rules.s3_li_7') ?></li>
            </ul>
            <p><?= __('policy.rules.s3_outro') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s4_title') ?></h3>
            <p><?= __('policy.rules.s4_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s5_title') ?></h3>
            <ul style="padding-left: 1.25rem;">
                <li><?= __('policy.rules.s5_li_1') ?></li>
                <li><?= __('policy.rules.s5_li_2') ?></li>
                <li><?= __('policy.rules.s5_li_3') ?></li>
                <li><?= __('policy.rules.s5_li_4') ?></li>
            </ul>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s6_title') ?></h3>
            <p><?= __('policy.rules.s6_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s7_title') ?></h3>
            <p><?= __('policy.rules.s7_body', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s8_title') ?></h3>
            <p><?= __('policy.rules.s8_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s9_title') ?></h3>
            <p><?= __('policy.rules.s9_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.rules.s10_title') ?></h3>
            <p><?= __('policy.rules.s10_body', $p) ?></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
