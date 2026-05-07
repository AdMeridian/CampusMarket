<?php
session_start();
require_once __DIR__ . '/../config/constants.php';
include __DIR__ . '/../config/db.php';
include __DIR__ . '/../includes/functions.php';
include __DIR__ . '/../includes/data.php';

// Filter for products that have authentic local images (not placeholders)
$authentic_products = array_filter($products, function($p) {
    return strpos($p['img'], '../public/images/') === 0;
});

// Mix them up and take 6
shuffle($authentic_products);
$latest_products = array_slice($authentic_products, 0, 6);
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
    <?php foreach($latest_products as $p): ?>
    <div class="product-card" onclick="window.location.href='product.php?id=<?= $p['id'] ?>'">
      <img class="product-img" src="<?= $p['img'] ?>" alt="<?= htmlspecialchars($p['title']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
      <div class="product-img-placeholder" style="display:none">📦</div>
      <button class="heart-btn" type="button" id="heart-<?= $p['id'] ?>" onclick="event.stopPropagation();toggleWishlist(<?= $p['id'] ?>,<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>)" aria-label="Add to wishlist">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
      </button>
      <div class="product-body">
        <div class="product-title"><?= htmlspecialchars($p['title']) ?></div>
        <div class="product-category"><?= $p['category'] ?></div>
        <div class="product-price"><?= formatPrice($p['price']) ?></div>
      </div>
    </div>
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
    <a href="browse.php?category=<?= urlencode($cat['name']) ?>" class="cat-card">
      <span class="cat-icon"><?= $cat['icon'] ?></span>
      <div class="cat-name"><?= $cat['name'] ?></div>
      <div class="cat-count"><?= $cat['count'] ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
</div>

<style>
  .product-card { position: relative; }
  .heart-btn { position: absolute; top: 8px; right: 8px; z-index: 2; width: 36px; height: 36px; border: none; border-radius: 50%; background: rgba(255,255,255,.95); cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 4px rgba(0,0,0,.12); color: #64748b; }
  .heart-btn.active { color: #dc2626; }
</style>
<script src="../public/js/wishlist.js"></script>
<script>updateWishlistUI();</script>
</body>
</html>
