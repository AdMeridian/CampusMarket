<?php
// admin/index.php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/header.php';

// Auth Check
if (!isAdmin()) {
    setFlash('error', 'Unauthorized access.');
    redirect('../index.php');
}

$pageTitle = "Admin Dashboard";

// Fetch Stats
$stats = [
    'listings'        => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'active_listings' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
    'users'           => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn(),
    'orders'          => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'pending_orders'  => $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(),
    'reports'         => $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn(),
    'completed_deals' => $pdo->query("SELECT COUNT(*) FROM deal_confirmations WHERE status = 'completed'")->fetchColumn(),
];

// Top sellers by completed transactions
$sellerTxnStmt = $pdo->query("
    SELECT
        u.id AS seller_id,
        u.username AS seller_username,
        COUNT(dc.id) AS total_transactions
    FROM deal_confirmations dc
    JOIN users u ON u.id = dc.seller_id
    WHERE dc.status = 'completed'
    GROUP BY u.id, u.username
    ORDER BY total_transactions DESC, u.username ASC
    LIMIT 10
");
$sellerTransactionStats = $sellerTxnStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
/* ── Admin Dashboard Styles ───────────────────────────── */

.admin-wrap {
    max-width: var(--container-max);
    margin: 2.5rem auto 5rem;
    padding: 0 1.5rem;
}

/* Page title row */
.admin-title-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2rem;
    flex-wrap: wrap;
    gap: 1rem;
}

.admin-title-row h1 {
    margin: 0;
    font-size: 1.9rem;
}

.admin-breadcrumb {
    font-size: 0.85rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

/* ── Stat Cards ───────────────────────────────────────── */

.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2.5rem;
}

@media (max-width: 900px)  { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 500px)  { .stat-grid { grid-template-columns: 1fr; } }

.stat-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1.5rem;
    border-left: 4px solid transparent;
    transition: var(--transition);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.stat-card-label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted);
}

.stat-card-num {
    font-family: 'Outfit', sans-serif;
    font-size: 2.4rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1;
}

.stat-card-sub {
    font-size: 0.8rem;
    color: var(--text-muted);
}

/* ── Bottom two-col layout ────────────────────────────── */

.admin-bottom {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

@media (max-width: 800px) { .admin-bottom { grid-template-columns: 1fr; } }

/* ── Module Grid ──────────────────────────────────────── */

.module-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.module-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.1rem 1.25rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    text-decoration: none;
    color: var(--text-main);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.module-card::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    background: var(--module-color, var(--primary));
    border-radius: 4px 0 0 4px;
}

.module-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-md);
    border-color: var(--module-color, var(--primary));
    color: var(--text-main);
}

.module-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-md);
    background: var(--module-bg, var(--primary-light));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

.module-info {
    flex: 1;
    min-width: 0;
}

.module-name {
    font-family: 'Outfit', sans-serif;
    font-weight: 700;
    font-size: 0.95rem;
    color: var(--text-main);
    margin-bottom: 0.15rem;
}

.module-desc {
    font-size: 0.75rem;
    color: var(--text-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.module-arrow {
    color: var(--text-muted);
    font-size: 1rem;
    flex-shrink: 0;
    transition: var(--transition);
}

.module-card:hover .module-arrow {
    color: var(--module-color, var(--primary));
    transform: translateX(3px);
}

/* ── System Status Card ───────────────────────────────── */

.system-card {
    background: var(--primary);
    border-radius: var(--radius-lg);
    padding: 1.75rem;
    color: #fff;
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.system-card h3 {
    color: #fff;
    margin: 0;
    font-size: 1.2rem;
}

.system-card p {
    color: rgba(255,255,255,0.7);
    font-size: 0.875rem;
    margin: 0;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.15);
    border-radius: var(--radius-md);
    padding: 0.9rem 1.1rem;
    backdrop-filter: blur(4px);
}

.status-dot {
    width: 10px;
    height: 10px;
    border-radius: var(--radius-lg);
    background: var(--success);
    flex-shrink: 0;
}

.status-label {
    font-weight: 600;
    font-size: 0.9rem;
    color: #fff;
}

.status-sub {
    font-size: 0.78rem;
    color: rgba(255,255,255,0.6);
}

/* Reports alert strip */
.reports-alert {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    border-left: 4px solid #f97316;
    border-radius: var(--radius-md);
    padding: 0.9rem 1.25rem;
    gap: 1rem;
    text-decoration: none;
    transition: var(--transition);
    margin-bottom: 1.5rem;
}

.reports-alert:hover {
    background: #fff3e0;
    box-shadow: var(--shadow-sm);
}

.reports-alert-text {
    font-weight: 600;
    font-size: 0.9rem;
    color: #9a3412;
}

.reports-alert-sub {
    font-size: 0.8rem;
    color: #c2410c;
}

.reports-alert-num {
    font-family: 'Outfit', sans-serif;
    font-size: 2rem;
    font-weight: 800;
    color: #ea580c;
    line-height: 1;
    flex-shrink: 0;
}

.section-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    margin-top: 0.25rem;
}

/* ── Transaction Insight Card ─────────────────────────── */
.txn-insight-card {
    margin-top: 1.5rem;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
}

.txn-insight-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.9rem;
    flex-wrap: wrap;
}

.txn-insight-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-main);
    margin: 0;
}

.txn-insight-total {
    font-size: 0.78rem;
    font-weight: 700;
    color: #059669;
    background: rgba(16, 185, 129, 0.12);
    border: 1px solid rgba(16, 185, 129, 0.25);
    border-radius: var(--radius-lg);
    padding: 0.2rem 0.65rem;
}

.txn-seller-list {
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.txn-seller-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    background: var(--bg-main);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: 0.55rem 0.7rem;
}

.txn-seller-name {
    font-weight: 600;
    color: var(--text-main);
    font-size: 0.88rem;
}

.txn-seller-count {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
}
</style>

<div class="admin-wrap">

    <!-- Title row -->
    <div class="admin-title-row">
        <div>
            <h1>Admin Dashboard</h1>
            <div class="admin-breadcrumb">
                <svg style="width: 14px; height: 14px; display: inline-block; position: relative; top: -1px; margin-right: 4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> Administrator &rsaquo; Platform Overview
            </div>
        </div>
        <span style="font-size: 0.82rem; color: var(--text-muted);">
            <?php echo date('l, F j, Y'); ?>
        </span>
    </div>

    <!-- ── Stat Cards ──────────────────────────────────────────── -->
    <div class="stat-grid">
        <div class="stat-card" style="border-left-color: var(--primary);">
            <div class="stat-card-label">Total Listings</div>
            <div class="stat-card-num"><?php echo $stats['listings']; ?></div>
            <div class="stat-card-sub"><?php echo $stats['active_listings']; ?> currently active</div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-card-label">Registered Users</div>
            <div class="stat-card-num"><?php echo $stats['users']; ?></div>
            <div class="stat-card-sub">Students on platform</div>
        </div>
        <div class="stat-card" style="border-left-color: var(--warning);">
            <div class="stat-card-label">Total Orders</div>
            <div class="stat-card-num"><?php echo $stats['orders']; ?></div>
            <div class="stat-card-sub"><?php echo $stats['pending_orders']; ?> pending</div>
        </div>
        <div class="stat-card" style="border-left-color: var(--error);">
            <div class="stat-card-label">Pending Reports</div>
            <div class="stat-card-num"><?php echo $stats['reports']; ?></div>
            <div class="stat-card-sub">Awaiting moderation</div>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <div class="stat-card-label"><svg style="width: 14px; height: 14px; display: inline-block; margin-right: 4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Completed Deals</div>
            <div class="stat-card-num"><?php echo $stats['completed_deals']; ?></div>
            <div class="stat-card-sub">Verified transactions</div>
        </div>
    </div>

    <!-- ── Bottom layout ──────────────────────────────────────── -->
    <div class="admin-bottom">

        <!-- Left: Management Modules -->
        <div>
            <?php if ($stats['reports'] > 0): ?>
            <a href="reports.php" class="reports-alert">
                <div>
                    <div class="reports-alert-text"><svg style="width: 16px; height: 16px; display: inline-block; margin-right: 4px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Moderation Required</div>
                    <div class="reports-alert-sub">Reports are waiting for your review</div>
                </div>
                <div class="reports-alert-num"><?php echo $stats['reports']; ?></div>
            </a>
            <?php endif; ?>

            <div class="card" style="padding: 1.5rem;">
                <h3 style="margin-bottom: 1.25rem; font-size: 1.1rem;">Management Modules</h3>

                <div class="section-label">Core</div>
                <div class="module-grid" style="margin-bottom: 1.25rem;">
                    <a href="listings.php" class="module-card" style="--module-color: var(--primary); --module-bg: var(--primary-light);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg></div>
                        <div class="module-info">
                            <div class="module-name">Listings</div>
                            <div class="module-desc">Manage all products</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                    <a href="users.php" class="module-card" style="--module-color: var(--primary); --module-bg: var(--primary-light);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
                        <div class="module-info">
                            <div class="module-name">Users</div>
                            <div class="module-desc">Promote, demote, remove</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                    <a href="orders.php" class="module-card" style="--module-color: var(--warning); --module-bg: var(--warning-bg);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg></div>
                        <div class="module-info">
                            <div class="module-name">Orders</div>
                            <div class="module-desc">View all transactions</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                    <a href="reports.php" class="module-card" style="--module-color: var(--error); --module-bg: var(--error-bg);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
                        <div class="module-info">
                            <div class="module-name">Moderation</div>
                            <div class="module-desc">Review flagged content</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                    <a href="transactions.php" class="module-card" style="--module-color: var(--success); --module-bg: var(--success-bg);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
                        <div class="module-info">
                            <div class="module-name">Transactions</div>
                            <div class="module-desc">Verified deal history</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                    <a href="../pages/inbox.php" class="module-card" style="--module-color: var(--secondary); --module-bg: var(--secondary-light);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg></div>
                        <div class="module-info">
                            <div class="module-name">Support Chat</div>
                            <div class="module-desc">Reply to student messages</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                </div>

                <div class="section-label">Taxonomy</div>
                <div class="module-grid">
                    <a href="categories.php" class="module-card" style="--module-color: var(--primary); --module-bg: var(--primary-light);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg></div>
                        <div class="module-info">
                            <div class="module-name">Categories</div>
                            <div class="module-desc">Browse & edit</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                    <a href="tags.php" class="module-card" style="--module-color: var(--primary); --module-bg: var(--primary-light);">
                        <div class="module-icon"><svg style="width: 24px; height: 24px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></div>
                        <div class="module-info">
                            <div class="module-name">Tags</div>
                            <div class="module-desc">Browse & edit</div>
                        </div>
                        <span class="module-arrow">›</span>
                    </a>
                </div>

                <div class="txn-insight-card">
                    <div class="txn-insight-head">
                        <h4 class="txn-insight-title">Transaction Insights</h4>
                        <span class="txn-insight-total">Total: <?php echo (int)$stats['completed_deals']; ?></span>
                    </div>
                    <?php if (empty($sellerTransactionStats)): ?>
                        <p style="margin: 0; font-size: 0.85rem; color: var(--text-muted);">No completed transactions yet.</p>
                    <?php else: ?>
                        <div class="txn-seller-list">
                            <?php foreach ($sellerTransactionStats as $row): ?>
                                <div class="txn-seller-row">
                                    <a class="txn-seller-name" href="../pages/profile.php?id=<?php echo (int)$row['seller_id']; ?>">
                                        @<?php echo sanitize($row['seller_username']); ?>
                                    </a>
                                    <span class="txn-seller-count">
                                        <?php echo (int)$row['total_transactions']; ?> transaction<?php echo (int)$row['total_transactions'] !== 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: System Status -->
        <div class="system-card">
            <div>
                <h3>System Pulse</h3>
                <p>All core systems are operational. The marketplace engine is connected and actively serving students.</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div class="status-item">
                    <div class="status-dot"></div>
                    <div>
                        <div class="status-label">Database Server</div>
                        <div class="status-sub">Connected · Latency &lt; 15ms</div>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-dot"></div>
                    <div>
                        <div class="status-label">Safety Engine</div>
                        <div class="status-sub">Active monitoring · <?php echo $stats['reports']; ?> report<?php echo $stats['reports'] != 1 ? 's' : ''; ?> pending</div>
                    </div>
                </div>
                <div class="status-item">
                    <div class="status-dot"></div>
                    <div>
                        <div class="status-label">File Storage</div>
                        <div class="status-sub">Image uploads operational</div>
                    </div>
                </div>
            </div>

            <div style="margin-top: auto; padding-top: 0.5rem; border-top: 1px solid rgba(255,255,255,0.15);">
                <div style="font-size: 0.78rem; color: rgba(255,255,255,0.55);">
                    Platform stats as of <?php echo date('H:i, M j'); ?> · <?php echo $stats['users']; ?> users · <?php echo $stats['listings']; ?> listings
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
