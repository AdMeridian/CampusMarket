<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$page_title = __('policy.safety.page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4 page-content-offset">
    <div class="card p-5" style="max-width: 800px; margin: 0 auto; background: var(--bg-surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);">
        <h1 class="mb-4" style="color: var(--text-main); font-weight: 800; font-size: 2.2rem; text-align: center;"><?= __('policy.safety.title') ?></h1>
        <p style="text-align: center; color: var(--text-muted); margin-bottom: 2rem;"><?= __('policy.safety.subtitle') ?></p>

        <div style="line-height: 1.8; color: var(--text-main);">
            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.safety.s1_title') ?></h3>
            <p><?= __('policy.safety.s1_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.safety.s2_title') ?></h3>
            <p><?= __('policy.safety.s2_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.safety.s3_title') ?></h3>
            <p><?= __('policy.safety.s3_body') ?></p>

            <h3 style="margin-top: 1.5rem; color: var(--primary);"><?= __('policy.safety.s4_title') ?></h3>
            <p><?= __('policy.safety.s4_body') ?></p>

            <hr style="border-color: var(--border-light); margin: 2rem 0;">

            <h2 id="meeting-points" style="color: var(--text-main); font-weight: 800; font-size: 1.8rem;"><?= __('policy.safety.meeting_title') ?></h2>
            <ul style="margin-top: 1rem; list-style-type: disc; padding-left: 20px;">
                <li><?= __('policy.safety.mp1') ?></li>
                <li><?= __('policy.safety.mp2') ?></li>
                <li><?= __('policy.safety.mp3') ?></li>
                <li><?= __('policy.safety.mp4') ?></li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
