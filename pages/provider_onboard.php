<?php
// pages/provider_onboard.php
// AI-Assisted Student Service Provider Onboarding (Controlled Pilot)

require_once '../includes/bootstrap.php';

// Admins are moderators only
if (isAdmin()) {
    setFlash('error', 'Administrators cannot register as service providers.');
    redirect(BASE_URL . 'admin/services.php');
}

$pageTitle = 'Become a Campus Service Provider — Pilot';
$pageDescription = 'Enroll as a student service provider. Describe what you do and our AI will categorize your skills and generate your launch listing.';

// Fetch service-type categories
$serviceCategories = [];
try {
    $serviceCategories = $pdo->query("SELECT * FROM categories WHERE type = 'service' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("[provider_onboard] Error fetching categories: " . $e->getMessage());
}

// Fetch existing provider profile if user is logged in
$currentUserId = isLoggedIn() ? currentUserId() : 0;
$existingProfile = null;
$existingListing = null;
if ($currentUserId > 0) {
    try {
        $pStmt = $pdo->prepare("SELECT * FROM provider_profiles WHERE user_id = ? LIMIT 1");
        $pStmt->execute([$currentUserId]);
        $existingProfile = $pStmt->fetch(PDO::FETCH_ASSOC);

        $lStmt = $pdo->prepare("SELECT * FROM products WHERE user_id = ? AND listing_type = 'service' AND status != 'deleted' ORDER BY id DESC LIMIT 1");
        $lStmt->execute([$currentUserId]);
        $existingListing = $lStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {}
}

include '../includes/header.php';
?>

<style>
/* Scoped Provider Onboard Styling matching CampusMarket Design System */
.provider-hero {
    text-align: center;
    max-width: 720px;
    margin: 0 auto 2.5rem;
}
.pilot-pill-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    background: var(--primary-light);
    color: var(--primary);
    border: 1px solid rgba(26, 127, 100, 0.2);
    border-radius: var(--radius-full);
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    margin-bottom: 0.85rem;
}
.step-number-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--primary);
    color: #ffffff;
    font-size: 0.82rem;
    font-weight: 700;
    flex-shrink: 0;
}
.skill-badge-tag {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.7rem;
    font-size: 0.8rem;
    font-weight: 600;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: var(--radius-full);
    border: 1px solid rgba(26, 127, 100, 0.2);
}
.onboard-card {
    background: var(--bg-surface);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-xl);
    padding: 2rem;
    box-shadow: var(--shadow-sm);
    margin-bottom: 2rem;
}
.inferred-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: var(--radius-full);
    background: rgba(99, 102, 241, 0.12);
    color: #4f46e5;
    letter-spacing: 0.4px;
    vertical-align: middle;
    margin-left: 0.35rem;
    display: inline-block;
}
.skill-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.32rem 0.75rem;
    font-size: 0.82rem;
    font-weight: 600;
    background: var(--primary-light);
    color: var(--primary);
    border-radius: var(--radius-full);
    border: 1px solid rgba(26, 127, 100, 0.25);
}
.skill-chip-remove {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: rgba(26, 127, 100, 0.15);
    font-size: 0.8rem;
    line-height: 1;
    color: var(--primary);
    transition: var(--transition);
}
.skill-chip-remove:hover {
    background: var(--primary);
    color: #ffffff;
}
.suggested-addition-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.2rem 0.65rem;
    font-size: 0.76rem;
    font-weight: 600;
    background: #ffffff;
    color: #4f46e5;
    border: 1px solid rgba(99, 102, 241, 0.3);
    border-radius: var(--radius-full);
    cursor: pointer;
    transition: var(--transition);
}
.suggested-addition-btn:hover {
    background: #4f46e5;
    color: #ffffff;
}
@media (max-width: 640px) {
    .onboard-card {
        padding: 1.25rem;
    }
}
</style>

<div class="min-h-screen pt-24 pb-20 relative">
    <div class="container" style="max-width: 860px;">

        <!-- Hero Section -->
        <div class="provider-hero">
            <span class="pilot-pill-badge">
                <span>🚀</span>
                <span>CAMPUS SERVICES PILOT</span>
            </span>
            <h1 class="page-hero-title mb-3" style="font-size: 2.25rem;">Offer Your Skills on Campus</h1>
            <p class="page-subtitle mb-0" style="font-size: 1.05rem; line-height: 1.6;">
                Turn your talents into student bookings. Describe what you do in your own words, and our AI assistant will automatically structure your skills, category, and launch listing.
            </p>
        </div>

        <?php if (!isLoggedIn()): ?>
            <!-- Login Requirement Notice -->
            <div class="onboard-card text-center py-12">
                <div style="font-size: 2.5rem; margin-bottom: 0.75rem;">👋</div>
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main);">Sign In to Enroll as a Provider</h3>
                <p class="text-muted mb-6" style="max-width: 480px; margin-left: auto; margin-right: auto; font-size: 0.95rem;">
                    Log in with your campus student account to register your provider profile and get your listing live during the pilot.
                </p>
                <div class="flex items-center justify-center gap-3 flex-wrap">
                    <a href="<?= BASE_URL ?>pages/login.php?redirect=pages/provider_onboard.php" class="btn btn-primary" style="padding: 0.75rem 2rem;">Log In to Enroll</a>
                    <a href="<?= BASE_URL ?>pages/register.php?redirect=pages/provider_onboard.php" class="btn btn-secondary" style="padding: 0.75rem 2rem;">Create Account</a>
                </div>
            </div>
        <?php else: ?>

            <form id="providerOnboardForm" method="POST" action="<?= BASE_URL ?>actions/enroll_provider.php" enctype="multipart/form-data">
                <?= csrfTokenField() ?>

                <?php if (!empty($existingListing)): ?>
                    <!-- Optional Draft Loader for Returning Providers -->
                    <div class="mb-4 p-3.5 rounded-xl flex items-center justify-between gap-3 flex-wrap" style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.25);">
                        <div class="flex items-center gap-2 text-sm text-main">
                            <span>ℹ️</span>
                            <span>You have an existing service listing: <strong><?= sanitize($existingListing['title']) ?></strong>.</span>
                        </div>
                        <button type="button" id="btnLoadExistingDraft" class="btn btn-secondary btn-sm" style="font-size: 0.8rem; padding: 0.35rem 0.85rem; border-radius: var(--radius-md); font-weight: 600;">
                            Load Previous Details
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Step 1: Tell Us What You Do -->
                <div class="onboard-card">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="step-number-badge">1</span>
                        <div>
                            <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text-main);">Tell Us What You Offer</h2>
                            <p class="text-muted mb-0" style="font-size: 0.85rem;">Write freely about the skills or tasks you can help fellow students with.</p>
                        </div>
                    </div>
                    <div class="form-group mb-5">
                        <label for="skills_input" class="block font-bold mb-2 text-main" style="font-size: 0.95rem;">
                            Skills & Services Description <span class="text-danger">*</span>
                        </label>
                        <textarea id="skills_input" name="raw_skills_input" rows="4" class="premium-input w-full" style="padding: 1rem; font-size: 0.95rem; border-radius: var(--radius-md); line-height: 1.5;" placeholder="Example: I'm a 3rd-year CS student offering Python scripting, web scraping, and bug fixing. I also help fellow students set up coding environments and debug course projects..."></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
                        <div>
                            <label for="provider_bio" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Student Headline / Bio</label>
                            <input type="text" id="provider_bio" name="bio" class="premium-input w-full" style="padding: 0.75rem 1rem;" placeholder="e.g. 3rd-year Architecture student & 3D visualizer" value="">
                        </div>
                        <div>
                            <label for="portfolio_link" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Portfolio / Social Link <span class="text-muted font-normal">(Optional)</span></label>
                            <input type="text" id="portfolio_link" name="portfolio_link" class="premium-input w-full" style="padding: 0.75rem 1rem;" placeholder="https://github.com/... or behance.net/..." value="">
                        </div>
                    </div>

                    <!-- KVKK / AI Consent -->
                    <div class="mb-4 p-3.5 rounded-xl" style="background: var(--bg-main); border: 1px solid var(--border-light);">
                        <label class="flex items-start gap-3 cursor-pointer mb-0" style="font-size: 0.88rem;">
                            <input type="checkbox" id="ai_consent" name="ai_consent" style="margin-top: 3px; flex-shrink: 0;">
                            <span class="text-muted" style="line-height: 1.45;">Your skills description will be processed by <strong>Google's Gemini AI</strong> to suggest categories and listing copy. By checking this box, you provide explicit consent to this automated data processing under KVKK/GDPR. <a href="<?= BASE_URL ?>pages/privacy.php" target="_blank" class="text-primary font-bold">Privacy Policy</a></span>
                        </label>
                    </div>

                    <!-- AI Trigger Button -->
                    <div class="flex items-center justify-between gap-4 flex-wrap pt-3 border-t" style="border-color: var(--border-light);">
                        <button type="button" id="btnAnalyzeAi" class="btn btn-primary" style="padding: 0.75rem 1.6rem; display: inline-flex; align-items: center; gap: 0.55rem; font-weight: 700; border-radius: var(--radius-lg);">
                            <span id="aiBtnText">Analyze & Auto-Categorize with AI</span>
                        </button>
                        <span id="aiStatus" class="text-muted small font-medium" style="display: none;">Analyzing skills and structuring listing...</span>
                    </div>
                </div>

                <input type="hidden" id="edited_fields_input" name="edited_fields" value="[]">
                <input type="hidden" id="existing_product_id" name="existing_product_id" value="">

                <!-- Step 2: Review & Customization -->
                <div id="aiResultsCard" class="onboard-card">
                    <div class="flex items-center justify-between gap-4 flex-wrap mb-6 pb-4 border-b" style="border-color: var(--border-light);">
                        <div class="flex items-center gap-3">
                            <span class="step-number-badge">2</span>
                            <div>
                                <h2 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: var(--text-main);">Review & Publish Your Service</h2>
                                <p class="text-muted mb-0" style="font-size: 0.85rem;">Fine-tune your generated listing details before publishing to the campus directory.</p>
                            </div>
                        </div>
                        <span id="aiEngineBadge" class="badge" style="background: var(--bg-main); color: var(--text-muted); font-size: 0.76rem; font-weight: 600; letter-spacing: 0.4px;">
                            AWAITING SKILLS
                        </span>
                    </div>

                    <!-- Extracted Skills Tags -->
                    <div class="mb-6 p-4 rounded-xl" style="background: var(--bg-main); border: 1px solid var(--border-light);">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block font-bold text-main mb-0" style="font-size: 0.88rem;">Skill Tags</label>
                            <span class="text-muted small" style="font-size: 0.76rem;">Type tag and press Enter</span>
                        </div>
                        <div id="skillsBadgesContainer" class="flex flex-wrap gap-2 mb-3">
                            <!-- Dynamic tag chips -->
                        </div>

                        <!-- AI Inferred Additions Section -->
                        <div id="suggestedAdditionsWrap" style="display: none; margin-bottom: 0.85rem; padding: 0.65rem 0.85rem; border-radius: var(--radius-md); background: rgba(99, 102, 241, 0.06); border: 1px dashed rgba(99, 102, 241, 0.3);">
                            <div class="text-xs font-bold mb-1.5" style="color: #4f46e5;">Suggested Additions (Not in your text — click to add if you teach/provide this):</div>
                            <div id="suggestedAdditionsContainer" class="flex flex-wrap gap-1.5"></div>
                        </div>

                        <div class="flex gap-2">
                            <input type="text" id="tagTextInput" class="premium-input flex-1" style="padding: 0.55rem 0.85rem; font-size: 0.85rem;" placeholder="Add a custom skill tag (e.g. Python, Calculus) and press Enter">
                            <button type="button" id="btnAddTagManual" class="btn btn-secondary" style="padding: 0.55rem 1rem; font-size: 0.85rem; font-weight: 600;">+ Add</button>
                        </div>
                        <input type="hidden" id="skillsInputHidden" name="skills" value="">
                        <small class="text-muted block mt-2" style="font-size: 0.78rem;">These tags appear on your listing card and help student buyers discover you.</small>
                    </div>

                    <!-- Category & Title -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
                        <div class="md:col-span-1">
                            <label for="category_id" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Category <span class="text-danger">*</span> <span id="badge_inferred_cat" class="inferred-badge" style="display:none;">AI SUGGESTED</span></label>
                            <select id="category_id" name="category_id" class="premium-input w-full" style="padding: 0.75rem 1rem; cursor: pointer;" required>
                                <option value="" disabled selected>Select service category...</option>
                                <?php foreach ($serviceCategories as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>">
                                        <?= htmlspecialchars(translateCategory($cat['name'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="service_title" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Listing Title <span class="text-danger">*</span> <span id="badge_inferred_title" class="inferred-badge" style="display:none;">AI SUGGESTED</span></label>
                            <input type="text" id="service_title" name="service_title" class="premium-input w-full" style="padding: 0.75rem 1rem;" required placeholder="e.g. Professional Graduation Portraits & Photo Editing" value="">
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group mb-5">
                        <label for="service_description" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Service Description <span class="text-danger">*</span> <span id="badge_inferred_desc" class="inferred-badge" style="display:none;">AI SUGGESTED</span></label>
                        <textarea id="service_description" name="service_description" rows="4" class="premium-input w-full" style="padding: 1rem; font-size: 0.95rem; line-height: 1.5;" required placeholder="Describe what clients receive, your process, and why they should choose you..."></textarea>
                    </div>

                    <!-- Pricing, Location & Turnaround -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-5 mb-5">
                        <div>
                            <label for="location_town" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Campus Location / Town <span class="text-danger">*</span></label>
                            <select id="location_town" name="location_town" class="premium-input w-full" style="padding: 0.75rem 1rem; cursor: pointer;" required>
                                <option value="" disabled selected>Select your campus town / location...</option>
                                <?php
                                $allowedTowns = ['remote', 'lefkosa', 'girne', 'gazimagusa', 'guzelyurt', 'lefke', 'iskele'];
                                foreach ($allowedTowns as $tSlug):
                                ?>
                                    <option value="<?= $tSlug ?>">
                                        <?= htmlspecialchars(formatLocationTown($tSlug)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="pricing_model" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Pricing Model <span class="text-danger">*</span></label>
                            <select id="pricing_model" name="pricing_model" class="premium-input w-full" style="padding: 0.75rem 1rem; cursor: pointer;" required>
                                <option value="" disabled selected>Select pricing model...</option>
                                <option value="hourly">Per Hour (₺ / hr)</option>
                                <option value="flat">Fixed Rate (₺ total)</option>
                            </select>
                        </div>
                        <div>
                            <label for="service_price" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Rate (₺ TRY) <span class="text-danger">*</span> <span id="badge_inferred_price" class="inferred-badge" style="display:none;">AI</span></label>
                            <input type="number" id="service_price" name="price" step="5" min="0" max="10000" class="premium-input w-full" style="padding: 0.75rem 1rem;" required placeholder="e.g. 150" value="">
                            <small id="aiPriceHint" class="text-muted" style="font-size: 0.78rem; display: none;">AI suggested — adjust to match your own rate</small>
                        </div>
                        <div id="delivery_days_wrap" style="display: none;">
                            <label for="delivery_days" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Delivery / Turnaround (Days)</label>
                            <input type="number" id="delivery_days" name="delivery_days" min="1" max="30" class="premium-input w-full" style="padding: 0.75rem 1rem;" placeholder="e.g. 2" value="">
                        </div>
                    </div>

                    <!-- Availability & Cover Image -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-6">
                        <div>
                            <label for="availability_status" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Current Availability</label>
                            <select id="availability_status" name="availability_status" class="premium-input w-full" style="padding: 0.75rem 1rem; cursor: pointer;">
                                <option value="available" selected>🟢 Available for new clients</option>
                                <option value="busy">🟡 Busy (Accepting with delay)</option>
                            </select>
                        </div>
                        <div>
                            <label for="cover_image" class="block font-bold mb-2 text-main" style="font-size: 0.88rem;">Cover Photo / Sample Work <span class="text-muted font-normal">(Optional)</span></label>
                            <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp" class="premium-input w-full" style="padding: 0.6rem 0.85rem;">
                        </div>
                    </div>

                    <!-- Final Submission -->
                    <div class="pt-6 border-t flex items-center justify-between gap-4 flex-wrap" style="border-color: var(--border-light);">
                        <div class="flex items-center gap-2 text-muted" style="font-size: 0.85rem;">
                            <span>Pilot listings are reviewed and approved by campus moderation before going live.</span>
                        </div>
                        <button type="submit" id="btnSubmitListing" class="btn btn-primary" style="padding: 0.85rem 2.25rem; font-weight: 700; font-size: 1rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);">
                            Submit for Review
                        </button>
                    </div>
                </div>

            </form>
        <?php endif; ?>

    </div>
</div>

<?php if (!empty($existingListing)): ?>
<script>
window.existingDraftData = <?= json_encode([
    'skills_input'        => $existingProfile['raw_skills_input'] ?? '',
    'bio'                 => $existingProfile['bio'] ?? '',
    'portfolio_link'      => $existingProfile['portfolio_link'] ?? '',
    'category_id'         => $existingListing['category_id'] ?? '',
    'service_title'       => $existingListing['title'] ?? '',
    'service_description' => $existingListing['description'] ?? '',
    'location_town'       => $existingListing['location_town'] ?? '',
    'pricing_model'       => $existingListing['pricing_model'] ?? 'hourly',
    'price'               => $existingListing['price'] ?? '',
    'delivery_days'       => $existingListing['delivery_days'] ?? '',
    'availability_status' => $existingListing['availability_status'] ?? 'available',
    'skills'              => is_array(json_decode($existingProfile['parsed_skills'] ?? '', true)) ? json_decode($existingProfile['parsed_skills'], true) : [],
    'product_id'          => (int)$existingListing['id']
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const csrfToken = <?= json_encode($csrfToken ?? ($_COOKIE['csrf_token'] ?? '')) ?>;

    // ── Optional: Load Previous Draft Details ────────────────────────────
    const btnLoadDraft = document.getElementById('btnLoadExistingDraft');
    if (btnLoadDraft && window.existingDraftData) {
        btnLoadDraft.addEventListener('click', () => {
            const d = window.existingDraftData;
            if (d.skills_input) document.getElementById('skills_input').value = d.skills_input;
            if (d.bio) document.getElementById('provider_bio').value = d.bio;
            if (d.portfolio_link) document.getElementById('portfolio_link').value = d.portfolio_link;
            if (d.category_id) document.getElementById('category_id').value = d.category_id;
            if (d.service_title) document.getElementById('service_title').value = d.service_title;
            if (d.service_description) document.getElementById('service_description').value = d.service_description;
            if (d.location_town) document.getElementById('location_town').value = d.location_town;
            if (d.pricing_model) {
                document.getElementById('pricing_model').value = d.pricing_model;
                toggleDelivery();
            }
            if (d.price) document.getElementById('service_price').value = d.price;
            if (d.delivery_days) document.getElementById('delivery_days').value = d.delivery_days;
            if (d.availability_status) document.getElementById('availability_status').value = d.availability_status;
            if (d.product_id) {
                const idEl = document.getElementById('existing_product_id');
                if (idEl) idEl.value = d.product_id;
            }
            if (Array.isArray(d.skills) && d.skills.length > 0) {
                currentTags = [...d.skills];
                syncHiddenTags();
                renderTags();
            }
            btnLoadDraft.textContent = '✓ Details Loaded';
            btnLoadDraft.disabled = true;
            btnLoadDraft.style.opacity = '0.7';
        });
    }

    // ── PostHog Page View ────────────────────────────────────────────────
    if (typeof posthog !== 'undefined' && posthog.capture) {
        posthog.capture('provider_onboard_viewed');
    }

    // ── Delivery days: hide when pricing model is hourly ──────────────────
    const pricingModelSel = document.getElementById('pricing_model');
    const deliveryWrap    = document.getElementById('delivery_days_wrap');
    function toggleDelivery() {
        if (!deliveryWrap) return;
        deliveryWrap.style.display = pricingModelSel?.value === 'flat' ? '' : 'none';
    }
    pricingModelSel?.addEventListener('change', toggleDelivery);
    toggleDelivery(); // run on load

    // ── KVKK consent gates the AI button ─────────────────────────────────
    const consentCheckbox = document.getElementById('ai_consent');
    const btnAnalyzeOuter = document.getElementById('btnAnalyzeAi');
    function syncAiBtn() {
        if (!btnAnalyzeOuter) return;
        const isConsentGiven = !!consentCheckbox?.checked;
        btnAnalyzeOuter.disabled = !isConsentGiven;
        btnAnalyzeOuter.style.opacity = isConsentGiven ? '1' : '0.45';
        btnAnalyzeOuter.style.cursor = isConsentGiven ? 'pointer' : 'not-allowed';
        btnAnalyzeOuter.title = isConsentGiven ? '' : 'Please check the consent box directly above first to enable AI analysis';
    }
    consentCheckbox?.addEventListener('change', syncAiBtn);
    syncAiBtn(); // run on load (unticked by default)

    // ── Track user manual edits to report signals to PostHog ─────────────
    const userEdited = {
        category_id: false,
        service_title: false,
        service_description: false,
        pricing_model: false,
        service_price: false,
        delivery_days: false,
        location_town: false,
        skills: false
    };

    const monitoredFields = ['category_id', 'service_title', 'service_description', 'pricing_model', 'service_price', 'delivery_days', 'location_town'];
    monitoredFields.forEach(id => {
        const el = document.getElementById(id);
        el?.addEventListener('input', () => { userEdited[id] = true; });
        el?.addEventListener('change', () => { userEdited[id] = true; });
    });

    // ── Interactive Tag Chips Manager ─────────────────────────────────────
    const skillsHiddenInput = document.getElementById('skillsInputHidden');
    const skillsBadgesContainer = document.getElementById('skillsBadgesContainer');
    const tagTextInput = document.getElementById('tagTextInput');
    const btnAddTagManual = document.getElementById('btnAddTagManual');
    const suggestedAdditionsWrap = document.getElementById('suggestedAdditionsWrap');
    const suggestedAdditionsContainer = document.getElementById('suggestedAdditionsContainer');

    let currentTags = [];
    if (skillsHiddenInput && skillsHiddenInput.value.trim()) {
        currentTags = skillsHiddenInput.value.split(',')
            .map(s => s.trim().replace(/^#+/, ''))
            .filter(Boolean);
    }

    function syncHiddenTags() {
        if (skillsHiddenInput) {
            skillsHiddenInput.value = currentTags.join(', ');
        }
    }

    function renderTags() {
        if (!skillsBadgesContainer) return;
        skillsBadgesContainer.innerHTML = '';
        if (currentTags.length === 0) {
            const emptyPrompt = document.createElement('span');
            emptyPrompt.className = 'text-muted small';
            emptyPrompt.style.fontSize = '0.82rem';
            emptyPrompt.textContent = 'No tags added yet. Type below or run AI analysis.';
            skillsBadgesContainer.appendChild(emptyPrompt);
            return;
        }

        currentTags.forEach((tag, idx) => {
            const chip = document.createElement('span');
            chip.className = 'skill-chip';
            chip.innerHTML = `<span>#${escapeHtml(tag)}</span><span class="skill-chip-remove" title="Remove tag" data-index="${idx}">&times;</span>`;
            skillsBadgesContainer.appendChild(chip);
        });

        // Attach removal listeners
        skillsBadgesContainer.querySelectorAll('.skill-chip-remove').forEach(el => {
            el.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.getAttribute('data-index'), 10);
                if (!isNaN(index)) {
                    currentTags.splice(index, 1);
                    userEdited.skills = true;
                    syncHiddenTags();
                    renderTags();
                }
            });
        });
    }

    function addTag(raw) {
        const clean = raw.trim().replace(/^#+/, '');
        if (clean.length < 2) return;
        const lower = clean.toLowerCase();
        if (!currentTags.some(t => t.toLowerCase() === lower)) {
            currentTags.push(clean);
            userEdited.skills = true;
            syncHiddenTags();
            renderTags();
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Initial render
    renderTags();

    tagTextInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTag(tagTextInput.value);
            tagTextInput.value = '';
        }
    });

    btnAddTagManual?.addEventListener('click', () => {
        if (tagTextInput) {
            addTag(tagTextInput.value);
            tagTextInput.value = '';
            tagTextInput.focus();
        }
    });

    // ── AI Classification Logic ──────────────────────────────────────────
    const btnAnalyze = document.getElementById('btnAnalyzeAi');
    const skillsInput = document.getElementById('skills_input');
    const aiStatus = document.getElementById('aiStatus');
    const aiIcon = document.getElementById('aiIcon');
    const aiBtnText = document.getElementById('aiBtnText');
    const aiEngineBadge = document.getElementById('aiEngineBadge');

    const badgeCat = document.getElementById('badge_inferred_cat');
    const badgeTitle = document.getElementById('badge_inferred_title');
    const badgeDesc = document.getElementById('badge_inferred_desc');
    const badgePrice = document.getElementById('badge_inferred_price');

    if (btnAnalyze && skillsInput) {
        btnAnalyze.addEventListener('click', async () => {
            if (!consentCheckbox?.checked) {
                alert('Please tick the consent box above first.');
                consentCheckbox?.focus();
                return;
            }

            const text = skillsInput.value.trim();
            if (text.length < 5) {
                alert('Please enter a brief description of what skills or services you can provide.');
                skillsInput.focus();
                return;
            }

            // Confirm before overwriting if student manually edited any fields
            const editedFieldKeys = Object.keys(userEdited).filter(k => userEdited[k]);
            let overwriteAll = true;
            if (editedFieldKeys.length > 0) {
                overwriteAll = confirm("You have manually edited some listing details (" + editedFieldKeys.join(', ') + ").\n\n• Click OK to overwrite with new AI suggestions\n• Click Cancel to keep your edits and only update unedited fields");

                if (typeof posthog !== 'undefined' && posthog.capture) {
                    posthog.capture('provider_ai_reanalyze_prompted', {
                        edited_fields: editedFieldKeys,
                        overwritten: overwriteAll
                    });
                }
            }

            // PostHog event: AI click
            if (typeof posthog !== 'undefined' && posthog.capture) {
                posthog.capture('provider_ai_analyze_clicked', { text_length: text.length });
            }

            // UI loading state
            btnAnalyze.disabled = true;
            aiIcon.textContent = '⏳';
            aiBtnText.textContent = 'Analyzing...';
            if (aiStatus) {
                aiStatus.style.display = 'inline';
            }

            try {
                const response = await fetch('<?= BASE_URL ?>api/ai_classify_service.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        skills_text: text,
                        bio: document.getElementById('provider_bio')?.value || ''
                    })
                });

                const json = await response.json();
                if (response.ok && json && json.ok && json.data) {
                    const data = json.data;

                    // Update engine badge accurately
                    if (aiEngineBadge) {
                        if (data.engine === 'fallback') {
                            aiEngineBadge.textContent = '⚡ KEYWORD MATCHED';
                            aiEngineBadge.style.background = 'rgba(245, 158, 11, 0.15)';
                            aiEngineBadge.style.color = '#b45309';
                            aiEngineBadge.title = 'Categorized using keyword patterns';
                        } else {
                            aiEngineBadge.textContent = '✨ AI STRUCTURED';
                            aiEngineBadge.style.background = 'var(--primary-light)';
                            aiEngineBadge.style.color = 'var(--primary)';
                            aiEngineBadge.title = 'Generated with ' + (data.engine || 'Gemini AI');
                        }
                    }

                    // 1. Category
                    if (data.category_id && (overwriteAll || !userEdited.category_id)) {
                        const catSelect = document.getElementById('category_id');
                        if (catSelect) {
                            catSelect.value = data.category_id;
                            if (badgeCat) badgeCat.style.display = 'inline-block';
                        }
                    }

                    // 2. Title & Description
                    if (data.title && (overwriteAll || !userEdited.service_title)) {
                        const titleEl = document.getElementById('service_title');
                        if (titleEl) {
                            titleEl.value = data.title;
                            if (badgeTitle) badgeTitle.style.display = 'inline-block';
                        }
                    }
                    if (data.description && (overwriteAll || !userEdited.service_description)) {
                        const descEl = document.getElementById('service_description');
                        if (descEl) {
                            descEl.value = data.description;
                            if (badgeDesc) badgeDesc.style.display = 'inline-block';
                        }
                    }

                    // 3. Pricing & Turnaround
                    if (data.pricing_model && (overwriteAll || !userEdited.pricing_model)) {
                        const modelEl = document.getElementById('pricing_model');
                        if (modelEl) {
                            modelEl.value = data.pricing_model;
                            toggleDelivery();
                        }
                    }
                    if (data.suggested_price && (overwriteAll || !userEdited.service_price)) {
                        const priceEl   = document.getElementById('service_price');
                        const priceHint = document.getElementById('aiPriceHint');
                        if (priceEl) {
                            priceEl.value = data.suggested_price;
                            if (badgePrice) badgePrice.style.display = 'inline-block';
                        }
                        if (priceHint) priceHint.style.display = 'block';
                    }
                    if (data.delivery_days && (overwriteAll || !userEdited.delivery_days)) {
                        const delEl = document.getElementById('delivery_days');
                        if (delEl) delEl.value = data.delivery_days;
                    }

                    // 4. Bio (only fill if empty)
                    if (data.suggested_bio) {
                        const bioEl = document.getElementById('provider_bio');
                        if (bioEl && !bioEl.value.trim()) {
                            bioEl.value = data.suggested_bio;
                        }
                    }

                    // 5. Skills tags (Strictly from description)
                    if (Array.isArray(data.skills) && data.skills.length > 0 && (overwriteAll || !userEdited.skills)) {
                        currentTags = [...data.skills];
                        syncHiddenTags();
                        renderTags();
                    }

                    // 6. Inferred additions (Transparent opt-in chips)
                    if (Array.isArray(data.suggested_additions) && data.suggested_additions.length > 0 && suggestedAdditionsContainer && suggestedAdditionsWrap) {
                        suggestedAdditionsContainer.innerHTML = '';
                        let shownCount = 0;
                        data.suggested_additions.forEach(suggestion => {
                            if (currentTags.some(t => t.toLowerCase() === suggestion.toLowerCase())) return;
                            shownCount++;
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'suggested-addition-btn';
                            btn.innerHTML = `+ ${escapeHtml(suggestion)}`;
                            btn.addEventListener('click', () => {
                                addTag(suggestion);
                                btn.remove();
                                if (suggestedAdditionsContainer.children.length === 0) {
                                    suggestedAdditionsWrap.style.display = 'none';
                                }
                            });
                            suggestedAdditionsContainer.appendChild(btn);
                        });
                        suggestedAdditionsWrap.style.display = (shownCount > 0) ? 'block' : 'none';
                    } else if (suggestedAdditionsWrap) {
                        suggestedAdditionsWrap.style.display = 'none';
                    }

                    // If user confirmed overwriting, reset edit tracking
                    if (overwriteAll) {
                        Object.keys(userEdited).forEach(k => { userEdited[k] = false; });
                    }

                    // PostHog event: AI success
                    if (typeof posthog !== 'undefined' && posthog.capture) {
                        posthog.capture('provider_ai_analyze_succeeded', {
                            engine: data.engine || 'unknown',
                            category_id: data.category_id,
                            pricing_model: data.pricing_model,
                            skills_count: currentTags.length,
                            additions_count: (data.suggested_additions || []).length
                        });
                    }

                    // Smooth scroll to results
                    document.getElementById('aiResultsCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } else {
                    const errMsg = json?.error || 'Could not analyze with AI. You can still fill in the details manually below.';
                    alert(errMsg);
                    if (typeof posthog !== 'undefined' && posthog.capture) {
                        posthog.capture('provider_ai_analyze_failed', {
                            error: errMsg,
                            status: response.status
                        });
                    }
                }
            } catch (err) {
                console.error('AI classify error:', err);
                alert('AI assistant is momentarily busy. Please fill in your service details manually.');
                if (typeof posthog !== 'undefined' && posthog.capture) {
                    posthog.capture('provider_ai_analyze_failed', {
                        error: err.message || 'network_error',
                        status: 0
                    });
                }
            } finally {
                syncAiBtn();
                aiIcon.textContent = '✨';
                aiBtnText.textContent = 'Re-Analyze with AI';
                if (aiStatus) {
                    aiStatus.style.display = 'none';
                }
            }
        });
    }

    // ── Form submit tracking & validation gate ────────────────────────────
    const form = document.getElementById('providerOnboardForm');
    const editedFieldsHidden = document.getElementById('edited_fields_input');
    form?.addEventListener('submit', (e) => {
        // If HTML5 browser validation fails, do NOT record submission
        if (form.checkValidity && !form.checkValidity()) {
            return;
        }

        const editedFieldNames = Object.keys(userEdited).filter(k => userEdited[k]);
        if (editedFieldsHidden) {
            editedFieldsHidden.value = JSON.stringify(editedFieldNames);
        }

        if (typeof posthog !== 'undefined' && posthog.capture) {
            posthog.capture('provider_onboard_submitted', {
                edited_fields: editedFieldNames,
                edited_count: editedFieldNames.length,
                category_id: document.getElementById('category_id')?.value,
                pricing_model: document.getElementById('pricing_model')?.value,
                location_town: document.getElementById('location_town')?.value
            });
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
