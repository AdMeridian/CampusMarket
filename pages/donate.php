<?php
// pages/donate.php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = 'Support CampusMarket';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="donation-page-wrapper" style="position: relative; overflow: hidden; min-height: 80vh; padding: 4rem 0;">
    <!-- Subtle Background Glows -->
    <div style="position: absolute; top: 10%; left: 15%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(99, 102, 241, 0.08) 0%, rgba(255, 255, 255, 0) 70%); filter: blur(60px); z-index: -1;"></div>
    <div style="position: absolute; bottom: 10%; right: 15%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(168, 85, 247, 0.08) 0%, rgba(255, 255, 255, 0) 70%); filter: blur(60px); z-index: -1;"></div>

    <div class="container" style="max-width: 1000px; padding: 0 1.5rem;">
        <!-- Header -->
        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 mb-4 font-bold" style="font-size: 0.85rem; color: var(--primary); letter-spacing: 0.1em; text-transform: uppercase;">
                <span style="font-size: 1.2rem;">✨</span> Small Gift • Big Change
            </div>
            <h1 class="font-bold mb-6 text-main hero-title" style="font-size: 4rem; letter-spacing: -0.03em; line-height: 1.1;">Building the <br><span class="gradient-text">Future Together</span></h1>
            <p class="text-main mx-auto" style="font-size: 1.1rem; line-height: 1.6; font-weight: 500; opacity: 0.8; text-align: center; width: 100%;">
                Your contributions help us maintain a safe, student-first marketplace. <br class="hidden lg:block"> 100% of donations go directly into platform improvements and community events.
            </p>
        </div>

        <div class="flex flex-col lg:flex-row gap-10 items-stretch">
            
            <!-- Left: High-Contrast Payment Form -->
            <div class="glass-panel" style="flex: 1.2; width: 100%; border-radius: 32px; border: 1px solid rgba(99, 102, 241, 0.15); box-shadow: 0 25px 60px rgba(0,0,0,0.08); background: white; padding: 2.5rem 2rem; display: flex; flex-direction: column; align-items: center;">
                <h3 class="mb-6 font-bold text-main uppercase tracking-widest text-center" style="font-size: 0.8rem; letter-spacing: 0.15em;">Select Your Amount</h3>
                
                <form action="create_stripe_session.php" method="POST" id="donation-form" style="width: 100%;">
                    <input type="hidden" name="payment_type" value="donation">
                    
                    <div class="grid grid-cols-3 gap-6 mb-8">
                        <div class="amount-pill active" data-amount="50"><span>₺50</span></div>
                        <div class="amount-pill" data-amount="100"><span>₺100</span></div>
                        <div class="amount-pill" data-amount="200"><span>₺200</span></div>
                    </div>

                    <div class="mb-0 text-center">
                        <label class="block mb-2 font-bold uppercase tracking-wider text-main" style="font-size: 0.75rem; opacity: 0.7;">Or enter a custom amount</label>
                        <div style="position: relative; max-width: 220px; margin: 0 auto;">
                            <span style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-main); font-weight: 900; font-size: 1.1rem; opacity: 0.3;">₺</span>
                            <input type="number" id="custom-amount" name="amount" step="0.01" min="1" value="50" 
                                   class="premium-input w-full text-center" 
                                   style="padding: 0.75rem 0.75rem 0.75rem 2.5rem; font-size: 1.35rem; font-weight: 900; border-radius: 14px; background: #f8fafc; border: 2px solid #e2e8f0; color: #0f172a;" 
                                   placeholder="0.00" required>
                        </div>
                    </div>

                    <div class="text-center" style="margin-top: 2rem;">
                        <button type="submit" class="cta-button" id="submit-btn">
                            Donate with Stripe
                        </button>
                        
                        <div class="flex items-center justify-center gap-4 mt-8" style="padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                            <div class="flex items-center gap-2 opacity-60">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="color: #22c55e;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                <span class="font-bold uppercase tracking-widest" style="font-size: 0.65rem;">Secure Encryption</span>
                            </div>
                            <div style="width: 1px; height: 12px; background: #cbd5e1;"></div>
                            <img src="https://upload.wikimedia.org/wikipedia/commons/b/ba/Stripe_Logo%2C_revised_2016.svg" height="15" alt="Stripe" style="opacity: 0.6;">
                        </div>
                    </div>
                </form>
            </div>

            <!-- Right: Impact & Trust -->
            <div style="flex: 0.8; display: flex; flex-direction: column; justify-content: center; gap: 1.5rem; padding: 0.5rem;">
                <div class="flex flex-col items-center text-center lg:flex-row lg:text-left gap-4">
                    <div class="perk-icon">🚀</div>
                    <div>
                        <h5 class="font-bold text-xl mb-1">100% Ad-Free</h5>
                        <p style="font-size: 0.95rem; color: #374151; line-height: 1.6;">We never sell your data or display intrusive ads. Your support keeps it that way.</p>
                    </div>
                </div>
                
                <div class="flex flex-col items-center text-center lg:flex-row lg:text-left gap-4">
                    <div class="perk-icon">🛡️</div>
                    <div>
                        <h5 class="font-bold text-xl mb-1">Community Safety</h5>
                        <p style="font-size: 0.95rem; color: #374151; line-height: 1.6;">Funding the verification tools and moderation that keep our campus marketplace safe.</p>
                    </div>
                </div>

                <div class="flex flex-col items-center text-center lg:flex-row lg:text-left gap-4">
                    <div class="perk-icon">💎</div>
                    <div>
                        <h5 class="font-bold text-xl mb-1">Supporter Status</h5>
                        <p style="font-size: 0.95rem; color: #374151; line-height: 1.6;">Every donor receives a permanent star on their profile and a place in the Hall of Fame.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$donors = getDonors($pdo, 12);
if (!empty($donors)):
?>
<div class="container mt-16 mb-24" style="max-width: 950px; padding: 0 1rem;">
    <div class="text-center mb-8">
        <div class="inline-flex items-center gap-2 font-bold" style="font-size: 0.85rem; color: var(--primary); letter-spacing: 0.08em; text-transform: uppercase;">
            <span style="animation: pulse 2s infinite;">❤️</span> Wall of Supporters
        </div>
        <h2 class="font-bold text-2xl mt-2 mb-2" style="color: var(--text-main); letter-spacing: -0.02em;">Community Hall of Fame</h2>
        <p class="text-muted" style="font-size: 0.9rem; opacity: 0.7;">Thank you to everyone who has supported CampusMarket.</p>
    </div>

    <div class="flex flex-wrap justify-center gap-8">
        <?php foreach ($donors as $donor): ?>
            <div class="donor-card flex flex-col items-center gap-2">
                <div style="position: relative; display: inline-block;">
                    <img src="<?php echo avatarUrl($donor['avatar']); ?>"
                         alt="<?php echo sanitize($donor['username']); ?>"
                         style="width: 64px; height: 64px; border-radius: 18px; border: 3px solid white; box-shadow: var(--shadow-md); object-fit: cover; transform: rotate(-3deg); transition: var(--transition); background: white;">
                    <div style="position: absolute; top: -6px; right: -6px; background: #fbbf24; color: white; width: 22px; height: 22px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 0.65rem; border: 2px solid white; box-shadow: var(--shadow-sm); z-index: 2;">★</div>
                </div>
                <p style="font-weight: 800; font-size: 0.8rem; color: var(--text-main);">@<?php echo sanitize($donor['username']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<style>
@media (max-width: 768px) {
    .hero-title { font-size: 2.25rem !important; }
}

.amount-pill {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    padding: 1rem 0.4rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.amount-pill:hover { background: white; border-color: var(--primary-light); transform: translateY(-4px); }
.amount-pill.active { 
    background: white; 
    border-color: var(--primary); 
    box-shadow: 0 10px 25px rgba(99, 102, 241, 0.12);
    transform: scale(1.05);
}
.amount-pill span { font-size: 1.2rem; font-weight: 900; color: #0f172a; }
.amount-pill.active span { color: var(--primary); }

.cta-button {
    background: linear-gradient(135deg, #635bff 0%, #8b5cf6 100%);
    color: white;
    padding: 0.9rem 2rem;
    font-size: 1.05rem;
    font-weight: 800;
    border: none;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 10px 25px rgba(99, 91, 255, 0.3), inset 0 1px 0 rgba(255,255,255,0.15);
    width: auto;
    min-width: 220px;
    letter-spacing: 0.01em;
}
.cta-button:hover {
    transform: translateY(-5px);
    box-shadow: 0 22px 45px rgba(99, 91, 255, 0.5), inset 0 1px 0 rgba(255,255,255,0.2);
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
}

.perk-icon {
    width: 60px;
    height: 60px;
    background: white;
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.03);
    transition: all 0.3s ease;
}
.perk-icon:hover { transform: rotate(8deg) scale(1.1); }

#custom-amount:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 5px rgba(99, 102, 241, 0.1); }
input::-webkit-outer-spin-button, input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

.donor-card { transition: var(--transition); cursor: default; }
.donor-card:hover { transform: translateY(-5px); }
.donor-card:hover img { transform: rotate(0deg); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pills = document.querySelectorAll('.amount-pill');
    const customInput = document.getElementById('custom-amount');
    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            customInput.value = this.dataset.amount;
            pills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
        });
    });
    customInput.addEventListener('input', function() {
        const val = parseFloat(this.value);
        pills.forEach(p => {
            if (parseFloat(p.dataset.amount) === val) p.classList.add('active');
            else p.classList.remove('active');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
