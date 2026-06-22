<?php
// pages/donate.php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = __('donate.page_title');
require_once __DIR__ . '/../includes/header.php';
?>

<div class="donation-page-wrapper" style="position: relative; overflow: hidden; min-height: 80vh; padding: calc(80px + 2rem) 0 4rem;">
    <div class="container" style="max-width: 1000px; padding: 0 1.5rem;">
        <div class="text-center mb-16">
            <div class="inline-flex items-center gap-2 mb-4 font-bold" style="font-size: 0.85rem; color: var(--primary); letter-spacing: 0.1em; text-transform: uppercase;">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> <?= __('donate.badge') ?>
            </div>
            <h1 class="font-bold mb-6 text-main hero-title" style="font-size: 4rem; letter-spacing: -0.03em; line-height: 1.1;"><?= __('donate.headline') ?></h1>
            <p class="text-main mx-auto" style="font-size: 1.1rem; line-height: 1.6; font-weight: 500; opacity: 0.8; text-align: center; width: 100%;">
                <?= __('donate.intro') ?>
            </p>
        </div>

        <div class="flex flex-col lg:flex-row gap-10 items-stretch">
            <div class="glass-panel" style="flex: 1.2; width: 100%; border-radius: 32px; border: 1px solid rgba(99, 102, 241, 0.15); box-shadow: 0 25px 60px rgba(0,0,0,0.08); background: white; padding: 2.5rem 2rem; display: flex; flex-direction: column; align-items: center;">
                <h3 class="mb-6 font-bold text-main uppercase tracking-widest text-center" style="font-size: 0.8rem; letter-spacing: 0.15em;"><?= __('donate.select_amount') ?></h3>
                
                <form action="create_stripe_session.php" method="POST" id="donation-form" style="width: 100%;">
                    <?php echo csrfTokenField(); ?>
                    <input type="hidden" name="payment_type" value="donation">
                    
                    <div class="grid grid-cols-3 gap-6 mb-8">
                        <div class="amount-pill active" data-amount="50"><span>₺50</span></div>
                        <div class="amount-pill" data-amount="100"><span>₺100</span></div>
                        <div class="amount-pill" data-amount="200"><span>₺200</span></div>
                    </div>

                    <div class="mb-0 text-center">
                        <label class="block mb-2 font-bold uppercase tracking-wider text-main" style="font-size: 0.75rem; opacity: 0.7;"><?= __('donate.custom_amount') ?></label>
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
                            <?= __('donate.submit') ?>
                        </button>
                        
                        <div class="flex items-center justify-center gap-4 mt-8" style="padding-top: 1rem; border-top: 1px solid #f1f5f9;">
                            <div class="flex items-center gap-2 opacity-60">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" style="color: #22c55e;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                <span class="font-bold uppercase tracking-widest" style="font-size: 0.65rem;"><?= __('donate.secure') ?></span>
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
                    <div class="perk-icon"><svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg></div>
                    <div>
                        <h5 class="font-bold text-xl mb-1"><?= __('donate.perk_adfree_title') ?></h5>
                        <p style="font-size: 0.95rem; color: #374151; line-height: 1.6;"><?= __('donate.perk_adfree_body') ?></p>
                    </div>
                </div>
                
                <div class="flex flex-col items-center text-center lg:flex-row lg:text-left gap-4">
                    <div class="perk-icon"><svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg></div>
                    <div>
                        <h5 class="font-bold text-xl mb-1"><?= __('donate.perk_safety_title') ?></h5>
                        <p style="font-size: 0.95rem; color: #374151; line-height: 1.6;"><?= __('donate.perk_safety_body') ?></p>
                    </div>
                </div>

                <div class="flex flex-col items-center text-center lg:flex-row lg:text-left gap-4">
                    <div class="perk-icon"><svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg></div>
                    <div>
                        <h5 class="font-bold text-xl mb-1"><?= __('donate.perk_supporter_title') ?></h5>
                        <p style="font-size: 0.95rem; color: #374151; line-height: 1.6;"><?= __('donate.perk_supporter_body') ?></p>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$donors = getDonors($pdo, 12);
$hallShowCta = false;
$hallSectionClass = 'hall-of-fame-section--below-form';
if (!empty($donors)) {
    include __DIR__ . '/../includes/partials/hall_of_fame_section.php';
}
?>

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
    background: var(--primary);
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
    background: color-mix(in srgb, var(--primary) 85%, black);
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
