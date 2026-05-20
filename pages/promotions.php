<?php
// pages/promotions.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

if (isAdmin()) {
    setFlash('info', 'Admins can review requests in the Admin Panel.');
    redirect(BASE_URL . 'admin/promotion_payments.php');
}

$currentUserId = currentUserId();
$pageTitle = 'Promotions & Donations';
$prefillProductId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$promoPaymentsTableExists = false;
try {
    $promoPaymentsTableExists = (bool)$pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'promotion_payments' LIMIT 1")->fetchColumn();
} catch (PDOException $e) {
    $promoPaymentsTableExists = false;
}

if (!$promoPaymentsTableExists) {
    setFlash('error', 'Promotion payment feature is not active yet. Database update is required.');
    redirect(BASE_URL . 'pages/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $action = sanitize($_POST['action'] ?? '');

    if ($action === 'submit_payment') {
        $paymentType = sanitize($_POST['payment_type'] ?? '');
        $method = sanitize($_POST['payment_method'] ?? '');
        $txRef = sanitize($_POST['transaction_ref'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $productIdRaw = (int)($_POST['product_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);

        if (!in_array($paymentType, ['promotion', 'donation'], true)) {
            setFlash('error', 'Invalid payment type.');
            redirect(BASE_URL . 'pages/promotions.php');
        }

        if (!in_array($method, ['venmo', 'zelle', 'cash', 'other'], true)) {
            setFlash('error', 'Invalid payment method.');
            redirect(BASE_URL . 'pages/promotions.php');
        }

        if ($amount <= 0) {
            setFlash('error', 'Amount must be greater than zero.');
            redirect(BASE_URL . 'pages/promotions.php');
        }

        $productId = null;
        if ($paymentType === 'promotion') {
            if ($productIdRaw <= 0) {
                setFlash('error', 'Choose a listing for promotion.');
                redirect(BASE_URL . 'pages/promotions.php');
            }

            $ownCheck = $pdo->prepare('SELECT id FROM products WHERE id = :pid AND user_id = :uid');
            $ownCheck->execute([':pid' => $productIdRaw, ':uid' => $currentUserId]);
            if (!$ownCheck->fetchColumn()) {
                setFlash('error', 'You can only promote your own listing.');
                redirect(BASE_URL . 'pages/promotions.php');
            }
            $productId = $productIdRaw;
        }

        $insert = $pdo->prepare("
            INSERT INTO promotion_payments
                (user_id, product_id, payment_type, payment_method, amount, transaction_ref, notes, status)
            VALUES
                (:uid, :pid, :ptype, :pmethod, :amount, :tx, :notes, 'pending')
        ");

        $insert->execute([
            ':uid' => $currentUserId,
            ':pid' => $productId,
            ':ptype' => $paymentType,
            ':pmethod' => $method,
            ':amount' => $amount,
            ':tx' => $txRef !== '' ? $txRef : null,
            ':notes' => $notes !== '' ? $notes : null,
        ]);

        setFlash('success', 'Payment request submitted. Admin will review it shortly.');
        redirect(BASE_URL . 'pages/promotions.php');
    }
}

$myProductsStmt = $pdo->prepare("SELECT id, title, status, is_featured FROM products WHERE user_id = :uid ORDER BY created_at DESC");
$myProductsStmt->execute([':uid' => $currentUserId]);
$myProducts = $myProductsStmt->fetchAll();

$paymentsStmt = $pdo->prepare("
    SELECT pp.*, p.title AS product_title
    FROM promotion_payments pp
    LEFT JOIN products p ON p.id = pp.product_id
    WHERE pp.user_id = :uid AND pp.payment_type = 'promotion'
    ORDER BY pp.created_at DESC
");
$paymentsStmt->execute([':uid' => $currentUserId]);
$payments = $paymentsStmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="donation-page-wrapper" style="position: relative; overflow: hidden; min-height: 80vh; padding: 4rem 0;">
    <!-- Subtle Background Glows -->
    <!-- Background removed for flat aesthetic -->

    <div class="container" style="max-width: 1000px;">
        <?php if (isset($_GET['new_listing']) && $_GET['new_listing'] == '1'): ?>
            <div class="mb-8 p-4 text-center" style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.3); border-radius: var(--radius-lg);">
                <h2 style="color: #15803d; font-size: 1.25rem; font-weight: 800; margin-bottom: 0.5rem;">🎉 Your listing is live!</h2>
                <p style="color: #166534; font-size: 0.95rem; margin-bottom: 0.5rem;">Want to get up to 10x more visibility right away? Boost your listing below.</p>
                <a href="<?php echo BASE_URL; ?>pages/profile.php" style="color: #166534; text-decoration: underline; font-size: 0.85rem; font-weight: 600;">No thanks, take me to my profile</a>
            </div>
        <?php endif; ?>

        <!-- Header -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center gap-2 mb-3 font-bold" style="font-size: 0.85rem; color: var(--primary); letter-spacing: 0.1em; text-transform: uppercase;">
                <svg style="width: 18px; height: 18px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg> Seller Spotlight • Boost Sales
            </div>
            <h1 class="font-bold text-6xl mb-4 text-main" style="letter-spacing: -0.03em; line-height: 1.1;">Elevate Your Listings <br><span>Reach More Buyers</span></h1>
            <p class="text-main" style="font-size: 1.1rem; line-height: 1.6; font-weight: 500; opacity: 0.8; text-align: center; width: 100%;">
                Promoted items stay at the top of search results and categories. Increase your visibility by up to 10x with a featured boost.
            </p>
        </div>

        <div class="flex flex-col lg:flex-row gap-10 items-stretch">
            <!-- Left: Promotion Configurator -->
            <div class="glass-panel" style="flex: 1.3; width: 100%; border-radius: 32px; border: 1px solid rgba(99, 102, 241, 0.15); box-shadow: 0 25px 60px rgba(0,0,0,0.08); background: white; padding: 2rem; display: flex; flex-direction: column;">
                <h3 class="mb-6 font-bold text-main uppercase tracking-widest text-center" style="font-size: 1rem; letter-spacing: 0.15em;">Configure Your Boost</h3>
                
                <div class="mb-8 p-4" style="background: rgba(99, 102, 241, 0.03); border-radius: 20px; border: 1px dashed rgba(99, 102, 241, 0.2);">
                    <label class="block mb-2 font-bold" style="font-size: 0.85rem; color: var(--primary); text-transform: uppercase;">1. Select Listing</label>
                    <select name="product_id" id="product_id" class="form-control" style="border-radius: 12px; border: 1px solid #e2e8f0; font-weight: 500;">
                        <option value="">Choose a listing to promote...</option>
                        <?php foreach ($myProducts as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo ($prefillProductId > 0 && (int)$p['id'] === $prefillProductId) ? 'selected' : ''; ?>>
                                <?php echo sanitize($p['title']); ?> (<?php echo (int)$p['is_featured'] === 1 ? 'Already Featured' : 'Not Featured'; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-8">
                    <label class="block mb-4 font-bold text-center" style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase;">2. Choose Boost Amount</label>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="amount-pill active" data-amount="50.00">₺50 <br><span style="font-size: 0.65rem; opacity: 0.8; display: block; margin-top: 2px;">3 DAYS</span></div>
                        <div class="amount-pill" data-amount="100.00">₺100 <br><span style="font-size: 0.65rem; opacity: 0.8; display: block; margin-top: 2px;">7 DAYS</span></div>
                        <div class="amount-pill" data-amount="200.00">₺200 <br><span style="font-size: 0.65rem; opacity: 0.8; display: block; margin-top: 2px;">30 DAYS</span></div>
                    </div>
                    <input type="number" id="custom-amount" class="premium-input mt-4 text-center" placeholder="Or enter amount in TL" style="border-radius: 16px; font-size: 1.1rem; font-weight: 700;">
                    <p class="text-center mt-2 text-muted" id="duration-preview" style="font-size: 0.8rem;">Boost duration: <strong>3 days</strong></p>
                </div>

                <!-- Stripe Call to Action -->
                <form action="create_stripe_session.php" method="POST" id="stripe-form">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="payment_type" value="promotion">
                    <input type="hidden" name="product_id" id="stripe_product_id">
                    <input type="hidden" name="amount" id="stripe_amount" value="50.00">
                    
                    <button type="button" id="pay-stripe-btn" class="cta-button" style="width: 100%; font-size: 1.1rem; padding: 1.1rem;">
                        Boost Instantly with Stripe
                    </button>
                    
                    <div class="flex items-center justify-center gap-4 mt-8" style="padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                        <div class="flex items-center gap-2 opacity-60">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="color: #22c55e;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            <span class="font-bold uppercase tracking-widest" style="font-size: 0.65rem;">Auto-Activation</span>
                        </div>
                        <div style="width: 1px; height: 12px; background: #cbd5e1;"></div>
                        <img src="https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg" height="15" alt="Stripe" style="opacity: 0.6;">
                    </div>
                </form>

                <button id="show-manual" class="mt-4 text-center text-muted" style="font-size: 0.85rem; background: none; border: none; cursor: pointer; text-decoration: underline;">
                    Prefer manual payment (Venmo/Zelle)?
                </button>
            </div>

            <!-- Right: Status & History -->
            <div style="flex: 0.7; display: flex; flex-direction: column; gap: 1.5rem;">
                <div class="glass-panel" style="padding: 1.75rem; border-radius: 28px; background: rgba(255,255,255,0.7); border: 1px solid rgba(0,0,0,0.03);">
                    <h4 class="font-bold mb-4" style="font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                        <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Boost Status
                    </h4>
                    
                    <?php if (empty($payments)): ?>
                        <div class="text-center py-8 opacity-50">
                            <p style="font-size: 0.9rem;">No active boosts yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col gap-4">
                            <?php foreach (array_slice($payments, 0, 5) as $pay): ?>
                                <div style="background: white; padding: 1rem; border-radius: 16px; box-shadow: var(--shadow-sm); border: 1px solid #f1f5f9;">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="font-bold" style="font-size: 0.85rem; color: var(--text-main);"><?php echo sanitize($pay['product_title'] ?: 'Listing Boost'); ?></span>
                                        <span class="badge badge-<?php echo sanitize($pay['status']); ?>" style="font-size: 0.65rem;"><?php echo strtoupper(sanitize($pay['status'])); ?></span>
                                    </div>
                                    <div class="flex justify-between items-center opacity-60" style="font-size: 0.75rem;">
                                        <span><?php echo formatPrice((float)$pay['amount']); ?> • <?php echo ucfirst(sanitize($pay['payment_method'])); ?></span>
                                        <span><?php echo date('M d', strtotime($pay['created_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="glass-panel" style="padding: 1.75rem; border-radius: 28px; background: var(--primary); color: white; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2);">
                    <h4 class="font-bold mb-2" style="color: white; font-size: 1.1rem;">Seller Tip</h4>
                    <p style="font-size: 0.95rem; color: #f8fafc; font-weight: 500; opacity: 1; line-height: 1.6; margin-bottom: 0;">Boosted items receive <strong>300% more clicks</strong> on average. For best results, use high-quality photos!</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Manual Payment Modal (Hidden by default) -->
<div id="manual-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 1.5rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; background: white; padding: 2rem; border-radius: 32px; position: relative;">
        <button id="close-manual" style="position: absolute; top: 1.5rem; right: 1.5rem; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        
        <h3 class="mb-2">Manual Verification</h3>
        <p class="text-muted mb-6" style="font-size: 0.9rem;">Submit your transaction details for admin review. This may take up to 24 hours.</p>
        
        <form method="post">
            <?php echo csrfTokenField(); ?>
            <input type="hidden" name="action" value="submit_payment">
            <input type="hidden" name="payment_type" value="promotion">
            <input type="hidden" name="product_id" id="manual_product_id">
            <input type="hidden" name="amount" id="manual_amount">

            <label class="form-label">Payment Method</label>
            <select name="payment_method" class="form-control mb-4" required>
                <option value="venmo">Venmo (@CampusMarket)</option>
                <option value="zelle">Zelle (pay@campusmarket.edu)</option>
                <option value="cash">Cash (In-person)</option>
            </select>

            <label class="form-label">Transaction Ref / Receipt ID</label>
            <input type="text" name="transaction_ref" class="form-control mb-4" placeholder="Optional reference number">

            <button type="submit" class="btn btn-primary w-full" style="padding: 0.8rem;">Submit for Review</button>
        </form>
    </div>
</div>

<style>
.amount-pill {
    padding: 0.75rem;
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    text-align: center;
    font-weight: 700;
    cursor: pointer;
    transition: var(--transition);
    color: var(--text-muted);
}
.amount-pill:hover {
    border-color: var(--primary-light);
    background: var(--primary-light);
    color: var(--primary);
}
.amount-pill.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
}
.cta-button {
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 18px;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.4);
}
.cta-button:hover {
    transform: translateY(-2px) scale(1.02);
    box-shadow: 0 15px 30px -5px rgba(99, 102, 241, 0.5);
}
.premium-input {
    width: 100%;
    padding: 0.8rem 1rem;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    transition: var(--transition);
}
.premium-input:focus {
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pills = document.querySelectorAll('.amount-pill');
    const customInput = document.getElementById('custom-amount');
    const stripeAmount = document.getElementById('stripe_amount');
    const manualAmount = document.getElementById('manual_amount');
    const productPicker = document.getElementById('product_id');
    const stripeProduct = document.getElementById('stripe_product_id');
    const manualProduct = document.getElementById('manual_product_id');

    const previewEl = document.getElementById('duration-preview');

    function updatePreview(val) {
        let days = 0;
        const amount = parseFloat(val);
        
        if (amount == 50) days = 3;
        else if (amount == 100) days = 7;
        else if (amount >= 200) days = 30;
        else if (amount > 0) days = Math.max(1, Math.floor(amount / 15));

        if (days > 0) {
            previewEl.innerHTML = `Boost duration: <strong>${days} day${days > 1 ? 's' : ''}</strong>`;
        } else {
            previewEl.innerHTML = "Enter an amount to see duration";
        }
    }

    // Handle Amount Selection
    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            const amt = this.dataset.amount;
            customInput.value = amt;
            stripeAmount.value = amt;
            manualAmount.value = amt;
            pills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            updatePreview(amt);
        });
    });

    customInput.addEventListener('input', function() {
        const val = this.value;
        stripeAmount.value = val;
        manualAmount.value = val;
        updatePreview(val);
        pills.forEach(p => {
            if (p.dataset.amount === val) p.classList.add('active');
            else p.classList.remove('active');
        });
    });

    // Handle Product Selection
    productPicker.addEventListener('change', function() {
        stripeProduct.value = this.value;
        manualProduct.value = this.value;
    });

    // Initialize if pre-selected
    if (productPicker.value) {
        stripeProduct.value = productPicker.value;
        manualProduct.value = productPicker.value;
    }

    // Prefill from create listing flow if query param is present.
    const prefillProductId = <?= (int)$prefillProductId ?>;
    if (prefillProductId > 0) {
        productPicker.value = String(prefillProductId);
        stripeProduct.value = String(prefillProductId);
        manualProduct.value = String(prefillProductId);
    }

    // Stripe Validation
    document.getElementById('pay-stripe-btn').addEventListener('click', function() {
        if (!productPicker.value) {
            alert('Please select a listing to boost first.');
            productPicker.focus();
            return;
        }
        if (!stripeAmount.value || stripeAmount.value <= 0) {
            alert('Please select or enter a valid boost amount.');
            return;
        }
        document.getElementById('stripe-form').submit();
    });

    // Manual Modal Logic
    const manualModal = document.getElementById('manual-modal');
    document.getElementById('show-manual').addEventListener('click', () => {
        if (!productPicker.value) {
            alert('Please select a listing to boost first.');
            return;
        }
        manualModal.style.display = 'flex';
    });
    document.getElementById('close-manual').addEventListener('click', () => {
        manualModal.style.display = 'none';
    });
});
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>

