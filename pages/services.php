<?php
// pages/services.php
require_once '../includes/bootstrap.php';

$search   = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$pricingModel = $_GET['pricing'] ?? '';
$locationFilter = $_GET['location'] ?? '';
$sellerId = (int)($_GET['seller'] ?? 0);
$sort     = $_GET['sort'] ?? 'newest';
$page     = max(1, (int)($_GET['page'] ?? 1));

$sellerInfo = null;
if ($sellerId > 0) {
    $sStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $sStmt->execute([$sellerId]);
    $sellerInfo = $sStmt->fetch();
}

$params    = [];
$filterSql = '';

if ($search !== '') {
    $filterSql .= productSearchFilterSql($search, $params);
}
if ($category) {
    $filterSql .= " AND (p.category_id = ? OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?))";
    $params[] = $category;
    $params[] = $category;
}
if (in_array($pricingModel, ['flat', 'hourly'])) {
    $filterSql .= " AND p.pricing_model = ?";
    $params[] = $pricingModel;
}
if ($locationFilter !== '' && isValidLocationTown($locationFilter)) {
    $filterSql .= " AND p.location_town = ?";
    $params[] = $locationFilter;
}
if ($sellerId > 0) {
    $filterSql .= " AND p.user_id = ?";
    $params[] = $sellerId;
}

$fromSql = " FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        WHERE p.status = 'active' AND p.listing_type = 'service'" . $filterSql;

$countStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.id)" . $fromSql);
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();

$orderBy = match($sort) {
    'price_asc'  => "p.price ASC",
    'price_desc' => "p.price DESC",
    default      => "p.created_at DESC",
};

$sql = "SELECT p.*, c.name as category_name, u.username as seller_name, u.avatar as seller_avatar, i.image_path,
        p.delivery_days, p.availability_status,
        COALESCE(r.avg_rating, 0) as avg_rating, COALESCE(r.review_count, 0) as review_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
        LEFT JOIN (
            SELECT product_id, ROUND(AVG(rating),1) as avg_rating, COUNT(*) as review_count
            FROM ratings GROUP BY product_id
        ) r ON r.product_id = p.id
        WHERE p.status = 'active' AND p.listing_type = 'service'" . $filterSql .
       " ORDER BY " . $orderBy .
       " LIMIT " . ITEMS_PER_PAGE . " OFFSET " . getOffset($page);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Fetch service-type categories only
$serviceCategories = $pdo->query("
    SELECT c.* FROM categories c
    WHERE c.type = 'service'
    ORDER BY c.name ASC
")->fetchAll();

$paginationQuery = $_GET;
unset($paginationQuery['page']);
$paginationBase = 'services.php';
if (!empty($paginationQuery)) {
    $paginationBase .= '?' . http_build_query($paginationQuery);
}

$pageTitle       = __('services.page_title');
$pageDescription = __('services.hero_subtitle');
include '../includes/header.php';
?>

<div class="min-h-screen pt-24 pb-16 relative">

    <div class="container">
        <!-- Services Header -->
        <div class="mb-10 flex justify-between items-end gap-6 flex-wrap">
            <div class="text-left">
                <h1 class="page-hero-title mb-2 text-main"><?= __('services.hero_title') ?></h1>
                <p class="page-subtitle"><?= __('services.hero_subtitle') ?></p>
            </div>
            
            <?php if (isLoggedIn()): ?>
            <a href="<?php echo BASE_URL; ?>pages/create_listing.php?type=service" class="btn btn--service" style="padding: 0.75rem 1.5rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <?= __('services.offer_service') ?>
            </a>
            <?php endif; ?>
        </div>

        <?php if ($sellerInfo): ?>
        <div class="mb-6 flex items-center justify-between gap-4 p-4 rounded-xl" style="background: var(--service-light); border: 1px solid var(--service); color: var(--text-main);">
            <div class="flex items-center gap-2">
                <span>🛠️</span>
                <span>Browsing services by <strong>@<?= htmlspecialchars($sellerInfo['username']) ?></strong></span>
            </div>
            <a href="<?= BASE_URL ?>pages/services.php" class="btn btn-sm btn-secondary" style="font-size: 0.8rem; border-radius: var(--radius-full);">View all services ✕</a>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg-grid-cols-5 gap-8 items-start">

            <!-- Sidebar Filters -->
            <aside class="lg-col-span-1">
                <div class="glass-panel p-5 sticky-desktop" style="border-radius: var(--radius-lg); border: 1px solid var(--border-light);">
                    <div class="flex justify-between items-center mb-8 pb-4 border-b">
                        <h2 class="page-section-title mb-0"><?= __('services.filters_title') ?></h2>
                        <a href="services.php" class="text-muted small font-bold uppercase tracking-wider hover:text-primary"><?= __('services.clear_filters') ?></a>
                    </div>

                    <form method="GET" action="<?php echo BASE_URL; ?>pages/services.php">
                        <?php if ($search !== ''): ?>
                            <input type="hidden" name="q" value="<?php echo sanitize($search); ?>">
                        <?php endif; ?>
                        <?php if ($sort): ?>
                            <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <?php endif; ?>

                        <!-- Category -->
                        <div class="filter-block mb-8">
                            <div class="flex items-center gap-2 mb-3 text-main font-bold uppercase tracking-wider" style="font-size: 0.85rem;">
                                <span><?= __('services.category_label') ?></span>
                            </div>
                            <div class="relative">
                                <select name="category" class="w-full premium-input" style="padding: 0.75rem 1rem; background: var(--bg-surface); cursor: pointer;" onchange="this.form.submit()">
                                    <option value=""><?= __('services.all_categories') ?></option>
                                    <?php foreach ($serviceCategories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize(translateCategory($cat['name'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="filter-block mb-8">
                            <div class="flex items-center gap-2 mb-3 text-main font-bold uppercase tracking-wider" style="font-size: 0.85rem;">
                                <span><?= __('services.location_label') ?></span>
                            </div>
                            <div class="relative">
                                <select name="location" class="w-full premium-input" style="padding: 0.75rem 1rem; background: var(--bg-surface); cursor: pointer;" onchange="this.form.submit()">
                                    <option value=""><?= __('services.all_locations') ?></option>
                                    <option value="remote" <?php echo $locationFilter === 'remote' ? 'selected' : ''; ?>>🌐 <?= __('location.town.remote') ?></option>
                                    <?php foreach (locationTownSlugs() as $townSlug): ?>
                                        <?php if ($townSlug === 'remote' || $townSlug === 'other') continue; ?>
                                        <option value="<?php echo $townSlug; ?>" <?php echo $locationFilter === $townSlug ? 'selected' : ''; ?>>
                                            <?php echo formatLocationTown($townSlug); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Pricing Model -->
                        <div class="filter-block mb-8">
                            <div class="flex items-center gap-2 mb-3 text-main font-bold uppercase tracking-wider" style="font-size: 0.85rem;">
                                <span><?= __('services.pricing_label') ?></span>
                            </div>
                            <div class="relative">
                                <select name="pricing" class="w-full premium-input" style="padding: 0.75rem 1rem; background: var(--bg-surface); cursor: pointer;" onchange="this.form.submit()">
                                    <option value=""><?= __('services.all_pricing') ?></option>
                                    <option value="flat" <?php echo $pricingModel === 'flat' ? 'selected' : ''; ?>><?= __('services.flat_rate') ?></option>
                                    <option value="hourly" <?php echo $pricingModel === 'hourly' ? 'selected' : ''; ?>><?= __('services.hourly_rate') ?></option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn--service w-full shadow-md" style="padding: 0.8rem; font-weight: 600;"><?= __('services.apply_filters') ?></button>
                    </form>
                </div>
            </aside>

            <!-- Results -->
            <main class="lg-col-span-4">
                <div class="mb-8 browse-results-header">
                    <!-- Item Count -->
                    <div class="item-count-badge" style="background: var(--bg-card); color: var(--text-main); padding: 0.45rem 1.25rem; border-radius: var(--radius-md); font-weight: 600; font-size: 0.9rem; border: 1px solid var(--border-light); flex-shrink: 0; white-space: nowrap;">
                        <?= $totalItems === 1 ? __('services.count_single', ['count' => 1]) : __('services.count_plural', ['count' => $totalItems]) ?>
                    </div>

                    <!-- Search Bar -->
                    <form method="GET" action="" class="search-bar search-form-el mb-0" style="height: 46px; position: relative; z-index: 50; flex: 1; max-width: 500px;">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                            <circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        <?php if ($category): ?><input type="hidden" name="category" value="<?php echo sanitize($category); ?>"><?php endif; ?>
                        <?php if ($pricingModel): ?><input type="hidden" name="pricing" value="<?php echo sanitize($pricingModel); ?>"><?php endif; ?>
                        <?php if ($locationFilter): ?><input type="hidden" name="location" value="<?php echo sanitize($locationFilter); ?>"><?php endif; ?>
                        <?php if ($sort): ?><input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>"><?php endif; ?>
                        <input type="text" name="q" value="<?php echo sanitize($search); ?>" placeholder="<?= __('services.search_placeholder') ?>" class="search-input">
                        <button type="submit" class="search-btn btn--service"><?= __('services.search_btn') ?></button>
                    </form>

                    <!-- Sort -->
                    <div class="flex items-center gap-3 flex-shrink-0 sort-dropdown-el">
                        <span class="text-muted small font-bold uppercase tracking-wider" style="font-size: 0.8rem; white-space: nowrap;"><?= __('services.sort_label') ?></span>
                        <form method="GET" action="services.php" id="sort-form" class="mb-0">
                            <?php if ($search): ?><input type="hidden" name="q" value="<?php echo sanitize($search); ?>"><?php endif; ?>
                            <?php if ($category): ?><input type="hidden" name="category" value="<?php echo sanitize($category); ?>"><?php endif; ?>
                            <?php if ($pricingModel): ?><input type="hidden" name="pricing" value="<?php echo sanitize($pricingModel); ?>"><?php endif; ?>
                            <?php if ($locationFilter): ?><input type="hidden" name="location" value="<?php echo sanitize($locationFilter); ?>"><?php endif; ?>
                            <select name="sort" class="premium-input" style="padding: 0.5rem 0.75rem; font-size: 0.9rem; min-width: 160px; border-radius: var(--radius-lg); background: var(--bg-main); border: 1px solid var(--border-light); cursor: pointer;" onchange="this.form.submit()">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                            </select>
                        </form>
                    </div>
                </div>

                            <?php if (empty($services)): ?>
                    <div class="glass-panel p-16 text-center shadow-sm" style="border-radius: var(--radius-lg); border: 2px dashed var(--border-light); background: var(--bg-card);">
                        <div class="mb-4 flex justify-center" style="color: var(--text-muted); opacity: 0.5;">
                            <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
                        </div>
                        <h3 class="empty-state-title mb-2"><?= __('services.no_services') ?></h3>
                        <p class="page-subtitle max-w-md mx-auto"><?= __('services.no_services_desc') ?></p>
                        <?php if (isLoggedIn()): ?>
                        <a href="<?php echo BASE_URL; ?>pages/create_listing.php?type=service" class="btn btn--service mt-6 shadow-sm" style="padding: 0.75rem 1.5rem;">
                            <?= __('services.offer_service') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <!-- Category pills strip -->
                    <?php if (!empty($serviceCategories)): ?>
                    <div style="display:flex; gap:0.5rem; overflow-x:auto; padding-bottom:0.5rem; margin-bottom:1.5rem; scrollbar-width:none;" class="category-pill-strip">
                        <a href="services.php<?= $search ? '?q='.urlencode($search) : '' ?>" style="flex-shrink:0; padding:0.45rem 1rem; border-radius:99px; font-size:0.85rem; font-weight:700; text-decoration:none; white-space:nowrap; border:1.5px solid <?= !$category ? 'var(--service)' : 'var(--border-light)' ?>; background:<?= !$category ? 'var(--service-light)' : 'var(--bg-surface)' ?>; color:<?= !$category ? 'var(--service)' : 'var(--text-muted)' ?>; transition:all 0.18s;">All</a>
                        <?php foreach ($serviceCategories as $cat): ?>
                        <a href="services.php?category=<?= (int)$cat['id'] ?><?= $search ? '&q='.urlencode($search) : '' ?>" style="flex-shrink:0; padding:0.45rem 1rem; border-radius:99px; font-size:0.85rem; font-weight:700; text-decoration:none; white-space:nowrap; border:1.5px solid <?= $category == $cat['id'] ? 'var(--service)' : 'var(--border-light)' ?>; background:<?= $category == $cat['id'] ? 'var(--service-light)' : 'var(--bg-surface)' ?>; color:<?= $category == $cat['id'] ? 'var(--service)' : 'var(--text-muted)' ?>; transition:all 0.18s;"><?= sanitize(translateCategory($cat['name'])) ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md-grid-cols-2 xl-grid-cols-3 gap-6">
                        <?php foreach ($services as $prod): ?>
                        <?php
                        $sAvail  = $prod['availability_status'] ?? 'available';
                        $sAvailColors = ['available'=>'#22c55e','busy'=>'#f59e0b','unavailable'=>'#ef4444'];
                        $sAvailColor = $sAvailColors[$sAvail] ?? '#22c55e';
                        $sDays   = (int)($prod['delivery_days'] ?? 0);
                        $sDayLabel = match(true) {
                            $sDays === 1 => 'Same day',
                            $sDays > 1 && $sDays < 7 => $sDays . 'd',
                            $sDays === 7 => '1 wk',
                            $sDays === 14 => '2 wks',
                            $sDays === 30 => '1 mo',
                            $sDays > 0 => $sDays . 'd',
                            default => null,
                        };
                        $sAvgRating  = (float)($prod['avg_rating'] ?? 0);
                        $sReviewCount = (int)($prod['review_count'] ?? 0);
                        ?>
                        <div class="card card-hover flex flex-col" style="position: relative; border-radius: var(--radius-lg); border: 1px solid var(--border-light); background: var(--bg-surface); overflow: hidden; transition: var(--transition);">
                            <a href="<?php echo BASE_URL; ?>pages/product.php?id=<?php echo $prod['id']; ?>" style="text-decoration: none; display: flex; flex-direction: column; height: 100%;">
                                <!-- Image -->
                                <div class="product-card-image-wrap" style="border-radius: 0; margin-bottom: 0; position: relative;">
                                    <img src="<?php echo getProductImage($prod['image_path'] ?? null); ?>" alt="<?php echo sanitize($prod['title']); ?>">
                                    <!-- Top-left: availability + delivery -->
                                    <div style="position:absolute; top:0.75rem; left:0.75rem; display:flex; gap:0.4rem; align-items:center;">
                                        <span style="display:flex; align-items:center; gap:0.35rem; background:rgba(0,0,0,0.55); backdrop-filter:blur(6px); color:white; padding:0.25rem 0.6rem; border-radius:99px; font-size:0.72rem; font-weight:700;">
                                            <span style="width:7px;height:7px;border-radius:50%;background:<?= $sAvailColor ?>;flex-shrink:0;"></span>
                                            <?= ucfirst($sAvail) ?>
                                        </span>
                                        <?php if ($sDayLabel): ?>
                                        <span style="background:rgba(0,0,0,0.55); backdrop-filter:blur(6px); color:white; padding:0.25rem 0.6rem; border-radius:99px; font-size:0.72rem; font-weight:700;">
                                            <?= $sDayLabel ?> delivery
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Top-right: pricing badge -->
                                    <div style="position:absolute; top:0.75rem; right:0.75rem;">
                                        <span style="background: var(--service); color: white; padding: 0.25rem 0.6rem; border-radius: 99px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                                            <?php echo ($prod['pricing_model'] ?? 'flat') === 'hourly' ? __('product.hourly_badge') : __('product.service_badge'); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Info -->
                                <div class="flex flex-col flex-grow" style="padding: 1rem 1.25rem 1.25rem;">
                                    <h4 class="mb-2 text-main product-card-title" style="font-size:0.98rem; line-height:1.35;"><?php echo sanitize($prod['title']); ?></h4>

                                    <!-- Star rating -->
                                    <?php if ($sReviewCount > 0): ?>
                                    <div style="display:flex; align-items:center; gap:0.3rem; margin-bottom:0.6rem; font-size:0.82rem; font-weight:700;">
                                        <span style="color:#f59e0b;">&#9733;</span>
                                        <span style="color:var(--text-main);"><?= number_format($sAvgRating, 1) ?></span>
                                        <span style="color:var(--text-muted); font-weight:500;">(<?= $sReviewCount ?>)</span>
                                    </div>
                                    <?php endif; ?>

                                    <div class="mt-auto">
                                        <!-- Price -->
                                        <div style="margin-bottom:0.75rem;">
                                            <span class="product-card-price__now product-card-price__now--regular">
                                                <?php echo formatPrice($prod['price'], productCurrencyCode($prod)); ?>
                                                <?php if (($prod['pricing_model'] ?? 'flat') === 'hourly'): ?>
                                                    <small style="font-size:0.75em; color:var(--text-muted); font-weight:500;">/hr</small>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                        <!-- Seller row -->
                                        <div style="display:flex; align-items:center; gap:0.5rem; padding-top:0.75rem; border-top:1px solid var(--border-light);">
                                            <img src="<?= avatarUrl($prod['seller_avatar'] ?? null) ?>" alt="@<?= sanitize($prod['seller_name']) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1.5px solid var(--border-light);">
                                            <span style="font-size:0.82rem; font-weight:700; color:var(--text-muted);">@<?php echo sanitize($prod['seller_name']); ?></span>
                                            <?php if (!empty($prod['location_town']) && isValidLocationTown($prod['location_town'])): ?>
                                            <span style="margin-left:auto; font-size:0.75rem; font-weight:600; color:var(--text-muted); background:var(--bg-main); border:1px solid var(--border-light); padding:0.15rem 0.5rem; border-radius:99px; white-space:nowrap;"><?= sanitize(formatLocationTown($prod['location_town'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php echo paginationLinks($totalItems, $page, $paginationBase); ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
