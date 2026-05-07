<?php
// pages/search.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/header.php';

$query = sanitize($_GET['q'] ?? '');
$categoryId = $_GET['category'] ?? '';
$pageTitle = "Search Results" . ($query ? ": " . $query : "");

$results = [];
if ($query !== '' || $categoryId !== '') {
    $sql = "SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = 1
            WHERE p.status = 'active'";
    $params = [];

    if ($query !== '') {
        $sql .= " AND (p.title LIKE ? OR p.description LIKE ?)";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }

    if ($categoryId !== '') {
        $sql .= " AND p.category_id = ?";
        $params[] = $categoryId;
    }

    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}
?>

<div class="container min-h-screen pt-12 pb-20 relative">
    <!-- Background Accents -->
    <div style="position: absolute; top: -5%; left: 10%; width: 500px; height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(99,102,241,0.06) 0%, rgba(255,255,255,0) 70%); z-index: -1;"></div>
    <div style="position: absolute; top: 20%; right: -5%; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(236,72,153,0.04) 0%, rgba(255,255,255,0) 70%); z-index: -1;"></div>

    <div class="mb-8 flex flex-col md:flex-row justify-between items-center gap-4 glass-panel p-6" style="border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
        <div class="text-center md:text-left">
            <h1 class="font-bold text-2xl mb-1 text-main">Search Results</h1>
            <p class="text-muted font-medium" style="font-size: 0.95rem;">Found <strong class="text-primary"><?php echo count($results); ?></strong> items <?php echo $query ? 'for "<strong class="text-main">'.sanitize($query).'</strong>"' : ''; ?></p>
        </div>
        
        <form action="<?php echo BASE_URL; ?>pages/search.php" method="GET" class="search-bar" style="flex: 1; max-width: 450px; height: 48px;">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Search items..." class="search-input">
            <button type="submit" class="search-btn">Search</button>
        </form>
    </div>

    <?php if (empty($results)): ?>
        <div class="glass-panel p-20 text-center shadow-sm relative overflow-hidden" style="border-radius: var(--radius-xl); border: 2px dashed rgba(0,0,0,0.05);">
            <div class="text-8xl mb-6 opacity-20" style="transform: rotate(-10deg);">🔦</div>
            <h3 class="font-bold text-main text-3xl mb-3">No items matched your search</h3>
            <p class="text-muted text-lg max-w-lg mx-auto mb-8">We couldn't find any listings matching "<strong class="text-primary"><?php echo $query; ?></strong>". Try using different keywords, checking for typos, or using broader terms.</p>
            <a href="<?php echo BASE_URL; ?>/pages/browse.php" class="btn btn-secondary shadow-md hover-scale" style="border-radius: var(--radius-full); padding: 0.8rem 2rem; font-weight: bold;">Browse All Items</a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($results as $prod): ?>
                <a href="product.php?id=<?php echo $prod['id']; ?>" class="glass-panel hover-scale relative" style="display: flex; flex-direction: column; overflow: hidden; border-radius: var(--radius-lg); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.5); text-decoration: none;">
                    <div style="height: 200px; background: #e2e8f0; position: relative; overflow: hidden;">
                        <?php if ($prod['image_path']): ?>
                            <img src="<?php echo BASE_URL; ?>/public/<?php echo $prod['image_path']; ?>" alt="<?php echo sanitize($prod['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #f1f5f9, #e2e8f0); color: #94a3b8;">
                                <svg class="w-12 h-12 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                <span class="text-sm font-medium">No Image Provided</span>
                            </div>
                        <?php endif; ?>
                        
                        <div style="position: absolute; top: 0.75rem; right: 0.75rem;">
                            <?php $badge = conditionBadge($prod['condition']); ?>
                            <span class="badge <?php echo $badge['class']; ?> shadow-sm" style="font-size: 0.75rem; padding: 0.25rem 0.6rem; backdrop-filter: blur(4px);"><?php echo $badge['label']; ?></span>
                        </div>
                    </div>
                    <div class="p-5 flex flex-col flex-grow bg-white">
                        <p class="text-primary font-bold small tracking-wider uppercase mb-1" style="font-size: 0.7rem;"><?php echo sanitize($prod['category_name']); ?></p>
                        <h4 class="mb-3 text-main font-bold" style="font-size: 1.1rem; line-height: 1.4; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo sanitize($prod['title']); ?></h4>
                        
                        <div class="flex justify-between items-center mt-auto pt-4 border-t border-gray-100">
                            <span style="font-weight: 800; color: var(--text-main); font-size: 1.05rem; font-family: 'Inter', sans-serif;"><?php echo renderProductPrice($prod); ?></span>
                            <div class="flex items-center gap-2">
                                <div style="min-width: 24px; min-height: 24px; border-radius: 50%; background: var(--primaryLight); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: bold; padding:0.2rem;"><?php echo strtoupper(substr($prod['seller_name'],0,2)); ?></div>
                                <span class="text-muted font-medium text-sm truncate" style="max-width: 80px;">@<?php echo sanitize($prod['seller_name']); ?></span>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
