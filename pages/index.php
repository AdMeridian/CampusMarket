<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/bootstrap.php';

// Fetch latest 6 active products from database
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 6
");
$stmt->execute();
$latest_products = $stmt->fetchAll();

// Fetch popular categories (PostgreSQL-compatible)
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY product_count DESC
    LIMIT 4
")->fetchAll();

// Fetch 5 products for each of these categories
foreach ($categories as &$cat) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
        WHERE p.status = 'active' AND p.category_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$cat['id']]);
    $cat['products'] = $stmt->fetchAll();
}
unset($cat);
?>
<?php
$pageTitle = __('home.index_page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<!-- HERO SECTION -->
<div class="hero-wrap" style="background: var(--primary); padding: 5rem 0; margin-bottom: 4rem; color: white; position: relative; overflow: hidden; border-radius: 0 0 40px 40px;">
    <div class="container relative z-10 text-center">
        <p class="text-indigo-100 font-bold uppercase tracking-widest mb-4" style="font-size: 0.85rem;"><?= __('home.index_tagline') ?></p>
        <h1 class="hero-title mb-6"><?= __('home.index_headline') ?></h1>
        <p class="hero-subtitle mb-10 max-w-2xl mx-auto"><?= __('home.index_subline') ?></p>
        <div class="flex justify-center gap-4">
            <a href="browse.php" class="btn btn-white-solid px-8 py-4 text-lg shadow-xl hover-scale" style="border-radius: 16px; font-weight: 800; color: #4f46e5 !important;">
                <?= __('home.start_browsing') ?>
            </a>
            <a href="create_listing.php" class="btn btn-outline border-white text-white px-8 py-4 text-lg hover-scale" style="border-radius: 16px; font-weight: 800; border-width: 2px;">
                <?= __('home.sell_something') ?>
            </a>
        </div>
    </div>
</div>

<div class="container">

<!-- LATEST LISTINGS -->
<div class="section">
  <div class="section-header">
    <h2 class="section-title"><?= __('home.latest_listings') ?></h2>
    <a href="browse.php" class="view-all"><?= __('home.view_all_arrow') ?></a>
  </div>
  <div class="scroll-row">
    <?php foreach($latest_products as $prod): ?>
      <?php include __DIR__ . '/../includes/product_card_template.php'; ?>
    <?php endforeach; ?>
  </div>
</div>

<!-- POPULAR CATEGORIES -->
<div class="section" style="background:#fff;border-top:1px solid var(--gray-200);border-bottom:1px solid var(--gray-200);max-width:100%;padding:2.5rem 0;">
<div style="max-width:1280px;margin:0 auto;padding:0 1.5rem;">
  <div class="section-header">
    <h2 class="section-title"><?= __('home.popular_categories') ?></h2>
    <a href="browse.php?view=categories" class="view-all"><?= __('home.view_all_arrow') ?></a>
  </div>
  <div class="space-y-12">
    <?php foreach($categories as $cat): ?>
      <div class="category-section">
        <div class="section-header">
          <div class="flex items-center gap-3">
            <span class="text-primary">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2l2 2h8a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 11v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6"></path></svg>
            </span>
            <h2 class="section-title"><?= htmlspecialchars($cat['name']) ?></h2>
          </div>
          <a href="browse.php?category=<?= $cat['id'] ?>" class="view-all"><?= __('home.see_more_in', ['category' => htmlspecialchars($cat['name'])]) ?></a>
        </div>
        <div class="scroll-row">
          <?php foreach($cat['products'] as $prod): ?>
            <?php include __DIR__ . '/../includes/product_card_template.php'; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</div>

</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
