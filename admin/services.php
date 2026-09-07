<?php
// admin/services.php
require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_audit.php';

requireAdmin();

$pageTitle = 'Manage Services';

if (function_exists('ensureServicesTable')) {
    ensureServicesTable($pdo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    verifyCsrfToken();
    $name = trim(sanitize($_POST['name'] ?? ''));
    $description = trim(sanitize($_POST['description'] ?? ''));
    $icon = trim(sanitize($_POST['icon'] ?? 'service'));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        setFlash('error', 'Service name cannot be empty.');
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO services (name, description, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description !== '' ? $description : null, $icon !== '' ? $icon : 'service', $sortOrder, $isActive]);
            setFlash('success', "Service '$name' added successfully.");
            logAdminAction($pdo, 'add_service', 'service', (int)$pdo->lastInsertId(), ['name' => $name]);
        } catch (PDOException $e) {
            setFlash('error', "Service '$name' could not be added. It may already exist.");
        }
    }
    redirect(BASE_URL . 'admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service'])) {
    verifyCsrfToken();
    $id = (int)($_POST['service_id'] ?? 0);
    $name = trim(sanitize($_POST['name'] ?? ''));
    $description = trim(sanitize($_POST['description'] ?? ''));
    $icon = trim(sanitize($_POST['icon'] ?? 'service'));
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    if ($id <= 0 || $name === '') {
        setFlash('error', 'Service name cannot be empty.');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $description !== '' ? $description : null, $icon !== '' ? $icon : 'service', $sortOrder, $isActive, $id]);
            setFlash('success', "Service '$name' updated successfully.");
            logAdminAction($pdo, 'edit_service', 'service', $id, ['name' => $name]);
        } catch (PDOException $e) {
            setFlash('error', 'Could not update service. The name may already be in use.');
        }
    }
    redirect(BASE_URL . 'admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    verifyCsrfToken();
    $id = (int)($_POST['service_id'] ?? 0);
    if ($id > 0) {
        $pdo->prepare("DELETE FROM services WHERE id = ?")->execute([$id]);
        setFlash('success', 'Service deleted.');
        logAdminAction($pdo, 'delete_service', 'service', $id);
    }
    redirect(BASE_URL . 'admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_default_services'])) {
    verifyCsrfToken();
    try {
        $added = seedDefaultServices($pdo);
        setFlash('success', $added > 0 ? "Restored {$added} default service(s)." : 'All default services are already present.');
    } catch (PDOException $e) {
        setFlash('error', 'Could not restore default services: ' . $e->getMessage());
    }
    redirect(BASE_URL . 'admin/services.php');
}

$services = $pdo->query("
    SELECT id, name, description, icon, is_active, sort_order, created_at
    FROM services
    ORDER BY sort_order ASC, name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editingService = null;
if ($editId > 0) {
    foreach ($services as $service) {
        if ((int)$service['id'] === $editId) {
            $editingService = $service;
            break;
        }
    }
}

require_once '../includes/header.php';
?>

<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb"><a href="<?php echo BASE_URL; ?>admin/index.php">Dashboard</a> &gt; Services</div>
            <h1>Marketplace Services</h1>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                <?php echo count($services); ?> service<?php echo count($services) !== 1 ? 's' : ''; ?> listed for the local marketplace
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Restore the default service list? Existing custom services are kept.');">
                <?php echo csrfTokenField(); ?>
                <button type="submit" name="restore_default_services" class="btn btn-secondary">Restore Defaults</button>
            </form>
            <?php if (!$editingService): ?>
            <button onclick="document.getElementById('add-service-card').scrollIntoView({behavior:'smooth'})" class="btn btn-primary">
                + Add Service
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-two-col">
        <div class="card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Service</th>
                            <th>Description</th>
                            <th style="width: 90px; text-align: center;">Order</th>
                            <th style="width: 90px; text-align: center;">Status</th>
                            <th style="width: 120px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="admin-empty">No services yet. Restore defaults or add the first service.</div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($services as $service): ?>
                        <tr<?php echo $editId === (int)$service['id'] ? ' style="background: rgba(79, 70, 229, 0.06);"' : ''; ?>>
                            <td class="muted">#<?php echo (int)$service['id']; ?></td>
                            <td style="font-weight: 700;">
                                <span class="badge badge-secondary" style="font-size: 0.78rem; margin-right: 0.4rem;"><?php echo sanitize($service['icon'] ?? 'service'); ?></span>
                                <?php echo sanitize($service['name']); ?>
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.86rem;"><?php echo sanitize($service['description'] ?? ''); ?></td>
                            <td style="text-align: center;"><?php echo (int)$service['sort_order']; ?></td>
                            <td style="text-align: center;">
                                <?php if ((int)$service['is_active'] === 1): ?>
                                    <span class="badge badge-success" style="font-size: 0.74rem;">Active</span>
                                <?php else: ?>
                                    <span class="badge" style="font-size: 0.74rem; background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light);">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 0.35rem;">
                                    <a href="?edit=<?php echo (int)$service['id']; ?>" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-lg); padding: 0.25rem 0.6rem; font-size: 0.78rem;">Edit</a>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete service &quot;<?php echo sanitize($service['name']); ?>&quot;?')">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="service_id" value="<?php echo (int)$service['id']; ?>">
                                        <button type="submit" name="delete_service" class="btn btn-danger btn-sm" style="border-radius: var(--radius-lg); padding: 0.25rem 0.6rem; font-size: 0.78rem;" title="Delete service">x</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="add-service-card">
            <div class="card">
                <div class="card-header">
                    <?php if ($editingService): ?>
                    <h3 style="margin-bottom: 0.25rem;">Edit Service</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Updating #<?php echo (int)$editingService['id']; ?>.</p>
                    <?php else: ?>
                    <h3 style="margin-bottom: 0.25rem;">Add Service</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">These are services students can browse and offer.</p>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrfTokenField(); ?>
                        <?php if ($editingService): ?>
                        <input type="hidden" name="service_id" value="<?php echo (int)$editingService['id']; ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label class="form-label">Service Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Tutoring & Academic Help" required maxlength="255" value="<?php echo $editingService ? sanitize($editingService['name']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="4" placeholder="Short examples of what this service includes"><?php echo $editingService ? sanitize($editingService['description'] ?? '') : ''; ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Icon Key</label>
                            <input type="text" name="icon" class="form-control" placeholder="e.g. book-open" maxlength="64" value="<?php echo $editingService ? sanitize($editingService['icon'] ?? 'service') : 'service'; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0" max="32767" value="<?php echo $editingService ? (int)$editingService['sort_order'] : 0; ?>">
                        </div>

                        <label style="display: flex; align-items: center; gap: 0.55rem; margin-bottom: 1rem; font-weight: 700;">
                            <input type="checkbox" name="is_active" value="1" <?php echo (!$editingService || (int)$editingService['is_active'] === 1) ? 'checked' : ''; ?>>
                            Active
                        </label>

                        <?php if ($editingService): ?>
                        <button type="submit" name="edit_service" class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">Save Changes</button>
                        <a href="<?php echo BASE_URL; ?>admin/services.php" class="btn btn-secondary" style="width: 100%; text-align: center;">Cancel</a>
                        <?php else: ?>
                        <button type="submit" name="add_service" class="btn btn-primary" style="width: 100%;">Save Service</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
