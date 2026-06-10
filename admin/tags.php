<?php
// admin/tags.php
require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/auth_check.php';

requireAdmin();

$pageTitle = "Manage Tags";

// Handle Add Tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tag'])) {
    verifyCsrfToken();
    $name = sanitize($_POST['name']);
    $slug = sanitize(strtolower(str_replace(' ', '-', $name)));

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?)");
            $stmt->execute([$name, $slug]);
            setFlash('success', "Tag '#$name' added successfully.");
        } catch (PDOException $e) {
            setFlash('error', "Tag '#$name' already exists.");
        }
    } else {
        setFlash('error', "Tag name cannot be empty.");
    }
    redirect(BASE_URL . 'admin/tags.php');
}

// Handle Delete Tag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_tag'])) {
    verifyCsrfToken();
    $tid = (int)$_POST['tag_id'];
    if ($tid > 0) {
        $pdo->prepare("DELETE FROM tags WHERE id = ?")->execute([$tid]);
        setFlash('success', "Tag deleted.");
    }
    redirect(BASE_URL . 'admin/tags.php');
}

// Restore missing default tags (idempotent)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_default_tags'])) {
    verifyCsrfToken();
    try {
        $added = seedDefaultTags($pdo);
        if ($added > 0) {
            setFlash('success', "Restored {$added} default tag(s). AI suggestions and listing tags are available again.");
        } else {
            setFlash('success', 'All default tags are already present.');
        }
    } catch (PDOException $e) {
        setFlash('error', 'Could not restore default tags: ' . $e->getMessage());
    }
    redirect(BASE_URL . 'admin/tags.php');
}

// Fetch tags with usage count
$tags = $pdo->query("
    SELECT t.id, t.name, t.slug, COUNT(pt.product_id) AS usage_count
    FROM tags t
    LEFT JOIN product_tags pt ON pt.tag_id = t.id
    GROUP BY t.id, t.name, t.slug
    ORDER BY t.name ASC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="admin-page">
    <div class="admin-page-header">
        <div>
            <div class="admin-breadcrumb"><a href="<?php echo BASE_URL; ?>admin/index.php">Dashboard</a> › Tags</div>
            <h1>Manage Interest Tags</h1>
            <p style="margin: 0.25rem 0 0; font-size: 0.85rem; color: var(--text-muted);">
                <?php echo count($tags); ?> tag<?php echo count($tags) !== 1 ? 's' : ''; ?> · AI uses these when auto-tagging listings
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <form method="POST" style="margin: 0;" onsubmit="return confirm('Restore the 12 default marketplace tags? Existing custom tags are kept.');">
                <?php echo csrfTokenField(); ?>
                <button type="submit" name="restore_default_tags" class="btn btn-secondary">Restore Defaults</button>
            </form>
            <button onclick="document.getElementById('add-tag-card').scrollIntoView({behavior:'smooth'})" class="btn btn-primary">
                + Create Tag
            </button>
        </div>
    </div>

    <?php if (empty($tags)): ?>
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; background: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #f97316; border-radius: var(--radius-md); padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
        <div>
            <div style="font-weight: 700; color: #9a3412; margin-bottom: 0.25rem;">No tags available</div>
            <div style="font-size: 0.85rem; color: #c2410c;">Sellers cannot pick tags and AI suggestions will not work until defaults are restored.</div>
        </div>
        <form method="POST" style="margin: 0;" onsubmit="return confirm('Restore all 12 default marketplace tags?');">
            <?php echo csrfTokenField(); ?>
            <button type="submit" name="restore_default_tags" class="btn btn-primary">Restore Default Tags</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="admin-two-col">

        <!-- Tags list -->
        <div class="card">
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th>Tag</th>
                            <th>System Slug</th>
                            <th style="width: 100px; text-align: center;">Listings</th>
                            <th style="width: 80px; text-align: right;">Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tags)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="admin-empty">
                                    <span class="admin-empty-icon"><svg style="width: 32px; height: 32px; display: inline-block; color: var(--text-muted); opacity: 0.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                                    No tags yet. <strong>Restore defaults</strong> to re-enable AI tagging →
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td class="muted">#<?php echo $tag['id']; ?></td>
                            <td>
                                <span class="badge badge-secondary" style="font-size: 0.85rem;">#<?php echo sanitize($tag['name']); ?></span>
                            </td>
                            <td><code style="background: var(--bg-main); padding: 0.2rem 0.6rem; border-radius: var(--radius-sm); font-size: 0.82rem;"><?php echo sanitize($tag['slug']); ?></code></td>
                            <td style="text-align: center;">
                                <?php if ((int)$tag['usage_count'] > 0): ?>
                                    <span class="badge badge-primary" style="font-size: 0.78rem;"><?php echo (int)$tag['usage_count']; ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete tag #<?php echo sanitize($tag['name']); ?>?<?php echo count($tags) <= 1 ? ' This is the last tag — AI suggestions and seller tag picks will stop working.' : ' This will remove it from all listings.'; ?>');">
                                    <?php echo csrfTokenField(); ?>
                                    <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                    <button type="submit" name="delete_tag" class="btn btn-danger btn-sm" style="border-radius: var(--radius-lg); padding: 0.25rem 0.6rem; font-size: 0.78rem;" title="Delete tag">✕</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add form -->
        <div id="add-tag-card">
            <div class="card">
                <div class="card-header">
                    <h3 style="margin-bottom: 0.25rem;">Quick Add Tag</h3>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Searchable keywords for products.</p>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php echo csrfTokenField(); ?>
                        <div class="form-group">
                            <label class="form-label">Tag Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Vintage, Study, Tech" required>
                        </div>
                        <button type="submit" name="add_tag" class="btn btn-primary" style="width: 100%;">Save Tag</button>
                    </form>
                </div>
            </div>

            <div style="margin-top: 1rem; padding: 1rem 1.25rem; background: var(--bg-surface); border: 1px solid var(--border-light); border-left: 4px solid var(--secondary); border-radius: var(--radius-md);">
                <h4 style="font-size: 0.9rem; margin-bottom: 0.35rem; color: var(--text-main);">AI-Powered Tagging</h4>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin: 0;">
                    When a seller lists a product, the AI suggests relevant tags from this list based on the title and description. Tags added here are immediately available for suggestion.
                </p>
            </div>
        </div>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
