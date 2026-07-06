<?php
// pages/categories.php
require_once __DIR__ . '/../includes/bootstrap.php';

$pagesBase = rtrim(BASE_URL, '/') . '/pages/';

$pageTitle = __('categories.browse_categories');
$pageDescription = __('seo.categories_description');

require_once __DIR__ . '/../includes/header.php';

$stmt = $pdo->query("
    SELECT c.*, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY c.name ASC
");
$categories = $stmt->fetchAll();
?>

<div class="container page-content-offset categories-page">
    <header class="categories-page__header">
        <h1 class="page-hero-title mb-2 text-main"><?= __('categories.explore_title') ?></h1>
        <p class="page-subtitle categories-page__subtitle"><?= __('categories.explore_desc') ?></p>
    </header>

    <?php if (count($categories) > 0): ?>
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
                <a href="<?php echo $pagesBase; ?>browse.php?category=<?php echo (int)$cat['id']; ?>" class="category-card card-hover">
                    <div class="category-card__icon" aria-hidden="true">
                        <?php echo categoryIconMarkup($cat['name']); ?>
                    </div>
                    <h2 class="category-card__name"><?php echo sanitize(translateCategory($cat['name'])); ?></h2>
                    <p class="category-card__count"><?= __('home.items_available', ['count' => (int)$cat['product_count']]) ?></p>
                    <span class="category-card__cta"><?= __('categories.browse_items') ?> →</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="categories-empty">
            <div class="categories-empty__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
            </div>
            <h2 class="empty-state-title mb-2"><?= __('categories.no_categories') ?></h2>
            <p class="text-muted mb-0"><?= __('categories.no_categories_desc') ?></p>
        </div>
    <?php endif; ?>

    <div class="categories-page__footer">
        <a href="<?php echo $pagesBase; ?>home.php" class="btn btn-secondary categories-page__back">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <?= __('categories.back_to_home') ?>
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
