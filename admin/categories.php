<?php
// admin/categories.php
require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/auth_check.php';
require_once '../includes/admin_audit.php';

requireAdmin();

$pageTitle = "Manage Categories";

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    verifyCsrfToken();
    $name = sanitize($_POST['name']);
    $slug = sanitize(strtolower(str_replace(' ', '-', $name)));

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            setFlash('success', "Category '$name' added successfully.");
            invalidateNavCategoriesCache();
            logAdminAction($pdo, 'add_category', 'category', (int)$pdo->lastInsertId(), ['name' => $name]);
        } catch (PDOException $e) {
            setFlash('error', "Category '$name' could not be added — slug may already exist.");
        }
    } else {
        setFlash('error', "Category name cannot be empty.");
    }
    redirect(BASE_URL . 'admin/categories.php');
}

// Handle Edit Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
    verifyCsrfToken();
    $id = (int)($_POST['category_id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');

    if ($id > 0 && $name !== '') {
        $slug = sanitize(strtolower(str_replace(' ', '-', $name)));
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $id]);
            setFlash('success', "Category '$name' updated successfully.");
            invalidateNavCategoriesCache();
            logAdminAction($pdo, 'edit_category', 'category', $id, ['name' => $name]);
        } catch (PDOException $e) {
            setFlash('error', "Could not update category — slug may already be in use.");
        }
    } else {
        setFlash('error', "Category name cannot be empty.");
    }
    redirect(BASE_URL . 'admin/categories.php');
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    verifyCsrfToken();
    $id = (int)($_POST['category_id'] ?? 0);

    if ($id > 0) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $countStmt->execute([$id]);
        $listingCount = (int)$countStmt->fetchColumn();

        if ($listingCount > 0) {
            setFlash('error', "Cannot delete — {$listingCount} listing(s) use this category. Reassign them first.");
        } else {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            setFlash('success', 'Category deleted.');
            invalidateNavCategoriesCache();
            logAdminAction($pdo, 'delete_category', 'category', $id);
        }
    }
    redirect(BASE_URL . 'admin/categories.php');
}

$categories = $pdo->query("
    SELECT c.id, c.name, c.slug, COUNT(p.id) AS usage_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.slug
    ORDER BY c.name ASC
")->fetchAll();

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editingCategory = null;
if ($editId > 0) {
    foreach ($categories as $cat) {
        if ((int)$cat['id'] === $editId) {
            $editingCategory = $cat;
            break;
        }
    }
}

require_once '../includes/header.php';
?>

<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb"><a href="<?php echo BASE_URL; ?>admin/index.php">Dashboard</a> › Categories</div>
            <h1>Marketplace Categories</h1>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                <?php echo count($categories); ?> categor<?php echo count($categories) !== 1 ? 'ies' : 'y'; ?> · used when students browse and filter listings
            </p>
        </div>
        <?php if (!$editingCategory): ?>
        <button onclick="document.getElementById('add-category-card').scrollIntoView({behavior:'smooth'})" class="btn btn-primary">
            + Add Category
        </button>
        <?php endif; ?>
    </div>

    <div class="admin-two-col">

        <!-- Categories list -->
        <div class="card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Category Name</th>
                            <th>URL Slug</th>
                            <th style="width: 100px; text-align: center;">Listings</th>
                            <th style="width: 120px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="admin-empty">
                                    <span class="admin-empty-icon"><svg style="width: 32px; height: 32px; display: inline-block; color: var(--text-muted); opacity: 0.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></span>
                                    No categories yet. Add the first one →
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                        <tr<?php echo $editId === (int)$cat['id'] ? ' style="background: rgba(79, 70, 229, 0.06);"' : ''; ?>>
                            <td class="muted">#<?php echo $cat['id']; ?></td>
                            <td style="font-weight: 700;"><?php echo sanitize($cat['name']); ?></td>
                            <td><code style="background: var(--bg-main); padding: 0.2rem 0.6rem; border-radius: var(--radius-sm); font-size: 0.82rem;"><?php echo sanitize($cat['slug']); ?></code></td>
                            <td style="text-align: center;">
                                <?php if ((int)$cat['usage_count'] > 0): ?>
                                    <span class="badge badge-primary" style="font-size: 0.78rem;"><?php echo (int)$cat['usage_count']; ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 0.35rem;">
                                    <a href="?edit=<?php echo (int)$cat['id']; ?>" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-lg); padding: 0.25rem 0.6rem; font-size: 0.78rem;">Edit</a>
                                    <?php if ((int)$cat['usage_count'] === 0): ?>
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete category &quot;<?php echo sanitize($cat['name']); ?>&quot;?')">
                                        <?php echo csrfTokenField(); ?>
                                        <input type="hidden" name="category_id" value="<?php echo (int)$cat['id']; ?>">
                                        <button type="submit" name="delete_category" class="btn btn-danger btn-sm" style="border-radius: var(--radius-lg); padding: 0.25rem 0.6rem; font-size: 0.78rem;" title="Delete category">✕</button>
                                    </form>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-danger btn-sm opacity-50" style="border-radius: var(--radius-lg); padding: 0.25rem 0.6rem; font-size: 0.78rem;" disabled title="Reassign listings before deleting">✕</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add / Edit form -->
        <div id="add-category-card">
            <div class="card">
                <div class="card-header">
                    <?php if ($editingCategory): ?>
                    <h3 style="margin-bottom: 0.25rem;">Edit Category</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Updating #<?php echo (int)$editingCategory['id']; ?> — slug regenerates from the name.</p>
                    <?php else: ?>
                    <h3 style="margin-bottom: 0.25rem;">Add New Category</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Slugs are auto-generated from the name.</p>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrfTokenField(); ?>
                        <?php if ($editingCategory): ?>
                        <input type="hidden" name="category_id" value="<?php echo (int)$editingCategory['id']; ?>">
                        <?php endif; ?>
                        <div class="form-group">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Electronics, Books" required value="<?php echo $editingCategory ? sanitize($editingCategory['name']) : ''; ?>">
                        </div>
                        <?php if ($editingCategory): ?>
                        <button type="submit" name="edit_category" class="btn btn-primary" style="width: 100%; margin-bottom: 0.5rem;">Save Changes</button>
                        <a href="<?php echo BASE_URL; ?>admin/categories.php" class="btn btn-secondary" style="width: 100%; text-align: center;">Cancel</a>
                        <?php else: ?>
                        <button type="submit" name="add_category" class="btn btn-primary" style="width: 100%;">Save Category</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div style="margin-top: 1rem; padding: 1rem 1.25rem; background: var(--bg-surface); border: 1px solid var(--border-light); border-radius: var(--radius-md);">
                <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                    <svg style="width: 16px; height: 16px; color: var(--primary);" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <strong>Tip:</strong> Categories with active listings cannot be deleted until those listings are moved to another category.
                </p>
            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
