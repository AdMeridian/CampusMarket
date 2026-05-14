<?php
session_start();
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

// Fetch categories for the grid
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    GROUP BY c.id
    ORDER BY product_count DESC
    LIMIT 12
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>CampusMarket – Buy &amp; Sell on Campus</title>
<meta name="description" content="The student marketplace to buy, sell, and connect on campus.">

</head>
<body>

<nav aria-label="Catalog" style="padding:.5rem 1rem;font-size:.95rem;">
  <a href="browse.php">Browse</a> ·
  <a href="search.php">Search</a> ·
  <a href="create_listing.php">Create listing</a> ·
  <a href="wishlist.php">Wishlist</a>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-inner" style="display:block;text-align:center;max-width:700px;margin:0 auto;">
    <div class="hero-text">
      <p style="font-size:.85rem;opacity:.7;font-weight:600;letter-spacing:.08em;text-transform:uppercase;margin-bottom:.5rem;">Buy. Sell. Connect.</p>
      <h1>Everything students need, all in one place.</h1>
      <p>Find great deals on books, gadgets, furniture, notes and more — from students, for students.</p>
      <a href="browse.php" class="btn-hero">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        Start Browsing
      </a>
    </div>
  </div>
</section>

<!-- LATEST LISTINGS -->
<div class="section">
  <div class="section-header">
    <h2 class="section-title">Latest Listings</h2>
    <a href="browse.php" class="view-all">View all →</a>
  </div>
  <div class="products-grid">
    <?php foreach($latest_products as $prod): ?>
      <?php include __DIR__ . '/../includes/product_card_template.php'; ?>
    <?php endforeach; ?>
  </div>
</div>

<!-- POPULAR CATEGORIES -->
<div class="section" style="background:#fff;border-top:1px solid var(--gray-200);border-bottom:1px solid var(--gray-200);max-width:100%;padding:2.5rem 0;">
<div style="max-width:1280px;margin:0 auto;padding:0 1.5rem;">
  <div class="section-header">
    <h2 class="section-title">Popular Categories</h2>
    <a href="browse.php?view=categories" class="view-all">View all →</a>
  </div>
  <div class="categories-grid">
    <?php foreach($categories as $cat): ?>
    <a href="browse.php?category=<?= $cat['id'] ?>" class="cat-card">
      <span class="cat-icon"><?= $cat['icon'] ?? '📁' ?></span>
      <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
      <div class="cat-count"><?= $cat['product_count'] ?> items</div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
</div>

<style>
  .product-card { position: relative; }
  .heart-btn { position: absolute; top: 8px; right: 8px; z-index: 2; width: 36px; height: 36px; border: none; border-radius: var(--radius-lg); background: rgba(255,255,255,.95); cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(0,0,0,.12); color: #64748b; }
  .heart-btn.active { color: #dc2626; }
</style>
<script src="../public/js/wishlist.js"></script>
<script>updateWishlistUI();</script>
</body>
</html>
