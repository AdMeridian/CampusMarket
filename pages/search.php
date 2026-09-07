<?php
// pages/search.php
require_once __DIR__ . '/../includes/bootstrap.php';

$query = sanitize($_GET['q'] ?? '');
$categoryId = $_GET['category'] ?? '';
$town = $_GET['town'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageTitle = __('search.page_title') . ($query ? ": " . $query : "");
$pageDescription = $query !== ''
    ? __('search.page_title') . ': ' . $query . ' — ' . __('seo.default_description')
    : __('seo.search_description');

$results = [];
$totalItems = 0;
$paginationBase = 'search.php';
$requestSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_wanted_request') {
    verifyCsrfToken();
    requireLogin();

    $requestTerm = trim(sanitize($_POST['search_term'] ?? $query));
    if ($requestTerm === '' || mb_strlen($requestTerm) > 200) {
        setFlash('error', 'Please enter a product name up to 200 characters.');
    } else {
        $duplicateStmt = $pdo->prepare("SELECT id FROM wanted_item_requests
            WHERE requester_id = ? AND LOWER(search_term) = LOWER(?)
              AND status = 'pending'
              AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
            LIMIT 1");
        $duplicateStmt->execute([currentUserId(), $requestTerm]);
        if ($duplicateStmt->fetchColumn()) {
            setFlash('error', 'You already have a pending suggestion for this item.');
        } else {
        $stmt = $pdo->prepare("INSERT INTO wanted_item_requests
            (requester_id, search_term, details, category_id, location_town, budget_max, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            currentUserId(),
            $requestTerm,
            null,
            null,
            null,
            null,
            date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        setFlash('success', 'Your suggestion was submitted for review.');
        $requestSubmitted = true;
        }
    }
}

if ($query !== '' || $categoryId !== '' || $town !== '') {
    $filterSql = '';
    $filterParams = [];

    if ($query !== '') {
        $filterSql .= productSearchFilterSql($query, $filterParams);
    }

    if ($categoryId !== '') {
        $filterSql .= " AND (p.category_id = ? OR EXISTS (SELECT 1 FROM product_categories pc WHERE pc.product_id = p.id AND pc.category_id = ?))";
        $filterParams[] = $categoryId;
        $filterParams[] = $categoryId;
    }

    $filterSql .= locationTownFilterSql('p', $town, $filterParams);

    $fromSql = " FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'active'" . $filterSql;

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT p.id)" . $fromSql);
    $countStmt->execute($filterParams);
    $totalItems = (int) $countStmt->fetchColumn();

    $queryParams = $filterParams;
    $orderBySql = ($query !== '') ? productSearchOrderBySql($query, $queryParams, 'p') : "ORDER BY p.created_at DESC";

    $sql = "SELECT p.*, c.name as category_name, i.image_path, u.username as seller_name
            FROM products p
            JOIN categories c ON p.category_id = c.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
            WHERE p.status = 'active'" . $filterSql . " " . $orderBySql . " LIMIT " . ITEMS_PER_PAGE . " OFFSET " . getOffset($page);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $results = $stmt->fetchAll();

    $paginationQuery = $_GET;
    unset($paginationQuery['page']);
    if (!empty($paginationQuery)) {
        $paginationBase .= '?' . http_build_query($paginationQuery);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container min-h-screen pt-24 pb-20 relative">
    <div class="mb-8 flex flex-col md:flex-row justify-between items-center gap-4 glass-panel p-6" style="border-radius: var(--radius-xl); box-shadow: var(--shadow-sm);">
        <div class="text-center md:text-left">
            <h1 class="page-section-title mb-1 text-main"><?= __('search.results_title') ?></h1>
            <p class="text-muted font-medium" style="font-size: 0.95rem;">
                <?php if ($query !== ''): ?>
                    <?= __('search.found_items_for', [
                        'count' => '<strong class="text-primary">' . $totalItems . '</strong>',
                        'query' => '<strong class="text-main">' . sanitize($query) . '</strong>'
                    ]) ?>
                <?php else: ?>
                    <?= __('search.found_items', [
                        'count' => '<strong class="text-primary">' . $totalItems . '</strong>'
                    ]) ?>
                <?php endif; ?>
            </p>
        </div>

        <form action="<?php echo BASE_URL; ?>pages/search.php" method="GET" class="search-bar" style="flex: 1; max-width: 450px; height: 48px;">
            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <?php if ($categoryId !== ''): ?>
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($categoryId); ?>">
            <?php endif; ?>
            <?php if ($town !== ''): ?>
                <input type="hidden" name="town" value="<?php echo htmlspecialchars($town); ?>">
            <?php endif; ?>
            <?php $placeholder = (isLoggedIn() && isAdmin()) ? __('nav.search_placeholder_admin') : __('nav.search_placeholder'); ?>
            <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="<?php echo $placeholder; ?>" class="search-input" autocomplete="off">
            <button type="submit" class="search-btn"><?= __('nav.search_btn') ?></button>
        </form>
    </div>

    <?php if (empty($results)): ?>
        <div class="glass-panel p-20 text-center shadow-sm relative overflow-hidden" style="border-radius: var(--radius-xl); border: 2px dashed rgba(0,0,0,0.05);">
            <div class="text-8xl mb-6 opacity-20" style="transform: rotate(-10deg);">🔦</div>
            <h3 class="empty-state-title mb-3"><?= __('search.no_items_matched') ?></h3>
            <p class="page-subtitle max-w-lg mx-auto mb-8"><?= __('search.no_items_desc', ['query' => '<strong class="text-primary">' . sanitize($query) . '</strong>']) ?></p>
            <div class="flex justify-center gap-4 flex-wrap">
                <a href="<?php echo BASE_URL; ?>/pages/browse.php" class="btn btn-secondary shadow-md hover-scale" style="border-radius: var(--radius-lg); padding: 0.8rem 2rem; font-weight: bold;"><?= __('search.browse_all_items') ?></a>
                <?php if ($query !== '' && !$requestSubmitted): ?>
                    <?php if (isLoggedIn()): ?>
                        <button type="button" class="btn btn-primary" style="border-radius: var(--radius-lg);" onclick="document.getElementById('suggest-item-dialog').showModal();">Suggest item</button>
                    <?php else: ?>
                        <a class="btn btn-primary" href="<?php echo BASE_URL; ?>pages/login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" style="border-radius: var(--radius-lg);">Log in to suggest</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php if ($query !== '' && isLoggedIn() && !$requestSubmitted): ?>
                <dialog id="suggest-item-dialog" class="suggest-item-dialog">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="grid gap-4">
                        <input type="hidden" name="action" value="submit_wanted_request">
                        <?php echo csrfTokenField(); ?>
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-primary font-bold" style="font-size: .72rem; text-transform: uppercase; letter-spacing: .08em; margin: 0 0 .35rem;">Buyer suggestion</p>
                                <h4 class="text-main font-bold" style="font-size: 1.35rem; margin: 0;">What item should we ask for?</h4>
                            </div>
                            <button type="button" aria-label="Close suggestion dialog" onclick="document.getElementById('suggest-item-dialog').close();" style="border: 0; background: transparent; font-size: 1.35rem; cursor: pointer;">&times;</button>
                        </div>
                        <label class="form-group">
                            <span class="form-label">Product name</span>
                            <input class="form-control" type="text" name="search_term" value="<?php echo htmlspecialchars($query); ?>" maxlength="200" required autofocus placeholder="e.g. graphing calculator">
                        </label>
                        <button type="submit" class="btn btn-primary" style="border-radius: var(--radius-md);">Send suggestion</button>
                    </form>
                </dialog>
                <style>
                    .suggest-item-dialog { position: fixed; inset: 50% auto auto 50%; transform: translate(-50%, -50%); width: min(92vw, 460px); max-height: 90vh; margin: 0; border: 1px solid var(--border-light); border-radius: var(--radius-xl); padding: 0; background: var(--bg-surface); color: var(--text-main); box-shadow: 0 24px 70px rgba(15, 23, 42, .22); }
                    .suggest-item-dialog::backdrop { background: rgba(15, 23, 42, .42); backdrop-filter: blur(3px); }
                    .suggest-item-dialog form { padding: 1.5rem; }
                    .suggest-item-dialog .form-control { box-sizing: border-box; }
                    .suggest-item-dialog .btn { width: 100%; justify-content: center; }
                </style>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($results as $prod): ?>
                <a href="product.php?id=<?php echo $prod['id']; ?>" class="glass-panel hover-scale relative" style="display: flex; flex-direction: column; overflow: hidden; border-radius: var(--radius-lg); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); border: 1px solid rgba(255,255,255,0.5); text-decoration: none;">
                    <div style="height: 200px; background: #e2e8f0; position: relative; overflow: hidden;">
                        <?php
                            $searchImg = getProductImage($prod['image_path'] ?? null);
                        ?>
                        <img src="<?php echo $searchImg; ?>" alt="<?php echo sanitize($prod['title']); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">

                        <div style="position: absolute; top: 0.75rem; right: 0.75rem;">
                            <?php $badge = conditionBadge($prod['condition']); ?>
                            <span class="badge <?php echo $badge['class']; ?> shadow-sm" style="font-size: 0.75rem; padding: 0.25rem 0.6rem; backdrop-filter: blur(4px);"><?php echo $badge['label']; ?></span>
                        </div>
                    </div>
                    <div class="p-5 flex flex-col flex-grow bg-white">
                        <?php
                            $searchMeta = [];
                            if (!empty($prod['location_town'])) {
                                $locText = formatLocationTown($prod['location_town'], $prod['custom_location'] ?? null);
                                if ($locText !== '' && $locText !== __('location.town.other')) {
                                    $searchMeta[] = $locText;
                                }
                            }
                            $searchMeta[] = sanitize($prod['category_name']);
                        ?>
                        <p class="text-primary font-bold small tracking-wider uppercase mb-1" style="font-size: 0.7rem;"><?php echo implode(' · ', $searchMeta); ?></p>
                        <h4 class="mb-3 text-main font-bold" style="font-size: 1.1rem; line-height: 1.4; flex-grow: 1; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo sanitize($prod['title']); ?></h4>

                        <div class="flex flex-col gap-2 mt-auto pt-4 border-t border-gray-100">
                            <div class="flex justify-between items-center">
                                <div class="flex flex-col">
                                    <span style="font-weight: 800; color: var(--text-main); font-size: 1.05rem; font-family: 'Inter', sans-serif;"><?php echo renderProductPrice($prod); ?></span>
                                    <span class="text-muted" style="font-size: 0.7rem; opacity: 0.7;">Listed <?php echo timeAgo($prod['created_at']); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div style="min-width: 24px; min-height: 24px; border-radius: var(--radius-md); background: var(--primaryLight); color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 0.6rem; font-weight: bold; padding:0.2rem;"><?php echo strtoupper(substr($prod['seller_name'],0,2)); ?></div>
                                    <span class="text-muted font-medium text-sm truncate" style="max-width: 80px;">@<?php echo sanitize($prod['seller_name']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php echo paginationLinks($totalItems, $page, $paginationBase); ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
