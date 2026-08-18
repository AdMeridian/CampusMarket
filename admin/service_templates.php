<?php
// admin/service_templates.php
require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_audit.php';

requireAdmin();

$pageTitle = "Manage Service Templates";

// Ensure service_templates table exists on local setups
try {
    $pdo->query("SELECT 1 FROM service_templates LIMIT 1");
} catch (Throwable $e) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_templates (
                id BIGSERIAL PRIMARY KEY,
                category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
                name VARCHAR(100) NOT NULL,
                title_template VARCHAR(200) NOT NULL,
                description_template TEXT NOT NULL,
                suggested_price_min NUMERIC(10,2) NULL,
                suggested_price_max NUMERIC(10,2) NULL,
                pricing_model VARCHAR(10) NOT NULL DEFAULT 'hourly',
                suggested_tags TEXT[] NULL,
                sort_order SMALLINT NOT NULL DEFAULT 0,
                is_active BOOLEAN NOT NULL DEFAULT TRUE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        ");
    } else {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS service_templates (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT NOT NULL,
                name VARCHAR(100) NOT NULL,
                title_template VARCHAR(200) NOT NULL,
                description_template TEXT NOT NULL,
                suggested_price_min NUMERIC(10,2) NULL,
                suggested_price_max NUMERIC(10,2) NULL,
                pricing_model VARCHAR(10) NOT NULL DEFAULT 'hourly',
                suggested_tags TEXT NULL,
                sort_order SMALLINT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }
}

// Handle Add Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_template'])) {
    verifyCsrfToken();
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $titleTemplate = sanitize($_POST['title_template'] ?? '');
    $descriptionTemplate = sanitize($_POST['description_template'] ?? '');
    $minPrice = !empty($_POST['suggested_price_min']) ? (float)$_POST['suggested_price_min'] : null;
    $maxPrice = !empty($_POST['suggested_price_max']) ? (float)$_POST['suggested_price_max'] : null;
    $pricingModel = in_array($_POST['pricing_model'] ?? '', ['flat', 'hourly']) ? $_POST['pricing_model'] : 'hourly';

    if ($categoryId > 0 && !empty($name) && !empty($titleTemplate)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO service_templates (category_id, name, title_template, description_template, suggested_price_min, suggested_price_max, pricing_model)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$categoryId, $name, $titleTemplate, $descriptionTemplate, $minPrice, $maxPrice, $pricingModel]);
            setFlash('success', "Template '$name' added successfully.");
        } catch (Throwable $e) {
            setFlash('error', 'Failed to add template: ' . $e->getMessage());
        }
    } else {
        setFlash('error', 'Please fill in all required template fields.');
    }
    redirect(BASE_URL . 'admin/service_templates.php');
}

// Handle Toggle Active
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    verifyCsrfToken();
    $tid = (int)($_POST['template_id'] ?? 0);
    if ($tid > 0) {
        $pdo->prepare("UPDATE service_templates SET is_active = NOT is_active WHERE id = ?")->execute([$tid]);
        setFlash('success', 'Template visibility updated.');
    }
    redirect(BASE_URL . 'admin/service_templates.php');
}

// Handle Delete Template
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_template'])) {
    verifyCsrfToken();
    $tid = (int)($_POST['template_id'] ?? 0);
    if ($tid > 0) {
        $pdo->prepare("DELETE FROM service_templates WHERE id = ?")->execute([$tid]);
        setFlash('success', 'Template deleted.');
    }
    redirect(BASE_URL . 'admin/service_templates.php');
}

// Fetch Service Categories
$serviceCategories = $pdo->query("SELECT id, name FROM categories WHERE type = 'service' ORDER BY name ASC")->fetchAll();

// Fetch Templates
$templates = [];
try {
    $templates = $pdo->query("
        SELECT st.*, c.name AS category_name
        FROM service_templates st
        JOIN categories c ON c.id = st.category_id
        ORDER BY c.name ASC, st.sort_order ASC, st.name ASC
    ")->fetchAll();
} catch (Throwable $e) {
    $templates = [];
}

require_once '../includes/header.php';
?>

<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb"><a href="<?php echo BASE_URL; ?>admin/index.php">Dashboard</a> › Service Templates</div>
            <h1>Manage Service Templates</h1>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                <?php echo count($templates); ?> template<?php echo count($templates) !== 1 ? 's' : ''; ?> · Pre-fills create listing for sellers
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <button onclick="document.getElementById('add-template-card').scrollIntoView({behavior:'smooth'})" class="btn btn-primary">
                + Create Template
            </button>
        </div>
    </div>

    <div class="admin-two-col">

        <!-- Templates List -->
        <div class="card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Template Name</th>
                            <th>Category</th>
                            <th>Pricing</th>
                            <th style="width: 80px; text-align: center;">Status</th>
                            <th style="width: 80px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="admin-empty">
                                    No custom service templates yet. Use the form on the right to add templates.
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($templates as $t): ?>
                        <tr>
                            <td>
                                <strong style="color: var(--text-main); font-size: 0.92rem;"><?php echo sanitize($t['name']); ?></strong>
                                <div style="font-size: 0.78rem; color: var(--text-muted);"><?php echo sanitize($t['title_template']); ?></div>
                            </td>
                            <td><span class="badge badge-secondary" style="font-size: 0.8rem;"><?php echo sanitize($t['category_name']); ?></span></td>
                            <td>
                                <div style="font-size: 0.85rem; font-weight: 600;">
                                    <?php if ($t['suggested_price_min']): ?>
                                        <?php echo (float)$t['suggested_price_min']; ?> – <?php echo (float)$t['suggested_price_max']; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.72rem; color: var(--text-muted); text-transform: uppercase;">
                                    <?php echo $t['pricing_model'] === 'hourly' ? 'Hourly' : 'Fixed Price'; ?>
                                </div>
                            </td>
                            <td style="text-align: center;">
                                <form method="POST" style="margin:0;">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="template_id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" name="toggle_active" class="badge" style="background: <?php echo $t['is_active'] ? 'var(--success-bg)' : 'var(--bg-main)'; ?>; color: <?php echo $t['is_active'] ? 'var(--success)' : 'var(--text-muted)'; ?>; border: 1px solid var(--border-light); cursor: pointer;">
                                        <?php echo $t['is_active'] ? 'Active' : 'Disabled'; ?>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete template \'<?php echo sanitize($t['name']); ?>\'?');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="template_id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" name="delete_template" class="btn btn-danger btn-sm" style="border-radius: var(--radius-lg); padding: 0.25rem 0.6rem; font-size: 0.78rem;" title="Delete">✕</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Template Form -->
        <div id="add-template-card">
            <div class="card">
                <div class="card-header">
                    <h3 style="margin-bottom: 0.25rem;">Add Service Template</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Creates a pre-fill preset for service providers.</p>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrfTokenField(); ?>
                        <div class="form-group">
                            <label class="form-label">Service Category</label>
                            <select name="category_id" class="form-control" required>
                                <option value="">Select Category</option>
                                <?php foreach ($serviceCategories as $sc): ?>
                                    <option value="<?php echo $sc['id']; ?>"><?php echo sanitize($sc['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Template Short Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Custom Website Development" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Default Title Template</label>
                            <input type="text" name="title_template" class="form-control" placeholder="e.g. Custom Website Development — Responsive & Modern" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Default Description</label>
                            <textarea name="description_template" rows="3" class="form-control" placeholder="Detailed service description..." required></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Pricing Model</label>
                            <select name="pricing_model" class="form-control">
                                <option value="hourly">Hourly Rate</option>
                                <option value="flat">Fixed Price / Flat Rate</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Suggested Price Range (Min – Max)</label>
                            <div style="display: flex; gap: 0.5rem;">
                                <input type="number" step="0.01" name="suggested_price_min" class="form-control" placeholder="Min (e.g. 150)">
                                <input type="number" step="0.01" name="suggested_price_max" class="form-control" placeholder="Max (e.g. 600)">
                            </div>
                        </div>

                        <button type="submit" name="add_template" class="btn btn-primary" style="width: 100%;">Save Template</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
