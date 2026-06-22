<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/policy_i18n.php';
$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (getenv('SUPPORT_EMAIL') ?: 'support@campusmarketplace.site');
$p = policyI18nParams($supportEmail);
$page_title = __('policy.terms.page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 page-content-offset">
    <div class="card p-5" style="max-width: 800px; margin: 0 auto; background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);">
        <h1 class="mb-4" style="color: var(--text-main); font-weight: 800; font-size: 2.2rem; text-align: center;"><?= __('policy.terms.title') ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;"><?= __('policy.last_updated', $p) ?></p>

        <div style="line-height: 1.8; color: var(--text-main);">
            <p><?= __('policy.terms.intro', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s1_title') ?></h3>
            <p><?= __('policy.terms.s1_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s2_title') ?></h3>
            <p><?= __('policy.terms.s2_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s3_title') ?></h3>
            <p><?= __('policy.terms.s3_body', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s4_title') ?></h3>
            <p><?= __('policy.terms.s4_body', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s5_title') ?></h3>
            <p><?= __('policy.terms.s5_body_1') ?></p>
            <p><?= __('policy.terms.s5_body_2') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s6_title') ?></h3>
            <p><?= __('policy.terms.s6_body', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s7_title') ?></h3>
            <p><?= __('policy.terms.s7_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s8_title') ?></h3>
            <p><?= __('policy.terms.s8_body', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s9_title') ?></h3>
            <p><?= __('policy.terms.s9_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s10_title') ?></h3>
            <p><?= __('policy.terms.s10_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.terms.s11_title') ?></h3>
            <p><?= __('policy.terms.s11_body', $p) ?></p>

            <p style="margin-top: 2rem; padding: 1rem; background: var(--bg-main); border-radius: var(--radius-md); font-size: 0.9rem; color: var(--text-muted);">
                <?= __('policy.terms.legal_note') ?>
            </p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
