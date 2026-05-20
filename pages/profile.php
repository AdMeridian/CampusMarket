<?php
// pages/profile.php
require_once '../includes/bootstrap.php';

// Decide which profile to show
$viewId = isset($_GET['id']) ? (int)$_GET['id'] : (int)(currentUserId() ?? 0);

if ($viewId <= 0) {
    requireLogin();
    $viewId = (int)currentUserId();
}

// Admins viewing their own profile → redirect to admin panel
// Admins can still view other users' profiles for moderation purposes
if (isAdmin() && $viewId === (int)currentUserId()) {
    redirect(BASE_URL . 'admin/index.php');
}

// Fetch User
$stmt = $pdo->prepare("SELECT id, username, email, role, phone, avatar, created_at FROM users WHERE id = :id");
$stmt->execute([':id' => $viewId]);
$user = $stmt->fetch();

if (!$user) {
    include '../includes/header.php';
    echo '<div class="container mt-12 text-center text-muted"><div class="mb-4 opacity-50 flex justify-center"><svg style="width: 64px; height: 64px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M16 16s-1.5-2-4-2-4 2-4 2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg></div><h2>User not found</h2><p>This user does not exist or has been deleted.</p><a href="' . BASE_URL . '/" class="btn btn-primary mt-4 hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Back Home</a></div>';
    include '../includes/footer.php';
    exit;
}

$isSelf  = isLoggedIn() && (int)currentUserId() === (int)$user['id'];
$rating  = getSellerRating($pdo, (int)$user['id']);
$trust   = getSellerTrustScore($pdo, (int)$user['id']);
$pageTitle = sanitize($user['username']) . "'s Profile";
$activeTab = ($_GET['tab'] ?? 'listings') === 'about' ? 'about' : 'listings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSelf && isset($_POST['action'], $_POST['product_id'])) {
    verifyCsrfToken();
    $action = sanitize($_POST['action']);
    $productId = (int)($_POST['product_id'] ?? 0);

    $ownStmt = $pdo->prepare("SELECT * FROM products WHERE id = :pid AND user_id = :uid");
    $ownStmt->execute([':pid' => $productId, ':uid' => $viewId]);
    $ownedProduct = $ownStmt->fetch();

    if (!$ownedProduct) {
        setFlash('error', 'Listing not found.');
    } else {
        if ($action === 'set_discount') {
            $discountPercent = (int)($_POST['discount_percent'] ?? 0);
            if ($discountPercent < 0 || $discountPercent > LISTING_DISCOUNT_MAX_PERCENT) {
                setFlash('error', 'Discount must be between 0 and ' . LISTING_DISCOUNT_MAX_PERCENT . ' percent.');
            } else {
                $upd = $pdo->prepare("UPDATE products SET discount_percent = :dp, discount_set_at = NOW() WHERE id = :pid");
                $upd->execute([':dp' => $discountPercent, ':pid' => $productId]);
                setFlash('success', $discountPercent > 0 ? 'Discount updated.' : 'Discount removed.');
            }
        } elseif ($action === 'update_price') {
            $newPrice = (float)($_POST['new_price'] ?? 0);
            if ($newPrice <= 0) {
                setFlash('error', 'Price must be greater than zero.');
            } else {
                $upd = $pdo->prepare("UPDATE products SET price = :price, updated_at = NOW() WHERE id = :pid");
                $upd->execute([':price' => $newPrice, ':pid' => $productId]);
                setFlash('success', 'Price updated successfully.');
            }
        } elseif ($action === 'delete_listing') {
            $upd = $pdo->prepare("UPDATE products SET status = 'deleted', deleted_at = NOW(), updated_at = NOW() WHERE id = :pid");
            if ($upd->execute([':pid' => $productId])) {
                setFlash('success', 'Listing moved to Recycle Bin.');
            }
        }
    }
    redirect(BASE_URL . 'pages/profile.php?id=' . $viewId . '#listings');
}

// Fetch listings count for the stat pill
$listingCountStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE user_id = :uid AND status = 'active'");
$listingCountStmt->execute([':uid' => $viewId]);
$listingCount = (int)$listingCountStmt->fetchColumn();

// Fetch all active listings
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name, i.image_path
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images i ON p.id = i.product_id AND i.is_primary = TRUE
    WHERE p.user_id = :uid AND p.status = 'active'
    ORDER BY p.created_at DESC
");
$stmt->execute([':uid' => $viewId]);
$userProducts = $stmt->fetchAll();

// Fetch sold items (publicly visible on profile to show seller history)
$soldItems = $pdo->prepare("
    SELECT p.id, p.title, p.price, p.discount_percent,
           pi.image_path,
           dc.seller_confirmed_at
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = TRUE
    LEFT JOIN deal_confirmations dc ON dc.product_id = p.id AND dc.seller_id = p.user_id AND dc.status = 'completed'
    WHERE p.user_id = :uid AND p.status = 'sold'
    ORDER BY COALESCE(dc.seller_confirmed_at, p.updated_at, p.created_at) DESC
");
$soldItems->execute([':uid' => $viewId]);
$soldProducts = $soldItems->fetchAll();

include '../includes/header.php';
?>

<style>
/* ── Profile Page Styles ─────────────────────────────── */

.profile-hero {
    background: var(--primary);
    padding: calc(75px + 2.5rem) 0 0;
    margin-bottom: 0;
    position: relative;
    overflow: hidden;
}

.profile-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Crect x='26' y='26' width='8' height='8'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}

.profile-hero-inner {
    max-width: var(--container-max);
    margin: 0 auto;
    padding: 0 1.5rem;
    position: relative;
    z-index: 1;
}

.profile-hero-body {
    display: flex;
    align-items: flex-end;
    gap: 2rem;
    flex-wrap: wrap;
    padding-bottom: 0;
}

.profile-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.profile-avatar {
    width: 130px;
    height: 130px;
    border-radius: var(--radius-xl);
    object-fit: cover;
    border: 4px solid rgba(255,255,255,0.9);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    display: block;
    background: #e2e8f0;
}

.profile-admin-badge {
    position: absolute;
    bottom: 4px;
    right: 4px;
    background: #f43f5e;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.55rem;
    border-radius: var(--radius-lg);
    border: 2px solid white;
}

.profile-hero-meta {
    flex: 1;
    min-width: 200px;
    padding-bottom: 1.25rem;
}

.profile-username {
    font-family: 'Outfit', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    margin: 0 0 0.25rem;
    line-height: 1.2;
}

.profile-since {
    color: rgba(255,255,255,0.7);
    font-size: 0.9rem;
    margin: 0 0 0.75rem;
}

.profile-stars {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
}

.profile-stars .stars {
    color: #fbbf24;
    font-size: 1.05rem;
    letter-spacing: 0.05em;
}

.profile-hero-actions {
    margin-left: auto;
    display: flex;
    align-items: flex-end;
    gap: 0.75rem;
    padding-bottom: 1.25rem;
    flex-shrink: 0;
}

/* Tab bar sits at the bottom of the hero */
.profile-tabs {
    display: flex;
    gap: 0;
    margin-top: 1.5rem;
    border-bottom: none;
}

.profile-tab {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255,255,255,0.65);
    border-bottom: 3px solid transparent;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.profile-tab.active,
.profile-tab:hover {
    color: #fff;
    border-bottom-color: #fff;
    background: rgba(255,255,255,0.07);
}

.profile-tab .tab-count {
    background: rgba(255,255,255,0.2);
    color: #fff;
    font-size: 0.75rem;
    padding: 0.1rem 0.5rem;
    border-radius: var(--radius-lg);
    font-weight: 700;
}

/* ── Profile Body ─────────────────────────────────────── */

.profile-body {
    max-width: var(--container-max);
    margin: 2.5rem auto;
    padding: 0 1.5rem;
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2.5rem;
    align-items: start;
}

@media (max-width: 900px) {
    .profile-body { grid-template-columns: 1fr; }
    .profile-hero-body { flex-direction: column; align-items: flex-start; }
    .profile-hero-actions { margin-left: 0; }
}

@media (min-width: 901px) {
    .profile-sidebar {
        position: sticky;
        top: 100px;
    }
}

/* ── Sidebar Card ─────────────────────────────────────── */

.profile-sidebar .card {
    overflow: visible;
}

.profile-stat-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 1.5rem;
    background: var(--bg-surface);
}

.profile-stat {
    padding: 1.1rem 0.75rem;
    text-align: center;
    border-right: 1px solid var(--border-light);
}

.profile-stat:last-child { border-right: none; }

.profile-stat-num {
    font-family: 'Outfit', sans-serif;
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1;
    margin-bottom: 0.25rem;
}

.profile-stat-label {
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
}

.info-row {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.info-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
}

.info-value {
    font-size: 0.95rem;
    color: var(--text-main);
    font-weight: 500;
}

.info-verified {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    color: var(--success);
    font-weight: 600;
    font-size: 0.9rem;
}

.info-divider {
    height: 1px;
    background: var(--border-light);
    margin: 0.25rem 0;
}

/* ── Listings Grid ─────────────────────────────────────── */

.listings-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.listing-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
}

.listing-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    text-decoration: none;
    color: var(--text-main);
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    height: 100%; /* Ensure cards in the same row match height */
    max-width: 380px; /* Prevent excessive stretching on large screens */
}

.listing-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-hover);
    border-color: var(--primary);
    color: var(--text-main);
}

.listing-card-img {
    width: 100%;
    aspect-ratio: 4 / 3; /* Standardized aspect ratio */
    object-fit: cover;
    display: block;
    background: var(--bg-main);
    border-bottom: 1px solid var(--border-light);
}

.listing-card-body {
    padding: 0.9rem 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.listing-card-title {
    font-family: 'Outfit', sans-serif;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.3;
    margin: 0;

    /* two-line clamp */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.listing-card-price {
    font-size: 1rem;
    font-weight: 800;
    color: var(--primary);
    margin: 0;
}

.listing-card-cat {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 0.15rem 0.6rem;
    align-self: flex-start;
}
.listing-card-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.discount-form {
    margin-top: 0.35rem;
}
.discount-form select {
    width: 100%;
    margin-bottom: 0.35rem;
}

/* Sold items section */
.sold-section-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 2.5rem 0 1.25rem;
    color: var(--text-main);
}

.sold-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    opacity: 0.85;
    transition: var(--transition);
}

.sold-card:hover {
    opacity: 1;
    box-shadow: var(--shadow-sm);
}

.sold-card-img {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    display: block;
    background: var(--bg-main);
    filter: grayscale(30%);
    border-bottom: 1px solid var(--border-light);
}

.sold-card-body {
    padding: 0.9rem 1rem;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.sold-badge {
    display: inline-block;
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    font-weight: 700;
    font-size: 0.72rem;
    padding: 0.15rem 0.55rem;
    border-radius: var(--radius-lg);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sold-date {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: auto;
}

.sold-empty {
    text-align: center;
    padding: 2.5rem 1.5rem;
    color: var(--text-muted);
    background: var(--bg-main);
    border: 2px dashed var(--border-light);
    border-radius: var(--radius-lg);
    font-size: 0.9rem;
}



/* btn-white for the hero */
.btn-white {
    background: rgba(255,255,255,0.15);
    color: #fff !important;
    border: 1px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(4px);
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1.25rem;
    border-radius: var(--radius-md);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;
    transition: var(--transition);
}

.btn-white:hover {
    background: rgba(255,255,255,0.25);
    border-color: rgba(255,255,255,0.5);
    transform: translateY(-1px);
}

.btn-white-solid {
    background: #fff;
    color: var(--primary) !important;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 1.25rem;
    border-radius: var(--radius-md);
    text-decoration: none;
    font-size: 0.9rem;
    transition: var(--transition);
}

.btn-white-solid:hover {
    background: var(--primary-light);
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(99,102,241,0.2);
}

.premium-input:focus {
    border-color: var(--primary);
    background: var(--bg-surface);
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1), 0 4px 12px rgba(0, 0, 0, 0.05); /* Softer shadow */
    transform: translateY(-1px);
}

body.dark-mode .profile-stat-row {
    background: var(--bg-surface);
}

body.dark-mode .profile-stat {
    border-right-color: var(--border-light);
}

body.dark-mode .listing-card-img {
    background: var(--bg-main);
}

body.dark-mode .btn-white-solid {
    background: #334155;
    color: #e2e8f0 !important;
}

body.dark-mode .btn-white-solid:hover {
    background: #475569;
}
</style>

<!-- ═══ Profile Hero Banner ═══════════════════════════════════════ -->
<div class="profile-hero">
    <div class="profile-hero-inner">
        <div class="profile-hero-body">

            <!-- Avatar -->
            <div class="profile-avatar-wrap">
                <img src="<?php echo avatarUrl($user['avatar']); ?>" alt="<?php echo sanitize($user['username']); ?>" class="profile-avatar">
                <?php if ($user['role'] === 'admin'): ?>
                    <span class="profile-admin-badge">ADMIN</span>
                <?php endif; ?>
            </div>

            <!-- Name & meta -->
            <div class="profile-hero-meta">
                <h1 class="profile-username"><?php echo sanitize($user['username']); ?></h1>
                <p class="profile-since">Member since <?php echo formatJoinDate($user['created_at']); ?></p>
                <div class="profile-stars">
                    <?php if ($rating['count'] > 0): ?>
                        <span class="stars"><?php echo renderStars($rating['avg']); ?></span>
                        <span><strong><?php echo $rating['avg']; ?></strong> &nbsp;(<?php echo $rating['count']; ?> review<?php echo $rating['count'] !== 1 ? 's' : ''; ?>)</span>
                    <?php else: ?>
                        <span>No reviews yet</span>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 0.45rem; display: inline-flex; align-items: center; gap: 0.45rem;">
                    <span class="badge" style="background: rgba(255,255,255,0.22); color: #fff; font-size: 0.72rem; padding: 0.18rem 0.55rem; border: 1px solid rgba(255,255,255,0.35); border-radius: var(--radius-lg);">
                        <?php echo sanitize($trust['tier']); ?>
                    </span>
                    <span style="font-size: 0.82rem; color: rgba(255,255,255,0.88);">Trust Score: <?php echo (int)$trust['score']; ?>/100</span>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="profile-hero-actions">
                <?php if ($isSelf): ?>
                    <a href="<?php echo BASE_URL; ?>pages/recycle_bin.php" class="btn btn-white"><svg style="width: 16px; height: 16px; display: inline-block; margin-right: 6px; vertical-align: text-bottom;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg> Recycle Bin</a>
                    <a href="<?php echo BASE_URL; ?>pages/edit_profile.php" class="btn btn-white"><svg style="width: 16px; height: 16px; display: inline-block; margin-right: 6px; vertical-align: text-bottom;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Edit Profile</a>
                    <a href="logout.php" class="btn btn-white" style="margin-left: 0.5rem; background: rgba(239,68,68,0.2); border-color: rgba(239,68,68,0.3);">Logout</a>
                <?php elseif (isLoggedIn()): ?>
                    <a href="messages.php?to=<?php echo $user['id']; ?>" class="btn btn-white-solid"><svg style="width: 16px; height: 16px; display: inline-block; margin-right: 6px; vertical-align: text-bottom;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg> Message</a>
                <?php endif; ?>
            </div>

        </div>

        <!-- Tab bar -->
        <nav class="profile-tabs">
            <a href="#about" class="profile-tab <?php echo $activeTab === 'about' ? 'active' : ''; ?>" data-tab="about">About</a>
            <a href="#listings" class="profile-tab <?php echo $activeTab === 'listings' ? 'active' : ''; ?>" data-tab="listings">
                Listings
                <span class="tab-count"><?php echo $listingCount; ?></span>
            </a>
        </nav>
    </div>
</div>

<!-- ═══ Profile Body ═══════════════════════════════════════════════ -->
<div class="profile-body">

    <!-- ── Sidebar (About) ────────────────────────────────────── -->
    <aside class="profile-sidebar" id="about">

        <!-- Stats row -->
        <div class="profile-stat-row" style="grid-template-columns: repeat(2, 1fr); border-radius: var(--radius-xl);">
            <div class="profile-stat" style="border-bottom: 1px solid var(--border-light);">
                <div class="profile-stat-num"><?php echo $listingCount; ?></div>
                <div class="profile-stat-label">Listings</div>
            </div>
            <div class="profile-stat" style="border-right: none; border-bottom: 1px solid var(--border-light);">
                <div class="profile-stat-num"><?php echo $rating['count']; ?></div>
                <div class="profile-stat-label">Reviews</div>
            </div>
            <div class="profile-stat">
                <div class="profile-stat-num"><?php echo $rating['count'] > 0 ? $rating['avg'] : '—'; ?></div>
                <div class="profile-stat-label">Rating</div>
            </div>
            <div class="profile-stat" style="border-right: none;">
                <div class="profile-stat-num"><?php echo (int)$trust['score']; ?></div>
                <div class="profile-stat-label">Trust</div>
            </div>
        </div>

        <!-- Info card -->
        <div class="card" style="padding: 1.5rem; border-radius: var(--radius-xl);">
            <h3 style="font-size: 1rem; margin-bottom: 1.25rem; color: var(--text-main);">Account Details</h3>
            <div class="info-row">

                <div class="info-item">
                    <span class="info-label">Status</span>
                    <span class="info-verified">
                        <svg width="15" height="15" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Verified Student
                    </span>
                </div>

                <div class="info-divider"></div>

                <div class="info-item">
                    <span class="info-label">Username</span>
                    <span class="info-value">@<?php echo sanitize($user['username']); ?></span>
                </div>

                <div class="info-divider"></div>

                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value" style="word-break: break-all;">
                        <?php echo ($isSelf || isAdmin()) ? sanitize($user['email']) : '••••••••@••••.com'; ?>
                    </span>
                </div>

                <?php if (!empty($user['phone'])): ?>
                <div class="info-divider"></div>
                <div class="info-item">
                    <span class="info-label">Phone</span>
                    <span class="info-value"><?php echo sanitize($user['phone']); ?></span>
                </div>
                <?php endif; ?>

                <div class="info-divider"></div>

                <div class="info-item">
                    <span class="info-label">Member Since</span>
                    <span class="info-value"><?php echo formatJoinDate($user['created_at']); ?></span>
                </div>

                <?php if ($user['role'] === 'admin'): ?>
                <div class="info-divider"></div>
                <div class="info-item">
                    <span class="info-label">Role</span>
                    <span class="badge" style="background: #fee2e2; color: #991b1b; font-size: 0.8rem; padding: 0.3rem 0.75rem; width: fit-content; display: inline-flex; align-items: center; gap: 0.3rem;"><svg style="width: 14px; height: 14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Platform Administrator</span>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <?php 
        // Mini Analytics: Only show for self
        if ($isSelf): 
            $featuredOwn = array_filter($userProducts, function($p) { return (int)$p['is_featured'] === 1; });
            if (!empty($featuredOwn)):
        ?>
            <div class="card mt-6" style="padding: 1.5rem; border-radius: var(--radius-xl); border: 1px solid rgba(99, 102, 241, 0.2); background: rgba(99, 102, 241, 0.02);">
                <h3 style="font-size: 1rem; margin-bottom: 1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    Promotion Status
                </h3>
                <div class="info-row">
                    <?php foreach ($featuredOwn as $fp): ?>
                        <div class="info-item" style="padding-bottom: 0.75rem; border-bottom: 1px solid var(--border-light); margin-bottom: 0.75rem;">
                            <span class="info-value" style="font-size: 0.85rem; font-weight: 700;"><?php echo sanitize($fp['title']); ?></span>
                            <span class="info-label" style="text-transform: none; font-size: 0.75rem; color: var(--success); display: flex; align-items: center; gap: 0.3rem;">
                                <span style="display:inline-block; width: 6px; height: 6px; background: currentColor; border-radius: var(--radius-sm);"></span>
                                Actively Promoted
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted" style="font-size: 0.75rem; margin-top: 0.5rem;">These items are boosted to the top of search results and the homepage.</p>
            </div>
        <?php endif; endif; ?>

    </aside>

    <div class="profile-main">
        <!-- ── Listings Section ────────────────────────────────── -->
        <section id="listings">
            <div class="listings-header">
                <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-main); margin: 0; display: flex; items-center; gap: 0.5rem;">
                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg> Active Listings (<?php echo count($userProducts); ?>)
                </h2>
                <?php if ($isSelf): ?>
                    <a href="<?php echo BASE_URL; ?>pages/create_listing.php" class="btn btn-primary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg); padding: 0.5rem 1rem;">
                        + New Listing
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($userProducts)): ?>
                <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0;">No active listings</p>
            <?php else: ?>
                <div class="listing-grid">
                    <?php foreach ($userProducts as $prod): ?>
                        <div class="listing-card">
                            <a href="<?php echo BASE_URL; ?>pages/product.php?id=<?php echo (int)$prod['id']; ?>" style="text-decoration: none; color: inherit; display: block;">
                                <img 
                                    class="listing-card-img" 
                                    src="<?php echo getProductImage($prod['image_path'] ?? null); ?>" 
                                    alt="<?php echo sanitize($prod['title']); ?>"
                                    loading="lazy"
                                />
                            </a>
                            <div class="listing-card-body">
                                <div class="listing-card-meta">
                                    <span class="listing-card-cat"><?php echo sanitize($prod['category_name']); ?></span>
                                    <span class="listing-card-price"><?php echo formatPrice($prod['price']); ?></span>
                                </div>
                                <h3 class="listing-card-title">
                                    <a href="<?php echo BASE_URL; ?>pages/product.php?id=<?php echo (int)$prod['id']; ?>" style="text-decoration: none; color: inherit;">
                                        <?php echo sanitize($prod['title']); ?>
                                    </a>
                                </h3>

                                <?php if ($isSelf): ?>
                                    <div class="mt-4" style="border-top: 1px solid var(--border-light); padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                        <!-- Price Update Form -->
                                        <form method="post" style="margin: 0;">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="action" value="update_price">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$prod['id']; ?>">
                                            <div style="display: flex; gap: 0.25rem;">
                                                <input type="number" name="new_price" step="0.01" value="<?php echo (float)$prod['price']; ?>" class="premium-input" style="flex: 1; padding: 0.35rem 0.45rem; font-size: 0.82rem;" placeholder="Price">
                                                <button type="submit" class="btn btn-primary btn-sm" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;">Update</button>
                                            </div>
                                        </form>

                                        <!-- Discount Form -->
                                        <form method="post" class="discount-form">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="action" value="set_discount">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$prod['id']; ?>">
                                            <div style="display: flex; gap: 0.25rem;">
                                                <select name="discount_percent" class="premium-input" style="flex: 1; padding: 0.35rem 0.45rem; font-size: 0.82rem;">
                                                    <?php foreach ([0, 5, 10, 15, 20, 25, 30, 40, 50] as $d): ?>
                                                        <option value="<?php echo $d; ?>" <?php echo ((int)($prod['discount_percent'] ?? 0) === $d) ? 'selected' : ''; ?>>
                                                            <?php echo $d === 0 ? 'No discount' : ('-' . $d . '%'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-secondary btn-sm" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;">Apply</button>
                                            </div>
                                        </form>

                                        <!-- Promote Button -->
                                        <?php if ((int)$prod['is_featured'] === 0): ?>
                                        <a href="<?php echo BASE_URL; ?>pages/promotions.php?product_id=<?php echo (int)$prod['id']; ?>" class="btn btn-sm w-full" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; background: rgba(99, 102, 241, 0.1); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.2); font-weight: 700; text-align: center; border-radius: var(--radius-md); display: block;">Boost Listing</a>
                                        <?php else: ?>
                                        <button disabled class="btn btn-sm w-full" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; background: rgba(34, 197, 94, 0.1); color: #15803d; border: 1px solid rgba(34, 197, 94, 0.2); font-weight: 700; cursor: not-allowed; border-radius: var(--radius-md);">Already Promoted</button>
                                        <?php endif; ?>

                                        <!-- Delete Form (Move to Bin) -->
                                        <form method="post" onsubmit="return confirm('Move to Recycle Bin?')">
                                            <?php echo csrfTokenField(); ?>
                                            <input type="hidden" name="action" value="delete_listing">
                                            <input type="hidden" name="product_id" value="<?php echo (int)$prod['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm w-full" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; background: #fee2e2; color: #ef4444; border: none; font-weight: 700;">Delete Listing</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- ── Sold Items Section ──────────────────────────────── -->
        <section id="sold-items">
            <h2 class="sold-section-title" style="margin: 2.5rem 0 1.25rem;">
                ✅ Sold Items (<?php echo count($soldProducts); ?>)
            </h2>

            <?php if (empty($soldProducts)): ?>
                <div class="sold-empty">
                    <?php echo $isSelf ? 'No items sold yet.' : sanitize($user['username']) . ' has no confirmed sold items yet.'; ?>
                </div>
            <?php else: ?>
                <div class="listing-grid">
                    <?php foreach ($soldProducts as $sold): ?>
                        <div class="sold-card">
                            <img
                                class="sold-card-img"
                                src="<?php echo getProductImage($sold['image_path'] ?? null); ?>"
                                alt="<?php echo sanitize($sold['title']); ?>"
                                loading="lazy"
                            >
                            <div class="sold-card-body">
                                <p style="font-family: 'Outfit', sans-serif; font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin: 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?php echo sanitize($sold['title']); ?>
                                </p>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="text-decoration: line-through; color: var(--text-muted); font-size: 0.9rem;"><?php echo formatPrice($sold['price']); ?></span>
                                    <span class="sold-badge">SOLD</span>
                                </div>
                                <div class="sold-date">
                                    <?php
                                    if (!empty($sold['seller_confirmed_at'])) {
                                        echo 'Sold on ' . date('M d, Y', strtotime($sold['seller_confirmed_at']));
                                    } else {
                                        echo 'Sold';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tabs = Array.from(document.querySelectorAll('.profile-tab'));
    const aboutSection = document.getElementById('about');
    const listingsSection = document.getElementById('listings');

    function setActive(tabName) {
        tabs.forEach(function (tab) {
            tab.classList.toggle('active', tab.dataset.tab === tabName);
        });
    }

    function scrollToSection(tabName) {
        const target = tabName === 'about' ? aboutSection : listingsSection;
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (e) {
            e.preventDefault();
            const tabName = tab.dataset.tab;
            setActive(tabName);
            scrollToSection(tabName);
            if (history.replaceState) {
                history.replaceState(null, '', '#' + tabName);
            }
        });
    });

    const hash = (window.location.hash || '').replace('#', '');
    if (hash === 'about' || hash === 'listings') {
        setActive(hash);
    } else {
        setActive('<?php echo $activeTab; ?>');
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
