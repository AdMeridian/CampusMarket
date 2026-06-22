<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/policy_i18n.php';
$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : (getenv('SUPPORT_EMAIL') ?: 'support@campusmarketplace.site');
$p = policyI18nParams($supportEmail);
$page_title = __('policy.cookies.page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 page-content-offset">
    <div class="card p-5" style="max-width: 800px; margin: 0 auto; background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);">
        <h1 class="mb-4" style="color: var(--text-main); font-weight: 800; font-size: 2.2rem; text-align: center;"><?= __('policy.cookies.title') ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;"><?= __('policy.last_updated', $p) ?></p>

        <div style="line-height: 1.8; color: var(--text-main);">
            <p><?= __('policy.cookies.intro', $p) ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.cookies.s1_title') ?></h3>
            <p><?= __('policy.cookies.s1_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.cookies.s2_title') ?></h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.92rem; margin-top: 0.75rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-light); text-align: left;">
                            <th style="padding: 0.5rem;"><?= __('policy.cookies.col_name') ?></th>
                            <th style="padding: 0.5rem;"><?= __('policy.cookies.col_purpose') ?></th>
                            <th style="padding: 0.5rem;"><?= __('policy.cookies.col_type') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.5rem;"><code>csrf_token</code></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.row_csrf') ?></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.type_essential') ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.5rem;"><code>campusmarket_sess_stateless</code></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.row_session') ?></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.type_essential') ?></td>
                        </tr>
                        <tr style="border-bottom: 1px solid var(--border-light);">
                            <td style="padding: 0.5rem;"><code>campusmarket_lang</code></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.row_lang') ?></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.type_functional') ?></td>
                        </tr>
                        <tr>
                            <td style="padding: 0.5rem;"><code>PHPSESSID</code></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.row_php') ?></td>
                            <td style="padding: 0.5rem;"><?= __('policy.cookies.type_essential') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-muted);"><?= __('policy.cookies.no_tracking') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.cookies.s3_title') ?></h3>
            <p><?= __('policy.cookies.s3_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.cookies.s4_title') ?></h3>
            <p><?= __('policy.cookies.s4_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.cookies.s5_title') ?></h3>
            <p><?= __('policy.cookies.s5_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.cookies.s6_title') ?></h3>
            <p><?= __('policy.cookies.s6_body', $p) ?></p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
