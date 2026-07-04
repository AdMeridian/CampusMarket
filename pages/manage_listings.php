<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAgent();

$pageTitle = __('agent.manage_page_title');
$statusFilter = sanitize($_GET['status'] ?? 'all');
$searchQuery = trim((string)($_GET['q'] ?? ''));
$editId = (int)($_GET['edit'] ?? 0);
$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = sanitize($_POST['action'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);
    $agentId = (int)currentUserId();

    if ($productId <= 0 || !agentOwnsProduct($pdo, $productId, $agentId)) {
        $flashError = __('agent.listing_not_found');
    } elseif ($action === 'update_contact') {
        $contact = parseManagedOwnerContactFromRequest($_POST);
        $errors = validateManagedOwnerContact($contact, true);
        if (!empty($errors)) {
            $flashError = reset($errors);
            $editId = $productId;
        } elseif (saveManagedListingContact($pdo, $productId, $contact)) {
            setFlash('success', __('agent.contact_updated'));
            redirect(BASE_URL . 'pages/manage_listings.php?' . http_build_query(array_filter([
                'status' => $statusFilter !== 'all' ? $statusFilter : null,
                'q' => $searchQuery !== '' ? $searchQuery : null,
            ])));
        } else {
            $flashError = __('agent.contact_save_failed');
            $editId = $productId;
        }
    }
}

$listings = getManagedListingsForAgent($pdo, (int)currentUserId(), $statusFilter, $searchQuery);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-24 mb-20">
    <div class="flex flex-wrap justify-between items-end gap-4 mb-8">
        <div>
            <h1 class="page-hero-title mb-2"><?= __('agent.manage_page_title') ?></h1>
            <p class="page-subtitle mb-0"><?= __('agent.manage_page_subtitle') ?></p>
        </div>
        <a href="<?php echo BASE_URL; ?>pages/create_listing.php" class="btn btn-primary"><?= __('agent.create_managed_listing') ?></a>
    </div>

    <?php if ($flashError): ?>
        <div class="flash flash-error mb-6"><?php echo sanitize($flashError); ?></div>
    <?php endif; ?>

    <form method="get" class="glass-panel mb-6" style="padding: 1rem 1.25rem; border-radius: var(--radius-lg); display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: end;">
        <div style="flex: 1 1 220px;">
            <label for="q" class="form-label font-bold"><?= __('agent.search_label') ?></label>
            <input type="search" id="q" name="q" value="<?php echo sanitize($searchQuery); ?>" placeholder="<?= htmlspecialchars(__('agent.search_placeholder')) ?>" class="w-full premium-input" style="padding: 0.7rem 1rem;">
        </div>
        <div style="flex: 0 1 180px;">
            <label for="status" class="form-label font-bold"><?= __('agent.status_filter') ?></label>
            <select id="status" name="status" class="w-full premium-input" style="padding: 0.7rem 1rem;">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>><?= __('agent.status_all') ?></option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>><?= __('agent.status_active') ?></option>
                <option value="pending_approval" <?php echo $statusFilter === 'pending_approval' ? 'selected' : ''; ?>><?= __('agent.status_pending') ?></option>
                <option value="sold" <?php echo $statusFilter === 'sold' ? 'selected' : ''; ?>><?= __('agent.status_sold') ?></option>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary" style="min-height: 46px;"><?= __('agent.apply_filters') ?></button>
    </form>

    <?php if (empty($listings)): ?>
        <div class="glass-panel text-center py-12" style="border-radius: var(--radius-xl);">
            <p class="text-muted mb-4"><?= __('agent.no_listings') ?></p>
            <a href="<?php echo BASE_URL; ?>pages/create_listing.php" class="btn btn-primary"><?= __('agent.create_managed_listing') ?></a>
        </div>
    <?php else: ?>
        <div class="managed-listings-stack" style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($listings as $row): ?>
                <?php
                    $isEditing = $editId === (int)$row['id'];
                    $imgUrl = getProductImage($row['image_path'] ?? null);
                    $status = (string)($row['status'] ?? 'active');
                ?>
                <article class="glass-panel managed-listing-card" style="border-radius: var(--radius-lg); padding: 1.25rem; border: 1px solid var(--border-light);">
                    <div class="flex flex-wrap gap-4" style="align-items: flex-start;">
                        <a href="<?php echo BASE_URL; ?>pages/product.php?id=<?php echo (int)$row['id']; ?>" style="flex: 0 0 96px;">
                            <img src="<?php echo $imgUrl; ?>" alt="" style="width: 96px; height: 72px; object-fit: cover; border-radius: var(--radius-md); background: var(--bg-main);">
                        </a>
                        <div style="flex: 1 1 240px; min-width: 0;">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <h2 style="font-size: 1.05rem; font-weight: 700; margin: 0;">
                                    <a href="<?php echo BASE_URL; ?>pages/product.php?id=<?php echo (int)$row['id']; ?>" style="color: var(--text-main); text-decoration: none;">
                                        <?php echo sanitize($row['title']); ?>
                                    </a>
                                </h2>
                                <span class="badge" style="font-size: 0.68rem; text-transform: uppercase;"><?php echo sanitize($status); ?></span>
                            </div>
                            <p class="text-muted small mb-2"><?php echo sanitize(translateCategory($row['category_name'] ?? '')); ?> · <?php echo renderProductPrice($row); ?> · <?= __('product.listed_time', ['time' => timeAgo($row['created_at'])]) ?></p>

                            <?php if (!$isEditing): ?>
                                <div class="managed-contact-summary" style="font-size: 0.9rem; line-height: 1.5;">
                                    <?php if (!empty($row['owner_name'])): ?>
                                        <div><strong><?= __('agent.owner_name_label') ?>:</strong> <?php echo sanitize($row['owner_name']); ?></div>
                                        <div><strong><?= __('agent.owner_phone_label') ?>:</strong> <?php echo sanitize($row['owner_phone']); ?></div>
                                        <?php if (!empty($row['owner_email'])): ?>
                                            <div><strong><?= __('agent.owner_email_label') ?>:</strong> <?php echo sanitize($row['owner_email']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['owner_notes'])): ?>
                                            <div class="text-muted" style="margin-top: 0.35rem;"><?php echo nl2br(sanitize($row['owner_notes'])); ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-muted mb-0" style="color: #b45309;"><?= __('agent.missing_contact') ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-3">
                                    <a href="<?php echo BASE_URL; ?>pages/manage_listings.php?<?php echo http_build_query(array_filter(['edit' => (int)$row['id'], 'status' => $statusFilter !== 'all' ? $statusFilter : null, 'q' => $searchQuery !== '' ? $searchQuery : null])); ?>" class="btn btn-secondary btn-sm"><?= __('agent.edit_contact') ?></a>
                                </div>
                            <?php else: ?>
                                <form method="post" class="mt-2">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="action" value="update_contact">
                                    <input type="hidden" name="product_id" value="<?php echo (int)$row['id']; ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="form-label font-bold"><?= __('agent.owner_name_label') ?></label>
                                            <input type="text" name="owner_name" maxlength="120" class="w-full premium-input" required value="<?php echo sanitize($row['owner_name'] ?? ''); ?>">
                                        </div>
                                        <div>
                                            <label class="form-label font-bold"><?= __('agent.owner_phone_label') ?></label>
                                            <input type="tel" name="owner_phone" maxlength="20" class="w-full premium-input" required value="<?php echo sanitize($row['owner_phone'] ?? ''); ?>">
                                        </div>
                                        <div>
                                            <label class="form-label font-bold"><?= __('agent.owner_email_label') ?></label>
                                            <input type="email" name="owner_email" maxlength="100" class="w-full premium-input" value="<?php echo sanitize($row['owner_email'] ?? ''); ?>">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="form-label font-bold"><?= __('agent.owner_notes_label') ?></label>
                                            <textarea name="owner_notes" rows="3" maxlength="2000" class="w-full premium-input"><?php echo sanitize($row['owner_notes'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 mt-3 flex-wrap">
                                        <button type="submit" class="btn btn-primary btn-sm"><?= __('agent.save_contact') ?></button>
                                        <a href="<?php echo BASE_URL; ?>pages/manage_listings.php?<?php echo http_build_query(array_filter(['status' => $statusFilter !== 'all' ? $statusFilter : null, 'q' => $searchQuery !== '' ? $searchQuery : null])); ?>" class="btn btn-secondary btn-sm"><?= __('agent.cancel_edit') ?></a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
