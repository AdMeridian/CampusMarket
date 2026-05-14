<?php
require_once '../includes/bootstrap.php';

$search = $_GET['q'] ?? '';
$category = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';
$minPrice = $_GET['min_price'] ?? '';
$maxPrice = $_GET['max_price'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Filters and sorting logic
$params = [];
$sql = "SELECT p.*, c.name as category_name, u.username as seller_name, i.image_path,
               (p.price * (100 - p.discount_percent) / 100) AS effective_price
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        JOIN users u ON p.user_id = u.id 
        LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
        WHERE p.status = 'active'";

if ($search !== '') {
    $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}
if ($condition) {
    $sql .= " AND p.condition = ?";
    $params[] = $condition;
}
if ($minPrice) {
    $sql .= " AND (p.price * (100 - p.discount_percent) / 100) >= ?";
    $params[] = $minPrice;
}
if ($maxPrice) {
    $sql .= " AND (p.price * (100 - p.discount_percent) / 100) <= ?";
    $params[] = $maxPrice;
}

$sql .= " ORDER BY p.is_featured DESC, ";
switch ($sort) {
    case 'price_asc': $sql .= "effective_price ASC"; break;
    case 'price_desc': $sql .= "effective_price DESC"; break;
    case 'condition_best': 
        $sql .= "CASE p.condition 
                    WHEN 'new' THEN 1 
                    WHEN 'like_new' THEN 2 
                    WHEN 'used' THEN 3 
                    WHEN 'poor' THEN 4 
                    ELSE 5 END ASC"; 
        break;
    default: $sql .= "p.created_at DESC"; break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$pageTitle = "Browse Marketplace";
include '../includes/header.php';
?>

<div class="min-h-screen pt-8 pb-16 relative">

    <div class="container">
        <!-- Browse Header -->
        <div class="mb-10 flex justify-between items-end gap-6">
            <div class="text-left">
                <h1 class="font-bold text-4xl mb-2 gradient-text" style="background: linear-gradient(135deg, var(--text-main), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Discover Great Finds</h1>
                <p class="text-muted text-lg">Browse items from students around your campus</p>
            </div>
            
            <div class="flex items-center gap-2 text-muted font-medium">
                <span class="w-2.5 h-2.5 rounded-sm bg-secondary animate-pulse"></span>
                Live Marketplace
            </div>
        </div>

        <div class="grid grid-cols-1 lg-grid-cols-5 gap-8 items-start">
            
            <!-- Sidebar Filters -->
            <aside class="lg-col-span-1">
                <div id="filter-sidebar" class="glass-panel p-5 sticky-desktop" style="border-radius: var(--radius-lg); border: 1px solid var(--border-light);">
                    <div class="flex justify-between items-center mb-8 pb-4 border-b">
                        <h2 class="mb-0" style="font-size: 1.25rem;">Filters</h2>
                        <a href="browse.php" class="text-muted small font-bold uppercase tracking-wider hover:text-primary">Clear</a>
                    </div>

                    <form method="GET" action="<?php echo BASE_URL; ?>pages/browse.php">
                        <?php if($search !== ''): ?>
                            <input type="hidden" name="q" value="<?php echo sanitize($search); ?>">
                        <?php endif; ?>
                        <?php if($sort): ?>
                            <input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>">
                        <?php endif; ?>
                        
                        <!-- Category Block -->
                        <div class="filter-block mb-8">
                            <div class="flex items-center gap-2 mb-3 text-main font-bold uppercase tracking-wider" style="font-size: 0.85rem;">
                                <span>Category</span>
                            </div>
                            <div class="relative">
                                <select name="category" class="w-full premium-input" style="padding: 0.75rem 1rem; background: var(--bg-surface); cursor: pointer;" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Price Block -->
                        <div class="filter-block mb-8">
                            <div class="flex items-center gap-2 mb-3 text-main font-bold uppercase tracking-wider" style="font-size: 0.85rem;">
                                <span>Price Range</span>
                            </div>
                            <div class="flex gap-2">
                                <input type="number" name="min_price" placeholder="Min" value="<?php echo sanitize($minPrice); ?>" class="w-full premium-input" style="padding: 0.6rem; font-size: 0.9rem;">
                                <input type="number" name="max_price" placeholder="Max" value="<?php echo sanitize($maxPrice); ?>" class="w-full premium-input" style="padding: 0.6rem; font-size: 0.9rem;">
                            </div>
                        </div>

                        <!-- Condition Block -->
                        <div class="filter-block mb-8">
                            <div class="flex items-center gap-2 mb-3 text-main font-bold uppercase tracking-wider" style="font-size: 0.85rem;">
                                <span>Condition</span>
                            </div>
                            <div class="relative">
                                <select name="condition" class="w-full premium-input" style="padding: 0.75rem 1rem; background: var(--bg-surface); cursor: pointer;" onchange="this.form.submit()">
                                    <?php 
                                    $conditions = ['' => 'Any Condition', 'new' => 'New', 'like_new' => 'Like New', 'used' => 'Used', 'poor' => 'Poor'];
                                    foreach ($conditions as $val => $label): 
                                    ?>
                                        <option value="<?php echo $val; ?>" <?php echo $condition == $val ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-full shadow-md" style="padding: 0.8rem; border-radius: var(--radius-md);">Apply Filters</button>
                    </form>
                </div>
            </aside>

            <!-- Results -->
            <main class="lg-col-span-4">
                <div class="mb-8 flex items-center justify-between gap-6" style="background: var(--bg-surface); padding: 1.1rem 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--border-light); box-shadow: var(--shadow-sm);">
                    <!-- Item Count -->
                    <div style="background: linear-gradient(135deg, var(--primary), var(--secondary)); color: white; padding: 0.4rem 1.25rem; border-radius: var(--radius-lg); font-weight: 800; font-size: 0.9rem; box-shadow: 0 4px 12px rgba(99,102,241,0.2); flex-shrink: 0;">
                        <?php echo count($products); ?> Items
                    </div>

                    <!-- Search Bar (In Between) -->
                    <form method="GET" action="" class="search-bar mb-0" style="flex: 1; max-width: 500px; height: 46px; position: relative; z-index: 50;">
                        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                        
                        <?php if($category): ?><input type="hidden" name="category" value="<?php echo sanitize($category); ?>"><?php endif; ?>
                        <?php if($condition): ?><input type="hidden" name="condition" value="<?php echo sanitize($condition); ?>"><?php endif; ?>
                        <?php if($minPrice !== ''): ?><input type="hidden" name="min_price" value="<?php echo sanitize($minPrice); ?>"><?php endif; ?>
                        <?php if($maxPrice !== ''): ?><input type="hidden" name="max_price" value="<?php echo sanitize($maxPrice); ?>"><?php endif; ?>
                        <?php if($sort): ?><input type="hidden" name="sort" value="<?php echo sanitize($sort); ?>"><?php endif; ?>
                        
                        <input type="text" name="q" value="<?php echo sanitize($search); ?>" placeholder="Search items..." class="search-input">
                        <button type="submit" class="search-btn" style="height: 34px; padding: 0 1.25rem;">Find</button>
                    </form>

                    <!-- Sort Dropdown -->
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="text-muted small font-bold uppercase tracking-wider" style="font-size: 0.8rem;">Sort By</span>
                        <form method="GET" action="browse.php" id="sort-form" class="mb-0">
                            <?php if($search): ?><input type="hidden" name="q" value="<?php echo sanitize($search); ?>"><?php endif; ?>
                            <?php if($category): ?><input type="hidden" name="category" value="<?php echo sanitize($category); ?>"><?php endif; ?>
                            <?php if($condition): ?><input type="hidden" name="condition" value="<?php echo sanitize($condition); ?>"><?php endif; ?>
                            <?php if($minPrice): ?><input type="hidden" name="min_price" value="<?php echo sanitize($minPrice); ?>"><?php endif; ?>
                            <?php if($maxPrice): ?><input type="hidden" name="max_price" value="<?php echo sanitize($maxPrice); ?>"><?php endif; ?>
                            
                            <select name="sort" class="premium-input" style="padding: 0.5rem 0.75rem; font-size: 0.9rem; min-width: 160px; border-radius: var(--radius-lg); background: var(--bg-main); border: 1px solid var(--border-light); cursor: pointer;" onchange="this.form.submit()">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="condition_best" <?php echo $sort == 'condition_best' ? 'selected' : ''; ?>>Condition: Best First</option>
                            </select>
                        </form>
                    </div>
                </div>

                <?php if($search): ?>
                    <div class="mb-6 px-2">
                        <span class="text-muted text-sm">Showing results for "<strong class="text-main"><?php echo sanitize($search); ?></strong>"</span>
                    </div>
                <?php endif; ?>

                <?php if (empty($products)): ?>
                    <div class="glass-panel p-16 text-center shadow-sm" style="border-radius: var(--radius-xl); border: 2px dashed rgba(0,0,0,0.05);">
                        <div class="mb-4 text-6xl opacity-30">🔍</div>
                        <h3 class="font-bold text-main text-2xl mb-2">No items found</h3>
                        <p class="text-muted text-lg max-w-md mx-auto">We couldn't find any items matching your current filters. Try adjusting your search criteria or clearing filters.</p>
                        <a href="browse.php" class="btn btn-primary mt-6 hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Clear All Filters</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md-grid-cols-2 xl-grid-cols-3 gap-6">
                        <?php foreach ($products as $prod): ?>
                            <?php include '../includes/product_card_template.php'; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<script>
    // Mobile Filter Toggle
    const filterBtn = document.getElementById('filter-toggle-btn');
    const filterSidebar = document.getElementById('filter-sidebar');
    const filterChevron = document.getElementById('filter-chevron');

    if (filterBtn && filterSidebar) {
        filterBtn.addEventListener('click', () => {
            const isHidden = filterSidebar.classList.contains('hidden');
            if (isHidden) {
                filterSidebar.classList.remove('hidden');
                filterChevron.style.transform = 'rotate(180deg)';
            } else {
                filterSidebar.classList.add('hidden');
                filterChevron.style.transform = 'rotate(0deg)';
            }
        });
    }
</script>

<?php include '../includes/footer.php'; ?>
