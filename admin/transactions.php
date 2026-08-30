<?php
// admin/transactions.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireAdmin();

$pageTitle = "Verified Transactions";

// Filters
$dateFrom         = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo           = isset($_GET['date_to'])   ? $_GET['date_to']   : '';
$selectedCurrency = isset($_GET['currency'])  ? strtoupper(trim($_GET['currency'])) : '';

$whereClause = "WHERE dc.status = 'completed'";
$params = [];

if (!empty($dateFrom)) {
    $whereClause .= " AND dc.seller_confirmed_at >= :date_from";
    $params[':date_from'] = $dateFrom . ' 00:00:00';
}
if (!empty($dateTo)) {
    $whereClause .= " AND dc.seller_confirmed_at <= :date_to";
    $params[':date_to'] = $dateTo . ' 23:59:59';
}
if (!empty($selectedCurrency) && array_key_exists($selectedCurrency, PRODUCT_CURRENCIES)) {
    if ($selectedCurrency === DEFAULT_PRODUCT_CURRENCY) {
        $whereClause .= " AND (p.price_currency = :currency OR p.price_currency IS NULL OR p.price_currency = '')";
    } else {
        $whereClause .= " AND p.price_currency = :currency";
    }
    $params[':currency'] = $selectedCurrency;
}

// Fetch deals
$sql = "
    SELECT
        dc.id,
        dc.product_id,
        p.title AS product_title,
        p.price AS product_price,
        p.price_currency AS product_price_currency,
        buyer.username AS buyer_username,
        seller.username AS seller_username,
        dc.buyer_confirmed_at,
        dc.seller_confirmed_at,
        dc.sale_source,
        dc.created_at
    FROM deal_confirmations dc
    JOIN products p ON dc.product_id = p.id
    LEFT JOIN users buyer ON dc.buyer_id = buyer.id
    JOIN users seller ON dc.seller_id = seller.id
    $whereClause
    ORDER BY dc.seller_confirmed_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$deals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary stats
$totalCount = count($deals);
$currencyTotals = [];
$totalValueTry = 0.0;
$totalValueUsd = 0.0;

foreach ($deals as $d) {
    $curr = productCurrencyCode(['price_currency' => $d['product_price_currency'] ?? DEFAULT_PRODUCT_CURRENCY]);
    $price = (float)$d['product_price'];
    $currencyTotals[$curr] = ($currencyTotals[$curr] ?? 0.0) + $price;
    $totalValueTry += convertCurrency($price, $curr, 'TRY');
    $totalValueUsd += convertCurrency($price, $curr, 'USD');
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.txn-summary-bar {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}

.txn-summary-stat {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    border-left: 4px solid #10b981;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.txn-summary-stat .label {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-muted);
    margin-bottom: 0.35rem;
}

.txn-summary-stat .value {
    font-family: 'Outfit', sans-serif;
    font-size: 1.7rem;
    font-weight: 800;
    color: var(--text-main);
    line-height: 1.15;
}

.txn-summary-stat .sub-hint {
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-top: 0.35rem;
}

.txn-currency-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem 0.5rem;
    align-items: center;
    margin-top: 0.25rem;
}

.txn-currency-pill {
    display: inline-flex;
    align-items: baseline;
    gap: 0.3rem;
    background: rgba(16, 185, 129, 0.08);
    border: 1px solid rgba(16, 185, 129, 0.22);
    padding: 0.2rem 0.5rem;
    border-radius: var(--radius-md);
}

.txn-currency-pill .txn-pill-code {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-muted);
}

.txn-currency-pill .txn-pill-amount {
    font-family: 'Outfit', sans-serif;
    font-size: 1.05rem;
    font-weight: 800;
    color: #059669;
}

.txn-filter-bar {
    display: flex;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1rem 1.25rem;
}

.txn-filter-bar label {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted);
    display: block;
    margin-bottom: 0.35rem;
}

.txn-filter-bar input[type="date"],
.txn-filter-bar select {
    padding: 0.45rem 0.75rem;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    background: var(--bg-main);
    color: var(--text-main);
}

.txn-filter-bar select {
    min-width: 140px;
}

.badge-completed {
    background: rgba(16, 185, 129, 0.12);
    color: #059669;
    font-weight: 700;
    font-size: 0.78rem;
    padding: 0.25rem 0.7rem;
    border-radius: var(--radius-full);
}
</style>

<div class="container mt-24 mb-16">
    <div class="flex justify-between items-end mb-8">
        <div>
            <div class="admin-breadcrumb mb-2"><a href="index.php">Dashboard</a> › Transactions</div>
            <h1 class="mb-0">Verified Transactions</h1>
        </div>
        <div class="badge" style="background: var(--bg-main); color: var(--text-muted); border: 1px solid var(--border-light); font-size: 0.9rem; padding: 0.5rem 1rem; border-radius: var(--radius-lg);"><?php echo $totalCount; ?> Deals</div>
    </div>

    <!-- Summary Bar -->
    <div class="txn-summary-bar">
        <div class="txn-summary-stat">
            <div class="label">Total Completed Deals</div>
            <div class="value"><?php echo $totalCount; ?></div>
            <div class="sub-hint"><?php echo ($dateFrom || $dateTo || $selectedCurrency) ? 'Filtered results' : 'All-time verified'; ?></div>
        </div>
        <div class="txn-summary-stat">
            <div class="label">Total Valuation (TRY)</div>
            <div class="value" style="color: #059669;">
                <?php 
                    $hasMultiOrForeign = count($currencyTotals) > 1 || (!empty($selectedCurrency) && $selectedCurrency !== 'TRY');
                    echo ($hasMultiOrForeign && $totalValueTry > 0 ? '≈ ' : '') . formatPrice($totalValueTry, 'TRY'); 
                ?>
            </div>
            <div class="sub-hint">Est. aggregate in Turkish Lira</div>
        </div>
        <div class="txn-summary-stat">
            <div class="label">Total Valuation (USD)</div>
            <div class="value" style="color: #059669;">
                <?php 
                    $hasMultiOrNonUsd = count($currencyTotals) > 1 || (!empty($selectedCurrency) && $selectedCurrency !== 'USD');
                    echo ($hasMultiOrNonUsd && $totalValueUsd > 0 ? '≈ ' : '') . formatPrice($totalValueUsd, 'USD'); 
                ?>
            </div>
            <div class="sub-hint">Est. aggregate in US Dollars</div>
        </div>
        <?php if (!empty($currencyTotals)): ?>
        <div class="txn-summary-stat" style="border-left-color: #3b82f6;">
            <div class="label">Recorded By Currency</div>
            <div class="txn-currency-pills">
                <?php foreach ($currencyTotals as $currCode => $sumAmount): ?>
                    <span class="txn-currency-pill">
                        <span class="txn-pill-code"><?php echo htmlspecialchars($currCode); ?></span>
                        <span class="txn-pill-amount"><?php echo formatPrice($sumAmount, $currCode); ?></span>
                    </span>
                <?php endforeach; ?>
            </div>
            <div class="sub-hint">Original listing currency totals</div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Date Range & Currency Filter -->
    <form method="GET" class="txn-filter-bar">
        <div>
            <label for="date_from">From</label>
            <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
        </div>
        <div>
            <label for="date_to">To</label>
            <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
        </div>
        <div>
            <label for="currency">Currency</label>
            <select id="currency" name="currency">
                <option value="">All Currencies</option>
                <?php foreach (PRODUCT_CURRENCIES as $code => $info): ?>
                    <option value="<?php echo $code; ?>" <?php echo ($selectedCurrency === $code) ? 'selected' : ''; ?>>
                        <?php echo $code; ?> (<?php echo $info['symbol']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="margin-bottom: 1px;">Filter</button>
        <?php if ($dateFrom || $dateTo || $selectedCurrency): ?>
            <a href="transactions.php" class="btn btn-secondary btn-sm" style="margin-bottom: 1px;">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Deals Table -->
    <div class="glass-panel table-responsive" style="border-radius: var(--radius-lg); border: 1px solid rgba(0,0,0,0.05); box-shadow: var(--shadow-md);">
        <table class="table w-full text-left" style="border-collapse: collapse; margin: 0;">
            <thead>
                <tr style="background: rgba(248, 250, 252, 0.8);">
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">#</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Product</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Seller</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Buyer</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Price</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider" style="border-bottom: 2px solid var(--border-light);">Status</th>
                    <th class="p-4 uppercase text-xs text-muted font-bold tracking-wider text-right" style="border-bottom: 2px solid var(--border-light);">Confirmed At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deals as $i => $deal): ?>
                    <tr style="transition: background 0.2s;" onmouseover="this.style.background='rgba(0,0,0,0.02)'" onmouseout="this.style.background='transparent'">
                        <td class="p-4 font-bold" style="border-bottom: 1px solid var(--border-light); color: var(--text-muted);">#<?php echo $deal['id']; ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <div class="font-bold text-main"><?php echo sanitize($deal['product_title']); ?></div>
                            <a href="../pages/product.php?id=<?php echo $deal['product_id']; ?>" class="small text-primary hover-scale inline-block mt-1" target="_blank" style="text-decoration: none; font-weight: 600;">View Listing →</a>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span style="background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-light); font-size: 0.85rem;">@<?php echo sanitize($deal['seller_username']); ?></span>
                        </td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <?php if (!empty($deal['buyer_username'])): ?>
                            <span style="background: var(--bg-main); padding: 0.2rem 0.5rem; border-radius: 4px; border: 1px solid var(--border-light); font-size: 0.85rem;">@<?php echo sanitize($deal['buyer_username']); ?></span>
                            <?php else: ?>
                            <span class="text-muted small">Off-platform</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-4 font-bold" style="border-bottom: 1px solid var(--border-light); font-size: 1.1rem; color: #059669;"><?php echo formatPrice($deal['product_price'], productCurrencyCode(['price_currency' => $deal['product_price_currency'] ?? DEFAULT_PRODUCT_CURRENCY])); ?></td>
                        <td class="p-4" style="border-bottom: 1px solid var(--border-light);">
                            <span class="badge-completed shadow-sm">Completed</span>
                        </td>
                        <td class="p-4 text-right text-muted small" style="border-bottom: 1px solid var(--border-light); font-family: monospace;">
                            <?php echo $deal['seller_confirmed_at'] ? date('M d, Y • H:i', strtotime($deal['seller_confirmed_at'])) : '—'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (empty($deals)): ?>
            <div class="text-center p-8 text-muted">
                <span class="text-4xl mb-4 block"><svg style="width: 48px; height: 48px; display: inline-block; color: var(--text-muted); opacity: 0.5;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg></span>
                No verified transactions found<?php echo ($dateFrom || $dateTo || $selectedCurrency) ? ' for the selected filters' : ''; ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
