<?php
// pages/create_listing.php
require_once '../includes/bootstrap.php';
require_once '../includes/ai_moderator.php';
require_once '../includes/listing_moderation.php';
requireLogin();

// Admins are moderators only — they cannot create listings
if (isAdmin()) {
    setFlash('error', 'Administrators cannot create listings. Use the Admin Panel to manage the marketplace.');
    redirect(BASE_URL . 'admin/index.php');
}

$success = false;
$error = '';
$createdProductId = 0;
$createdProductStatus = '';
$createdListingMeta = [];

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $title          = sanitize($_POST['title']);
    $categoryId     = (int)($_POST['category_id'] ?? 0);
    $price          = (float)$_POST['price'];
    $priceCurrency  = strtoupper(trim((string)($_POST['price_currency'] ?? DEFAULT_PRODUCT_CURRENCY)));
    if (!array_key_exists($priceCurrency, PRODUCT_CURRENCIES)) {
        $priceCurrency = DEFAULT_PRODUCT_CURRENCY;
    }
    $condition      = sanitize($_POST['condition'] ?? '');
    $listingType    = in_array($_POST['listing_type'] ?? 'product', ['product', 'service']) ? ($_POST['listing_type'] ?? 'product') : 'product';
    $pricingModel   = in_array($_POST['pricing_model'] ?? 'flat', ['flat', 'hourly']) ? ($_POST['pricing_model'] ?? 'flat') : 'flat';
    $description    = sanitize($_POST['description']);
    $locationTown     = strtolower(trim((string)($_POST['location_town'] ?? '')));
    $customLocation   = trim(sanitize($_POST['custom_location'] ?? ''));
    $userId         = currentUserId();
    $ownerContact   = isAgent() ? parseManagedOwnerContactFromRequest($_POST) : null;
    // Collect manually-selected tag IDs from the pill UI
    $selectedTagIds = array_unique(array_filter(array_map('intval', $_POST['tags'] ?? [])));
    if ($listingType === 'service') {
        $firstServiceCat = $pdo->query("SELECT id FROM categories WHERE type = 'service' ORDER BY id ASC LIMIT 1")->fetchColumn();
        $categoryId = $firstServiceCat ? (int)$firstServiceCat : 11;
        $selectedCategoryIds = [$categoryId];
    } else {
        $selectedCategoryIds = array_values(array_unique(array_filter(array_map('intval', $_POST['category_ids'] ?? []))));
        $primaryCategoryId = (int)($_POST['category_id'] ?? 0);
        if ($primaryCategoryId <= 0 && !empty($selectedCategoryIds)) {
            $primaryCategoryId = (int)$selectedCategoryIds[0];
        }
        if ($primaryCategoryId > 0) {
            $selectedCategoryIds[] = $primaryCategoryId;
        }
        $selectedCategoryIds = array_values(array_unique(array_filter($selectedCategoryIds)));
        $categoryId = $primaryCategoryId > 0 ? $primaryCategoryId : ($selectedCategoryIds[0] ?? 0);
    }

    // Validate Title and Image Presence
    if (empty($title) || mb_strlen($title) < 3) {
        $error = __('create_listing.title_required');
    } elseif (mb_strlen($title) > 100) {
        $error = __('create_listing.title_too_long');
    } elseif (empty($_FILES['images']['name'][0]) || $_FILES['images']['error'][0] === UPLOAD_ERR_NO_FILE) {
        $error = __('create_listing.image_required');
    } elseif ($listingType === 'product' && ($categoryId <= 0 || empty($selectedCategoryIds))) {
        $error = __('create_listing.select_category');
    }

    if ($listingType === 'service' && ($locationTown === '' || !isValidLocationTown($locationTown))) {
        // Default services to 'remote' when not specified — most student services are online-capable
        $locationTown = 'remote';
    }

    if (!$error && $listingType === 'product' && !isValidLocationTown($locationTown)) {
        $error = __('create_listing.town_required');
    } elseif (!$error && $locationTown === 'other' && empty($customLocation)) {
        $error = __('create_listing.custom_location_required');
    } elseif (!$error && $locationTown === 'other' && mb_strlen($customLocation) > 100) {
        $error = 'Custom location cannot exceed 100 characters.';
    }

    if (!$error && isAgent()) {
        $ownerErrors = validateManagedOwnerContact($ownerContact ?? [], true);
        if (!empty($ownerErrors)) {
            $error = reset($ownerErrors);
        }
    }

    if (!$error) {
        $duplicate = findSellerDuplicateListing($pdo, $userId, $title);
        if ($duplicate) {
            $error = listingModerationDuplicateMessage($duplicate);
        }
    }

    if (!$error) {
        // Collect Image Data for Moderation
        $imagesData = [];
        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($i >= MAX_IMAGES) break;
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp = $files['tmp_name'][$i];
                    $mime = $files['type'][$i];
                    if (strpos($mime, 'image/') === 0) {
                        $base64 = base64_encode(file_get_contents($tmp));
                        $imagesData[] = [
                            'mime' => $mime,
                            'base64' => $base64
                        ];
                    }
                }
            }
        }

        try {
            // Call AI Moderation Before Database Changes
            $aiResult = aiModerateListing($title, $description, $imagesData);

            if ($aiResult['is_blurry']) {
                $error = listingModerationBlurryMessage($aiResult);
            } else {
                $pdo->beginTransaction();

                // 1. Insert Product
                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                $conditionQuote = ($driver === 'mysql') ? '`condition`' : '"condition"';
                $conditionValue = ($listingType === 'service') ? null : $condition;
                $statusValue = ($listingType === 'service') ? 'pending_approval' : 'active';
                $sellerMarketplace = function_exists('getUserUniversityAndCountry') ? getUserUniversityAndCountry($pdo, $userId) : null;
                $sellerCountry = $sellerMarketplace['country_code'] ?? 'TR';
                $sellerUniversityId = $sellerMarketplace['university_id'] ?? null;

                $stmt = $pdo->prepare("INSERT INTO products (user_id, category_id, title, description, price, price_currency, {$conditionQuote}, status, listing_type, pricing_model, location_town, custom_location, country_code, university_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $categoryId, $title, $description, $price, $priceCurrency, $conditionValue, $statusValue, $listingType, $pricingModel, $locationTown, ($locationTown === 'other' ? $customLocation : null), $sellerCountry, $sellerUniversityId]);
                $productId = $pdo->lastInsertId();

                if (!empty($selectedCategoryIds)) {
                    $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $categoryInsertSql = ($driverName === 'pgsql')
                        ? 'INSERT INTO product_categories (product_id, category_id, is_primary) VALUES (?, ?, ?) ON CONFLICT (product_id, category_id) DO UPDATE SET is_primary = EXCLUDED.is_primary'
                        : 'INSERT INTO product_categories (product_id, category_id, is_primary) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary)';
                    $categoryInsert = $pdo->prepare($categoryInsertSql);
                    foreach ($selectedCategoryIds as $catId) {
                        try {
                            $categoryInsert->execute([$productId, (int)$catId, ((int)$catId === $categoryId) ? 1 : 0]);
                        } catch (PDOException $e) {
                            if (stripos($e->getMessage(), 'product_categories') !== false || stripos($e->getMessage(), 'does not exist') !== false || stripos($e->getMessage(), 'relation') !== false) {
                                ensureProductCategoriesTable($pdo);
                                $categoryInsert = $pdo->prepare($categoryInsertSql);
                                $categoryInsert->execute([$productId, (int)$catId, ((int)$catId === $categoryId) ? 1 : 0]);
                            } else {
                                throw $e;
                            }
                        }
                    }
                }

                if (isAgent() && $ownerContact) {
                    if (!saveManagedListingContact($pdo, (int)$productId, $ownerContact)) {
                        throw new Exception(__('agent.contact_save_failed'));
                    }
                }

                // 2. Handle Image Uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $files = $_FILES['images'];
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($i >= MAX_IMAGES) break; // Limit per listing

                        $fileData = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i]
                        ];

                        $upload = handleUpload($fileData, 'products/');
                        if ($upload['success']) {
                            $isPrimary = ($i === 0);
                            $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (:pid, :path, :primary)");
                            $stmtImg->bindValue(':pid', $productId, PDO::PARAM_INT);
                            $stmtImg->bindValue(':path', $upload['path'], PDO::PARAM_STR);
                            $stmtImg->bindValue(':primary', $isPrimary, PDO::PARAM_BOOL);
                            $stmtImg->execute();
                        } else {
                            throw new Exception("Image upload failed: " . $upload['error']);
                        }
                    }
                }

                if (aiModeratorShouldAutoApprove($aiResult)) {
                    $status = 'active';
                } else {
                    $status = 'pending_approval';
                    $sellerNote = listingModerationSellerFacingReason($aiResult);
                    $stmtUpdate = $pdo->prepare("UPDATE products SET status = :status WHERE id = :pid");
                    $stmtUpdate->execute([':status' => $status, ':pid' => $productId]);
                    listingModerationSaveNote($pdo, (int)$productId, $sellerNote);
                    notifyAdminsPendingListing(
                        $pdo,
                        (int)$productId,
                        $title,
                        $sellerNote
                    );
                    notifySellerPendingListing(
                        $pdo,
                        (int)$userId,
                        (int)$productId,
                        $title,
                        $sellerNote
                    );
                }

                $tagsToSave = [];
                if (!empty($selectedTagIds)) {
                    // User explicitly picked tags from the pill UI
                    $tagsToSave = $selectedTagIds;
                } elseif ($status === 'active' && !empty($aiResult['tags'])) {
                    // Auto-approved with no manual selection: resolve AI tag names → IDs
                    $placeholders = implode(',', array_fill(0, count($aiResult['tags']), '?'));
                    $nameStmt = $pdo->prepare("SELECT id FROM tags WHERE name IN ($placeholders)");
                    $nameStmt->execute($aiResult['tags']);
                    $tagsToSave = $nameStmt->fetchAll(PDO::FETCH_COLUMN);
                }

                // Process user-suggested new tags
                $newTagNames = array_slice(array_unique(array_filter(
                    array_map(fn($t) => mb_strtolower(trim(sanitize((string)$t))), $_POST['new_tags'] ?? [])
                )), 0, 5);

                foreach ($newTagNames as $newName) {
                    if (mb_strlen($newName) < 2 || mb_strlen($newName) > 40) continue;
                    $slug = strtolower(preg_replace('/[^a-z0-9\-]+/u', '-', $newName));
                    $slug = trim($slug, '-');
                    if ($slug === '') continue;

                    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $existing = $pdo->prepare("SELECT id FROM tags WHERE slug = ? OR LOWER(name) = ? LIMIT 1");
                    $existing->execute([$slug, $newName]);
                    $newTagId = $existing->fetchColumn();

                    if (!$newTagId) {
                        $insertTagSql = ($driver === 'pgsql')
                            ? "INSERT INTO tags (name, slug, status, created_by) VALUES (?, ?, 'active', ?) RETURNING id"
                            : "INSERT INTO tags (name, slug, status, created_by) VALUES (?, ?, 'active', ?)";
                        $tagStmt = $pdo->prepare($insertTagSql);
                        try {
                            $tagStmt->execute([$newName, $slug, $userId]);
                            $newTagId = ($driver === 'pgsql') ? $tagStmt->fetchColumn() : $pdo->lastInsertId();
                        } catch (Throwable $eT) {
                            $existing->execute([$slug, $newName]);
                            $newTagId = $existing->fetchColumn();
                        }
                    }

                    if ($newTagId) {
                        $tagsToSave[] = (int)$newTagId;
                    }
                }
                $tagsToSave = array_values(array_unique(array_map('intval', $tagsToSave)));

                if (!empty($tagsToSave)) {
                    $driverName = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                    $tagSql = ($driverName === 'pgsql')
                        ? "INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?) ON CONFLICT DO NOTHING"
                        : "INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)";
                    $tagInsert = $pdo->prepare($tagSql);
                    foreach ($tagsToSave as $tid) {
                        try {
                            $tagInsert->execute([$productId, (int)$tid]);
                        } catch (Throwable $eTag) {
                            error_log('[create_listing] tag insert warning: ' . $eTag->getMessage());
                        }
                    }
                }
                
                $pdo->commit();
                redirect(BASE_URL . 'pages/create_listing.php?created=' . (int)$productId);
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msg = $e->getMessage();
            error_log('[create_listing] ' . $msg);
            logSystemError($pdo, 'create_listing', $msg, $e, $userId);

            // Map known technical errors to specific, user-friendly messages
            if (stripos($msg, 'File too large') !== false) {
                $error = __('create_listing.error_file_too_large');
            } elseif (stripos($msg, 'Invalid file type') !== false || stripos($msg, 'Invalid file extension') !== false) {
                $error = __('create_listing.error_invalid_file_type');
            } elseif (stripos($msg, 'not a valid image') !== false) {
                $error = __('create_listing.error_invalid_image');
            } elseif (stripos($msg, 'Image upload failed') !== false || stripos($msg, 'Upload failed') !== false || stripos($msg, 'Upload error code') !== false) {
                $error = __('create_listing.error_upload_failed');
            } else {
                // Return exact detailed error message so users & admins know what went wrong
                $error = __('create_listing.error_msg', ['error' => $msg]);
            }
        }
    }
}

// Determine if this is a service listing (from URL or previous POST)
$isServiceMode = ($_GET['type'] ?? '') === 'service' || ($_POST['listing_type'] ?? '') === 'service';

// Fetch product categories only (services have their own category tree)
$categories = $pdo->query("SELECT * FROM categories WHERE type = 'product' ORDER BY name ASC")->fetchAll();
$serviceCategories = $pdo->query("SELECT * FROM categories WHERE type = 'service' ORDER BY name ASC")->fetchAll();
$allTags    = $pdo->query("SELECT id, name, slug FROM tags ORDER BY name ASC")->fetchAll();
$userHomeTownDetails = isLoggedIn() ? getUserHomeTownDetails((int)currentUserId()) : null;
$defaultTown = $userHomeTownDetails['town'] ?? '';
$defaultCustomLocation = $userHomeTownDetails['custom'] ?? '';
$selectedTown = $_POST['location_town'] ?? $defaultTown;
$selectedCustomLocation = $_POST['custom_location'] ?? $defaultCustomLocation;
$prevTags   = array_map('intval', $_POST['tags'] ?? []);
$prevCategoryIds = array_values(array_unique(array_filter(array_map('intval', $_POST['category_ids'] ?? []))));
if (empty($prevCategoryIds) && !empty($_POST['category_id'])) {
    $prevCategoryIds[] = (int)$_POST['category_id'];
}

// Post/Redirect/Get success screen — load from DB so it works even if session is flaky on mobile.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['created'])) {
    $createdId = (int)$_GET['created'];
    if ($createdId > 0) {
        $createdStmt = $pdo->prepare("
            SELECT id, status, category_id, price, moderation_note
            FROM products
            WHERE id = :id AND user_id = :uid
            LIMIT 1
        ");
        try {
            $createdStmt->execute([':id' => $createdId, ':uid' => currentUserId()]);
            $createdRow = $createdStmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $createdStmt = $pdo->prepare("
                SELECT id, status, category_id, price
                FROM products
                WHERE id = :id AND user_id = :uid
                LIMIT 1
            ");
            $createdStmt->execute([':id' => $createdId, ':uid' => currentUserId()]);
            $createdRow = $createdStmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($createdRow) {
            $success = true;
            $createdProductId = (int)$createdRow['id'];
            $createdProductStatus = (string)$createdRow['status'];
            $createdListingMeta = [
                'category_id' => (int)($createdRow['category_id'] ?? 0),
                'price' => (float)($createdRow['price'] ?? 0),
                'moderation_note' => trim((string)($createdRow['moderation_note'] ?? '')),
            ];
        }
    }
}

$pageTitle = __('create_listing.page_title');
include '../includes/header.php';
?>

<?php if ($success && $createdProductId > 0): ?>
<?php
    $createdCategoryName = '';
    if (!empty($createdListingMeta['category_id'])) {
        foreach ($categories as $cat) {
            if ((int)($cat['id'] ?? 0) === (int)$createdListingMeta['category_id']) {
                $createdCategoryName = (string)($cat['name'] ?? '');
                break;
            }
        }
    }
?>
<?php if (!IS_LOCALHOST): ?>
<script>
    if (typeof posthog !== 'undefined') {
        posthog.capture('listing_created', {
            category: <?php echo json_encode($createdCategoryName); ?>,
            price: <?php echo json_encode((float)($createdListingMeta['price'] ?? 0)); ?>
        });
    }
</script>
<?php endif; ?>
<div class="container mt-24 mb-20">
    <div class="glass-panel" style="max-width: 760px; margin: 0 auto; padding: 2rem; border-radius: var(--radius-xl); text-align: center;">
        <h1 class="page-hero-title mb-2"><?= __('create_listing.success_msg') ?></h1>
        <?php if ($createdProductStatus === 'active'): ?>
        <p class="text-muted mb-6">Your listing is live. Would you like to promote it now?</p>
        <div class="flex justify-center gap-4 flex-wrap">
            <a class="btn btn-primary" href="promotions.php?product_id=<?= (int)$createdProductId ?>&new_listing=1" style="padding: 0.8rem 1.4rem; border-radius: var(--radius-lg);">
                Yes, promote it
            </a>
            <a class="btn btn-secondary" href="product.php?id=<?= (int)$createdProductId ?>" style="padding: 0.8rem 1.4rem; border-radius: var(--radius-lg);">
                No, view my listing
            </a>
        </div>
        <?php else: ?>
        <?php $pendingNote = trim((string)($createdListingMeta['moderation_note'] ?? '')); ?>
        <p class="text-muted mb-4" style="font-size: 1.05rem; line-height: 1.6;">
            <?= __('create_listing.moderation_pending_intro') ?>
        </p>
        <?php if ($pendingNote !== ''): ?>
        <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: var(--radius-lg); padding: 1rem 1.25rem; margin-bottom: 1.5rem; text-align: left;">
            <p class="mb-1 font-bold" style="color: var(--text-main); font-size: 0.9rem;"><?= __('create_listing.moderation_pending_reason_label') ?></p>
            <p class="mb-0 text-muted" style="line-height: 1.55;"><?= sanitize($pendingNote) ?></p>
        </div>
        <?php endif; ?>
        <a class="btn btn-secondary" href="product.php?id=<?= (int)$createdProductId ?>" style="padding: 0.8rem 1.4rem; border-radius: var(--radius-lg);">
            <?= __('create_listing.moderation_view_listing') ?>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php return; ?>
<?php endif; ?>

<div class="container relative mt-24 mb-20 flex justify-center">
    <!-- Decorative elements -->



    <div class="w-full max-w-3xl" style="min-width: 0;">
        <div class="text-center mb-8">
            <h1 class="mb-2 page-hero-title"><?= __('create_listing.title') ?></h1>
            <p class="page-subtitle"><?= __('create_listing.subtitle') ?></p>
        </div>

        <?php if ($error): ?>
            <?= renderHumanErrorHtml($error) ?>
        <?php endif; ?>

        <div class="glass-panel create-listing-card" style="border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); z-index: 10; width: 100%; box-sizing: border-box;">
            <form action="create_listing.php" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-6 js-form-loading" data-loading-text="<?= htmlspecialchars(__('create_listing.submitting_review'), ENT_QUOTES, 'UTF-8') ?>">
                <?php echo csrfTokenField(); ?>

                <!-- Listing Type Selector (Product vs Service) -->
                <div class="form-group">
                    <label class="font-bold mb-3 block" style="color: var(--text-main);">What are you listing?</label>
                    <div class="flex gap-6" id="listing-type-selector">
                        <label class="listing-type-btn flex-1 cursor-pointer" style="border-radius: var(--radius-lg); border: 2px solid <?php echo !$isServiceMode ? 'var(--primary)' : 'var(--border-light)'; ?>; padding: 1rem; text-align: center; background: <?php echo !$isServiceMode ? 'color-mix(in srgb, var(--primary) 8%, transparent)' : 'var(--bg-surface)'; ?>; transition: all 0.2s;">
                            <input type="radio" name="listing_type" value="product" <?php echo !$isServiceMode ? 'checked' : ''; ?> class="hidden-radio" id="type-product">
                            <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">📦</div>
                            <div class="font-bold text-main" style="font-size: 0.95rem;">Physical Item</div>
                            <div class="text-muted" style="font-size: 0.78rem;">Something you can hand over</div>
                        </label>
                        <label class="listing-type-btn flex-1 cursor-pointer" style="border-radius: var(--radius-lg); border: 2px solid <?php echo $isServiceMode ? 'var(--service)' : 'var(--border-light)'; ?>; padding: 1rem; text-align: center; background: <?php echo $isServiceMode ? 'var(--service-light)' : 'var(--bg-surface)'; ?>; transition: all 0.2s;">
                            <input type="radio" name="listing_type" value="service" <?php echo $isServiceMode ? 'checked' : ''; ?> class="hidden-radio" id="type-service">
                            <div style="font-size: 1.5rem; margin-bottom: 0.25rem;">🛠️</div>
                            <div class="font-bold text-main" style="font-size: 0.95rem;">Service</div>
                            <div class="text-muted" style="font-size: 0.78rem;">Tutoring, cleaning, photography...</div>
                        </label>
                    </div>

                    <!-- Service Catalog Quick-Pick Button -->
                    <div class="js-service-only" style="<?php echo !$isServiceMode ? 'display:none;' : ''; ?> margin-top: 0.75rem;">
                        <button type="button" id="btn-open-service-catalog" class="btn btn-sm btn--service" style="border-radius: var(--radius-lg); width: 100%; padding: 0.65rem 1rem; font-size: 0.88rem; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                            ✨ Pick from Service Templates (Tutoring, Cleaning, Photography, Moving)
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label id="title-label" class="font-bold mb-2 block" style="color: var(--text-main);"><?= $isServiceMode ? __('create_listing.sell_label_service') : __('create_listing.sell_label') ?></label>
                    <input type="text" id="title-input" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="<?= $isServiceMode ? addslashes(__('create_listing.title_placeholder_service')) : addslashes(__('create_listing.title_placeholder')) ?>" class="w-full premium-input" style="padding: 0.8rem 1rem;" required>
                </div>
 
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Category Selector (product only) -->
                    <div class="form-group js-product-only" <?php echo $isServiceMode ? 'style="display:none"' : ''; ?> style="grid-column: 1 / -1;">
                        <label class="font-bold mb-2 block" style="color: var(--text-main);"><?= __('create_listing.category_label') ?></label>
                        <p class="text-muted small mb-3">Choose every category that fits. One will be used as the primary category.</p>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <?php foreach ($categories as $cat): ?>
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2" style="border-color: var(--border-light); background: color-mix(in srgb, var(--bg-surface) 85%, white);">
                                    <input type="checkbox" name="category_ids[]" value="<?php echo (int)$cat['id']; ?>" <?php echo in_array((int)$cat['id'], $prevCategoryIds, true) && !$isServiceMode ? 'checked' : ''; ?>>
                                    <span><?php echo sanitize(translateCategory($cat['name'])); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="font-bold mb-2 block" style="color: var(--text-main);">
                            <span class="js-product-only" <?php echo $isServiceMode ? 'style="display:none"' : ''; ?>><?= __('create_listing.town_label') ?></span>
                            <span class="js-service-only" <?php echo !$isServiceMode ? 'style="display:none"' : ''; ?>>📍 Availability / Location</span>
                        </label>
                        <select name="location_town" class="w-full premium-input" style="padding: 0.8rem 1rem;">
                            <?php
                            $townSlugs = locationTownSlugs();
                            // For services, pre-select 'remote' when no prior selection
                            $resolvedTown = $selectedTown;
                            if ($resolvedTown === '' && $isServiceMode) {
                                $resolvedTown = 'remote';
                            }
                            foreach ($townSlugs as $townSlug):
                                // Show 'Remote' option only for services; show towns first for products
                                if ($townSlug === 'remote' && !$isServiceMode) continue;
                            ?>
                                <option value="<?php echo $townSlug; ?>" <?php echo $resolvedTown === $townSlug ? 'selected' : ''; ?>>
                                    <?php echo formatLocationTown($townSlug); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="custom_location_container" class="mt-3 form-group" style="<?php echo ($selectedTown === 'other') ? '' : 'display: none;'; ?>">
                            <label class="font-bold mb-1 block text-sm" style="color: var(--text-main);"><?= __('create_listing.custom_location_label') ?></label>
                            <input type="text" name="custom_location" id="custom_location_input" value="<?= htmlspecialchars($selectedCustomLocation) ?>" placeholder="<?= htmlspecialchars(__('create_listing.custom_location_placeholder')) ?>" maxlength="100" class="w-full premium-input" style="padding: 0.8rem 1rem;">
                        </div>
                        <p class="text-muted small mt-2 mb-0 js-product-only" <?php echo $isServiceMode ? 'style="display:none"' : ''; ?>><?= __('create_listing.town_hint') ?></p>
                        <p class="text-muted small mt-2 mb-0 js-service-only" <?php echo !$isServiceMode ? 'style="display:none"' : ''; ?>>Choose <strong>Remote / Online</strong> if your service can be done virtually, or pick the campus town where you'll be available in person.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="font-bold mb-2 block" style="color: var(--text-main);"><?= __('create_listing.currency_label') ?></label>
                        <select name="price_currency" class="w-full premium-input" style="padding: 0.8rem 1rem;" required>
                            <?php
                            $selectedCurrency = strtoupper(trim((string)($_POST['price_currency'] ?? DEFAULT_PRODUCT_CURRENCY)));
                            foreach (PRODUCT_CURRENCIES as $code => $meta):
                            ?>
                                <option value="<?php echo $code; ?>" <?php echo $selectedCurrency === $code ? 'selected' : ''; ?>>
                                    <?= __('create_listing.currency_' . strtolower($code)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-muted small mt-2 mb-0"><?= __('create_listing.currency_hint') ?></p>
                    </div>
                    <div class="form-group">
                        <label class="font-bold mb-2 block" style="color: var(--text-main);"><?= __('create_listing.price_label') ?></label>
                        <div class="relative">
                            <input type="number" name="price" step="0.01" min="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" placeholder="0.00" class="w-full premium-input" style="padding: 0.8rem 1rem;" required>
                        </div>
                    </div>
                </div>

                <!-- Condition (product only) -->
                <div class="form-group js-product-only" <?php echo $isServiceMode ? 'style="display:none"' : ''; ?>>
                    <label class="font-bold mb-2 block" style="color: var(--text-main);"><?= __('create_listing.condition_label') ?></label>
                    <div class="flex flex-wrap gap-3" style="gap: 0.5rem;">
                        <?php
                        $opts = [
                            'new' => __('create_listing.cond_new'),
                            'like_new' => __('create_listing.cond_like_new'),
                            'used' => __('create_listing.cond_used')
                        ];
                        $default = 'used';
                        foreach($opts as $val => $label):
                        ?>
                        <label class="condition-label group flex items-center gap-2 cursor-pointer glass-panel transition-all duration-200" style="border-radius: var(--radius-full); border: 2px solid transparent; padding: 0.55rem 0.9rem; flex: 1; min-width: 0; justify-content: center;">
                            <input type="radio" name="condition" value="<?php echo $val; ?>" <?php echo (isset($_POST['condition']) ? $_POST['condition'] == $val : $val == $default) ? 'checked' : ''; ?> class="hidden-radio">
                            <span class="custom-radio"></span>
                            <span class="font-semibold text-main"><?php echo $label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pricing Model (service only) -->
                <div class="form-group js-service-only" <?php echo !$isServiceMode ? 'style="display:none"' : ''; ?>>
                    <label class="font-bold mb-2 block" style="color: var(--text-main);">Pricing Model</label>
                    <div class="flex gap-3">
                        <label class="pricing-model-btn flex-1 cursor-pointer" style="border-radius: var(--radius-lg); border: 2px solid var(--border-light); padding: 0.75rem 1rem; text-align: center; background: var(--bg-surface); transition: all 0.2s;">
                            <input type="radio" name="pricing_model" value="flat" <?php echo (($_POST['pricing_model'] ?? 'flat') === 'flat') ? 'checked' : ''; ?> class="hidden-radio">
                            <div class="font-bold text-main">💵 Flat Rate</div>
                            <div class="text-muted" style="font-size: 0.78rem;">One price for the job</div>
                        </label>
                        <label class="pricing-model-btn flex-1 cursor-pointer" style="border-radius: var(--radius-lg); border: 2px solid var(--border-light); padding: 0.75rem 1rem; text-align: center; background: var(--bg-surface); transition: all 0.2s;">
                            <input type="radio" name="pricing_model" value="hourly" <?php echo (($_POST['pricing_model'] ?? '') === 'hourly') ? 'checked' : ''; ?> class="hidden-radio">
                            <div class="font-bold text-main">⏱️ Hourly Rate</div>
                            <div class="text-muted" style="font-size: 0.78rem;">Price per hour</div>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="font-bold mb-2 block" style="color: var(--text-main);"><?= __('create_listing.description_label') ?></label>
                    <textarea id="description-textarea" name="description" rows="5" placeholder="<?= $isServiceMode ? addslashes(__('create_listing.desc_placeholder_service')) : addslashes(__('create_listing.desc_placeholder')) ?>" class="w-full premium-input" style="padding: 1rem; border-radius: var(--radius-lg);" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <?php if (isAgent()): ?>
                <div class="form-group agent-owner-contact-panel" style="padding: 1.25rem; border-radius: var(--radius-lg); border: 1px solid var(--border-light); background: color-mix(in srgb, var(--primary) 6%, var(--bg-surface));">
                    <div class="mb-4">
                        <h3 class="mb-1" style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);"><?= __('agent.owner_contact_heading') ?></h3>
                        <p class="text-muted small mb-0"><?= __('agent.owner_contact_hint') ?></p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="font-bold mb-2 block" for="owner_name"><?= __('agent.owner_name_label') ?></label>
                            <input type="text" id="owner_name" name="owner_name" maxlength="120" class="w-full premium-input" style="padding: 0.8rem 1rem;" required value="<?= htmlspecialchars($_POST['owner_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="font-bold mb-2 block" for="owner_phone"><?= __('agent.owner_phone_label') ?></label>
                            <input type="tel" id="owner_phone" name="owner_phone" maxlength="20" class="w-full premium-input" style="padding: 0.8rem 1rem;" required value="<?= htmlspecialchars($_POST['owner_phone'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="font-bold mb-2 block" for="owner_email"><?= __('agent.owner_email_label') ?></label>
                            <input type="email" id="owner_email" name="owner_email" maxlength="100" class="w-full premium-input" style="padding: 0.8rem 1rem;" value="<?= htmlspecialchars($_POST['owner_email'] ?? '') ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="font-bold mb-2 block" for="owner_notes"><?= __('agent.owner_notes_label') ?></label>
                            <textarea id="owner_notes" name="owner_notes" rows="3" maxlength="2000" class="w-full premium-input" style="padding: 0.8rem 1rem; border-radius: var(--radius-md);" placeholder="<?= htmlspecialchars(__('agent.owner_notes_placeholder')) ?>"><?= htmlspecialchars($_POST['owner_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
 
                <div class="form-group">
                    <label class="font-bold mb-2 block" style="color: var(--text-main);"><?= __('create_listing.photos_label') ?></label>
                    <div id="uploadDropzone" class="listing-upload-dropzone border-2 border-dashed rounded-lg text-center cursor-pointer transition-colors" style="border-color: color-mix(in srgb, var(--primary) 30%, transparent); background: color-mix(in srgb, var(--primary) 3%, var(--bg-surface)); padding: 3rem 2rem; min-height: 180px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <div class="mb-3 flex justify-center text-primary opacity-80">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <p class="font-bold mb-1" style="color: var(--primary); font-size: 1.05rem;"><?= __('create_listing.upload_click') ?></p>
                        <p id="uploadHelp" class="text-muted small"><?= __('create_listing.upload_desc') ?></p>
                        <p class="text-muted small mt-1 hidden md:block"><?= __('create_listing.drag_drop') ?></p>
                        <input type="file" id="imgInput" name="images[]" multiple accept="image/*" class="hidden">
                    </div>
                    <div id="preview" class="flex flex-wrap gap-4 mt-5"></div>
                </div>

                <!-- ── Tags Section ───────────────────────────────── -->
                <div class="form-group" id="tags-section">
                    <div class="flex items-center justify-between mb-2" style="flex-wrap: wrap; gap: 0.5rem;">
                        <label class="font-bold" style="color: var(--text-main);">
                            Tags
                            <span style="font-weight: 400; font-size: 0.82rem; color: var(--text-muted); margin-left: 0.4rem;">— select up to 5 that fit</span>
                        </label>
                        <button type="button" id="suggestTagsBtn"
                            style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.4rem 0.9rem; border-radius: var(--radius-lg); border: 1px solid var(--primary); background: var(--primary-light); color: var(--primary); font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: var(--transition);">
                            <svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                            Suggest Tags
                        </button>
                    </div>
                    <div id="tags-status" style="font-size: 0.78rem; color: var(--text-muted); min-height: 1.2em; margin-bottom: 0.5rem;"></div>
                    <div class="tag-pill-grid" style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        <?php foreach ($allTags as $tag): ?>
                        <label class="tag-pill" style="cursor: pointer;">
                            <input type="checkbox" name="tags[]" value="<?php echo $tag['id']; ?>"
                                   id="tag-<?php echo $tag['id']; ?>"
                                   class="tag-pill-check"
                                   <?php echo in_array((int)$tag['id'], $prevTags) ? 'checked' : ''; ?>
                                   style="position: absolute; opacity: 0; width: 0; height: 0;">
                            <span class="tag-pill-label">#<?php echo sanitize($tag['name']); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Custom Tag Creation -->
                    <div id="new-tag-wrapper" style="margin-top: 0.85rem; padding-top: 0.75rem; border-top: 1px dashed var(--border-light);">
                        <div style="display: flex; align-items: center; gap: 0.5rem; max-width: 420px;">
                            <input type="text" id="new-tag-input" placeholder="+ Add a custom tag (e.g. vintage, calculus)" maxlength="40" class="premium-input" style="padding: 0.4rem 0.8rem; font-size: 0.85rem; border-radius: var(--radius-full); flex: 1;">
                            <button type="button" id="btn-add-custom-tag" class="btn btn-secondary btn-sm" style="border-radius: var(--radius-full); font-size: 0.8rem; padding: 0.4rem 1rem;">Add</button>
                        </div>
                        <div id="custom-tags-container" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem;"></div>
                        <p class="text-muted small mt-1 mb-0" style="font-size: 0.76rem;">Don't see a tag you need? Type it above to add it to your listing.</p>
                    </div>
                </div>

                <hr style="border-color: rgba(0,0,0,0.05); margin: 1rem 0;">

                <div class="flex justify-between items-center">
                    <a href="browse.php" class="btn btn-secondary hover-scale shadow-sm flex items-center gap-2" style="padding: 0.75rem 1.5rem; border-radius: var(--radius-lg); font-weight: bold; font-size: 1.1rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        <?= __('create_listing.cancel') ?>
                    </a>
                    <button type="submit" id="submitBtn" class="btn btn-primary px-8 py-3 hover-scale shadow-lg" style="border-radius: var(--radius-lg); font-weight: bold; font-size: 1.1rem;"><?= __('create_listing.publish') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
    .hidden-radio { position: absolute; opacity: 0; width: 0; height: 0; }
    
    .custom-radio {
        width: 20px;
        height: 20px;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-md);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--bg-surface);
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .custom-radio::after {
        content: '';
        width: 10px;
        height: 10px;
        background: var(--primary);
        border-radius: var(--radius-lg);
        transform: scale(0);
        transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .hidden-radio:checked + .custom-radio {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }
    
    .hidden-radio:checked + .custom-radio::after {
        transform: scale(1);
    }

    .condition-label:has(.hidden-radio:checked) {
        background: color-mix(in srgb, var(--primary) 8%, var(--bg-surface));
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .condition-label:hover {
        background: color-mix(in srgb, var(--primary) 4%, var(--bg-surface));
        transform: translateY(-1px);
    }

    .listing-upload-dropzone.is-dragover {
        border-color: var(--primary) !important;
        background: color-mix(in srgb, var(--primary) 10%, var(--bg-surface)) !important;
    }

    select.premium-input option {
        background: var(--bg-surface);
        color: var(--text-main);
    }

    textarea { resize: vertical; }

    /* ── Tag Pills ─────────────────────────────────────────── */
    .tag-pill { position: relative; display: inline-block; }

    .tag-pill-label {
        display: inline-block;
        padding: 0.3rem 0.75rem;
        border-radius: var(--radius-full);
        border: 1.5px solid var(--border-light);
        background: var(--bg-main);
        color: var(--text-muted);
        font-size: 0.82rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.18s ease;
        user-select: none;
    }

    .tag-pill-label:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: var(--primary-light);
    }

    .tag-pill-check:checked ~ .tag-pill-label {
        border-color: var(--primary);
        background: var(--primary-light);
        color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }

    .tag-pill-label.ai-suggested {
        animation: pillPop 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
    }

    @keyframes pillPop {
        0%   { transform: scale(1); }
        50%  { transform: scale(1.18); }
        100% { transform: scale(1); }
    }

    @keyframes spin {
        from { transform: rotate(0deg); }
        to   { transform: rotate(360deg); }
    }

    #suggestTagsBtn:hover {
        background: var(--primary);
        color: white;
    }

    #suggestTagsBtn:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .max-w-3xl {
        max-width: 768px;
        width: 100%;
    }

    .create-listing-card {
        padding: 1.25rem;
    }
    @media (min-width: 768px) {
        .create-listing-card {
            padding: 2.5rem;
        }
    }
</style>

<script>
let uploadedFiles = [];
const maxFiles = 5;
const createListingI18n = {
    processing: <?= json_encode(__('create_listing.processing_images')) ?>,
    compressing: <?= json_encode(__('create_listing.compressing_images')) ?>,
    maxFilesAlert: <?= json_encode(__('create_listing.max_files_alert', ['max' => MAX_IMAGES])) ?>,
    publishLabel: <?= json_encode(__('create_listing.publish')) ?>,
    uploadHelp: <?= json_encode(__('create_listing.upload_desc')) ?>,
    sellLabel: <?= json_encode(__('create_listing.sell_label')) ?>,
    offerLabel: <?= json_encode(__('create_listing.sell_label_service')) ?>,
    titlePlaceholder: <?= json_encode(__('create_listing.title_placeholder')) ?>,
    titlePlaceholderService: <?= json_encode(__('create_listing.title_placeholder_service')) ?>,
    descPlaceholder: <?= json_encode(__('create_listing.desc_placeholder')) ?>,
    descPlaceholderService: <?= json_encode(__('create_listing.desc_placeholder_service')) ?>
};

function updateFileInput() {
    const dt = new DataTransfer();
    uploadedFiles.forEach(file => dt.items.add(file));
    document.getElementById('imgInput').files = dt.files;
}

function renderPreviews() {
    const preview = document.getElementById('preview');
    preview.innerHTML = '';
    
    uploadedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = (re) => {
            const div = document.createElement('div');
            div.style = "position: relative; width:120px; height:120px; border-radius: var(--radius-md); overflow:hidden; border: 2px solid var(--primaryLight); box-shadow: var(--shadow-sm); flex-shrink: 0;";
            
            const img = document.createElement('img');
            img.src = re.target.result;
            img.style = "width:100%; height:100%; object-fit:cover;";
            
            const removeBtn = document.createElement('button');
            removeBtn.type = "button";
            removeBtn.innerHTML = "&times;";
            removeBtn.style = "position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 16px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10;";
            removeBtn.onclick = function(e) {
                e.stopPropagation();
                e.preventDefault();
                uploadedFiles.splice(index, 1);
                updateFileInput();
                renderPreviews();
            };
            
            div.appendChild(img);
            div.appendChild(removeBtn);
            preview.appendChild(div);
        }
        reader.readAsDataURL(file);
    });
}

function compressImageAsync(file, maxWidth = 1200, maxHeight = 1200, quality = 0.8) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = function (e) {
            const img = new Image();
            img.onload = function () {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > maxWidth) {
                        height *= maxWidth / width;
                        width = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        width *= maxHeight / height;
                        height = maxHeight;
                    }
                }

                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);

                canvas.toBlob(function (blob) {
                    if (!blob) {
                        reject(new Error('Canvas to Blob failed'));
                        return;
                    }
                    const compressedFile = new File([blob], file.name.substring(0, file.name.lastIndexOf('.')) + '.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
                }, 'image/jpeg', quality);
            };
            img.onerror = reject;
            img.src = e.target.result;
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

document.getElementById('imgInput').addEventListener('change', async function(e) {
    await handleIncomingFiles([...e.target.files]);
});

async function handleIncomingFiles(newFiles) {
    const submitBtn = document.getElementById('submitBtn');
    const uploadHelp = document.getElementById('uploadHelp');

    if (newFiles.length === 0) {
        updateFileInput();
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerText = createListingI18n.processing;
    uploadHelp.innerText = createListingI18n.compressing;
    uploadHelp.style.color = "var(--primary)";

    for (let i = 0; i < newFiles.length; i++) {
        if (!newFiles[i].type || newFiles[i].type.indexOf('image/') !== 0) continue;
        if (uploadedFiles.some(f => f.name === newFiles[i].name && f.size === newFiles[i].size)) continue;

        if (uploadedFiles.length < maxFiles) {
            try {
                const compressed = await compressImageAsync(newFiles[i]);
                uploadedFiles.push(compressed);
            } catch (err) {
                console.error('Compression failed, using original file', err);
                uploadedFiles.push(newFiles[i]);
            }
        } else {
            alert(createListingI18n.maxFilesAlert);
            break;
        }
    }

    updateFileInput();
    renderPreviews();

    submitBtn.disabled = false;
    submitBtn.innerText = createListingI18n.publishLabel;
    uploadHelp.innerText = createListingI18n.uploadHelp;
    uploadHelp.style.color = "";
}

(function initUploadDropzone() {
    const dropzone = document.getElementById('uploadDropzone');
    const imgInput = document.getElementById('imgInput');
    if (!dropzone || !imgInput) return;

    dropzone.addEventListener('click', function () {
        imgInput.click();
    });

    ['dragenter', 'dragover'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', async function (e) {
        const files = [...(e.dataTransfer?.files || [])];
        await handleIncomingFiles(files);
    });
})();

// ── Suggest Tags ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const suggestBtn  = document.getElementById('suggestTagsBtn');
    const statusEl    = document.getElementById('tags-status');
    const MAX_PILLS   = 5;

    if (!suggestBtn) return;

    // Enforce max 5 tag selections
    document.querySelectorAll('.tag-pill-check').forEach(function (chk) {
        chk.addEventListener('change', function () {
            const checked = document.querySelectorAll('.tag-pill-check:checked');
            if (checked.length > MAX_PILLS) {
                this.checked = false;
                statusEl.textContent = 'You can select up to ' + MAX_PILLS + ' tags.';
                statusEl.style.color = '#dc2626';
                return;
            }
            statusEl.textContent = '';
            statusEl.style.color = '';
        });
    });

    suggestBtn.addEventListener('click', async function () {
        const title = (document.querySelector('input[name="title"]')?.value || '').trim();
        const desc  = (document.querySelector('textarea[name="description"]')?.value || '').trim();

        if (title.length < 3) {
            statusEl.textContent = 'Add a title first so the AI has something to work with.';
            statusEl.style.color = '#d97706';
            return;
        }

        suggestBtn.disabled = true;
        suggestBtn.innerHTML = '<svg style="width:14px;height:14px;animation:spin 1s linear infinite;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg> Thinking…';
        statusEl.textContent = 'Asking AI for tag suggestions…';
        statusEl.style.color = 'var(--primary)';

        // Get CSRF token from the hidden field
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || '';

        try {
            const formData = new FormData();
            formData.append('title',       title);
            formData.append('description', desc);
            formData.append('csrf_token',  csrfToken);

            const res  = await fetch('api_suggest_tags.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.error) {
                statusEl.textContent = 'Could not get suggestions: ' + data.error;
                statusEl.style.color = '#dc2626';
                return;
            }

            const suggested = data.tags || [];
            if (suggested.length === 0) {
                statusEl.textContent = data.note || 'No matching tags found — select manually.';
                statusEl.style.color = '#d97706';
                return;
            }

            // Check the suggested pills (up to MAX_PILLS total)
            let applied = 0;
            const alreadyChecked = document.querySelectorAll('.tag-pill-check:checked').length;
            let slots = MAX_PILLS - alreadyChecked;

            suggested.forEach(function (id) {
                if (slots <= 0) return;
                const chk   = document.getElementById('tag-' + id);
                const label = chk?.nextElementSibling;
                if (chk && !chk.checked) {
                    chk.checked = true;
                    if (label) {
                        label.classList.add('ai-suggested');
                        setTimeout(() => label.classList.remove('ai-suggested'), 400);
                    }
                    applied++;
                    slots--;
                }
            });

            if (applied > 0) {
                statusEl.textContent = '✓ ' + applied + ' tag' + (applied > 1 ? 's' : '') + ' suggested by AI — you can adjust freely.';
                statusEl.style.color = 'var(--success, #059669)';
            } else {
                statusEl.textContent = 'Suggested tags are already selected.';
                statusEl.style.color = 'var(--text-muted)';
            }

        } catch (err) {
            statusEl.textContent = 'Network error — please try again.';
            statusEl.style.color = '#dc2626';
        } finally {
            suggestBtn.disabled = false;
            suggestBtn.innerHTML = '<svg style="width:14px;height:14px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg> Suggest Tags';
        }
    });

    // Custom tag creation logic
    const customTagInput = document.getElementById('new-tag-input');
    const addCustomTagBtn = document.getElementById('btn-add-custom-tag');
    const customTagsContainer = document.getElementById('custom-tags-container');

    function addCustomTag() {
        if (!customTagInput || !customTagsContainer) return;
        const val = customTagInput.value.trim().replace(/^#+/, '');
        if (val.length < 2) {
            alert('Tag must be at least 2 characters long.');
            return;
        }
        if (val.length > 40) {
            alert('Tag cannot exceed 40 characters.');
            return;
        }
        
        const totalChecked = document.querySelectorAll('.tag-pill-check:checked').length;
        const totalCustom = customTagsContainer.querySelectorAll('input[name="new_tags[]"]').length;
        if (totalChecked + totalCustom >= 5) {
            alert('You can choose or create up to 5 tags maximum.');
            return;
        }

        // Duplicate check
        const existingCustom = [...customTagsContainer.querySelectorAll('input[name="new_tags[]"]')].map(i => i.value.toLowerCase());
        if (existingCustom.includes(val.toLowerCase())) {
            alert('This tag is already added.');
            return;
        }

        const pill = document.createElement('span');
        pill.className = 'tag-pill';
        pill.style = 'display: inline-flex; align-items: center; gap: 0.35rem; background: var(--primary-light); color: var(--primary); border: 1px solid var(--primary); border-radius: var(--radius-full); padding: 0.25rem 0.65rem; font-size: 0.8rem; font-weight: 600;';
        pill.innerHTML = `
            <span>#${val}</span>
            <input type="hidden" name="new_tags[]" value="${val.replace(/"/g, '&quot;')}">
            <button type="button" style="background:none; border:none; color:inherit; font-size:0.9rem; cursor:pointer; padding:0; line-height:1;" title="Remove tag">&times;</button>
        `;

        pill.querySelector('button').addEventListener('click', () => pill.remove());
        customTagsContainer.appendChild(pill);
        customTagInput.value = '';
    }

    if (addCustomTagBtn && customTagInput) {
        addCustomTagBtn.addEventListener('click', addCustomTag);
        customTagInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                addCustomTag();
            }
        });
    }

    const townSelect = document.querySelector('select[name="location_town"]');
    const customLocContainer = document.getElementById('custom_location_container');
    const customLocInput = document.getElementById('custom_location_input');
    if (townSelect && customLocContainer) {
        function updateCustomLocVisibility() {
            if (townSelect.value === 'other') {
                customLocContainer.style.display = 'block';
                if (customLocInput) customLocInput.required = true;
            } else {
                customLocContainer.style.display = 'none';
                if (customLocInput) customLocInput.required = false;
            }
        }
        townSelect.addEventListener('change', updateCustomLocVisibility);
        updateCustomLocVisibility();
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
// Dynamic listing type toggle
(function() {
    const typeRadios = document.querySelectorAll('input[name="listing_type"]');
    const productOnlyEls = document.querySelectorAll('.js-product-only');
    const serviceOnlyEls = document.querySelectorAll('.js-service-only');
    const typeBtns = document.querySelectorAll('.listing-type-btn');

    function applyType(type) {
        productOnlyEls.forEach(el => el.style.display = type === 'product' ? '' : 'none');
        serviceOnlyEls.forEach(el => el.style.display = type === 'service' ? '' : 'none');

        // Dynamically update labels & placeholders
        const titleLabel = document.getElementById('title-label');
        const titleInput = document.getElementById('title-input');
        const descTextarea = document.getElementById('description-textarea');
        
        if (titleLabel && titleInput && descTextarea) {
            if (type === 'service') {
                titleLabel.textContent = createListingI18n.offerLabel;
                titleInput.placeholder = createListingI18n.titlePlaceholderService;
                descTextarea.placeholder = createListingI18n.descPlaceholderService;
            } else {
                titleLabel.textContent = createListingI18n.sellLabel;
                titleInput.placeholder = createListingI18n.titlePlaceholder;
                descTextarea.placeholder = createListingI18n.descPlaceholder;
            }
        }

        typeBtns.forEach(btn => {
            const radio = btn.querySelector('input[name="listing_type"]');
            const isSelected = radio && radio.value === type;
            const color = radio && radio.value === 'service' ? '#ef4444' : 'var(--primary)';
            btn.style.border = isSelected ? `2px solid ${color}` : '2px solid var(--border-light)';
            if (isSelected && radio.value === 'service') {
                btn.style.background = 'rgba(239,68,68,0.08)';
            } else if (isSelected) {
                btn.style.background = 'color-mix(in srgb, var(--primary) 8%, transparent)';
            } else {
                btn.style.background = 'var(--bg-surface)';
            }
        });
    }

    typeRadios.forEach(radio => {
        radio.addEventListener('change', () => applyType(radio.value));
    });

    // Run once on load to initialize state
    const activeType = document.querySelector('input[name="listing_type"]:checked');
    if (activeType) {
        applyType(activeType.value);
    }
})();

// Dynamic pricing model toggle
(function() {
    const modelRadios = document.querySelectorAll('input[name="pricing_model"]');
    const modelBtns = document.querySelectorAll('.pricing-model-btn');

    function applyModel(model) {
        modelBtns.forEach(btn => {
            const radio = btn.querySelector('input[name="pricing_model"]');
            const isSelected = radio && radio.value === model;
            if (isSelected) {
                btn.style.border = '2px solid #ef4444';
                btn.style.background = 'rgba(239,68,68,0.08)';
            } else {
                btn.style.border = '2px solid var(--border-light)';
                btn.style.background = 'var(--bg-surface)';
            }
        });
    }

    modelRadios.forEach(radio => {
        radio.addEventListener('change', () => applyModel(radio.value));
    });

    // Run once on load to initialize styling
    const activeModel = document.querySelector('input[name="pricing_model"]:checked');
    if (activeModel) {
        applyModel(activeModel.value);
    }
})();

// Service Template Catalog Modal Logic
(function() {
    const catalogModal = document.getElementById('service-catalog-modal');
    const openBtn = document.getElementById('btn-open-service-catalog');
    const closeBtn = document.getElementById('btn-close-service-catalog');
    const tabsContainer = document.getElementById('catalog-category-tabs');
    const gridContainer = document.getElementById('catalog-templates-grid');

    if (!catalogModal) return;

    let catalogData = [];
    let isLoaded = false;

    async function loadCatalog() {
        if (isLoaded) return;
        try {
            const res = await fetch('<?= BASE_URL ?>api/service_templates.php');
            const data = await res.json();
            if (data.success && data.categories) {
                catalogData = data.categories;
                isLoaded = true;
                renderCategories();
            }
        } catch (e) {
            console.error('Failed to load service catalog', e);
        }
    }

    function renderCategories() {
        if (!tabsContainer || catalogData.length === 0) return;
        tabsContainer.innerHTML = '';
        catalogData.forEach((cat, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `btn btn-sm ${idx === 0 ? 'btn-primary' : 'btn-secondary'}`;
            btn.style = 'border-radius: var(--radius-full); font-size: 0.82rem; padding: 0.35rem 0.85rem;';
            btn.textContent = cat.category_name;
            btn.addEventListener('click', () => {
                tabsContainer.querySelectorAll('button').forEach(b => {
                    b.className = 'btn btn-sm btn-secondary';
                });
                btn.className = 'btn btn-sm btn-primary';
                renderTemplates(cat.templates);
            });
            tabsContainer.appendChild(btn);
        });

        if (catalogData[0]) {
            renderTemplates(catalogData[0].templates);
        }
    }

    function renderTemplates(templates) {
        if (!gridContainer) return;
        gridContainer.innerHTML = '';
        templates.forEach(t => {
            const card = document.createElement('div');
            card.style = 'border: 1px solid var(--border-light); border-radius: var(--radius-lg); padding: 1rem; background: var(--bg-surface); cursor: pointer; transition: all 0.2s; display: flex; flex-direction: column; gap: 0.5rem; position: relative;';
            card.className = 'card-hover';
            card.innerHTML = `
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                    <div style="font-weight: 700; font-size: 0.98rem; color: var(--text-main);">${t.name}</div>
                    <span style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; background: var(--service-light); color: var(--service); padding: 0.15rem 0.55rem; border-radius: var(--radius-full); white-space: nowrap;">
                        ${t.pricing_model === 'hourly' ? 'Hourly' : 'Fixed Price'}
                    </span>
                </div>
                <div style="font-size: 0.82rem; color: var(--text-muted); line-height: 1.45; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 0.25rem;">${t.description_template}</div>
                <div style="margin-top: auto; padding-top: 0.6rem; border-top: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; font-size: 0.82rem;">
                    <span style="font-weight: 700; color: var(--primary);">Suggested: ${t.suggested_price_min} – ${t.suggested_price_max}</span>
                    <span style="color: var(--service); font-weight: 600;">Use Template →</span>
                </div>
            `;

            card.addEventListener('click', () => {
                applyTemplate(t);
                catalogModal.style.display = 'none';
            });

            gridContainer.appendChild(card);
        });
    }

    function applyTemplate(t) {
        const titleInput = document.getElementById('title-input');
        const descInput = document.getElementById('description-textarea');
        const priceInput = document.querySelector('input[name="price"]');
        const typeServiceRadio = document.getElementById('type-service');
        const hourlyRadio = document.querySelector('input[name="pricing_model"][value="hourly"]');
        const flatRadio = document.querySelector('input[name="pricing_model"][value="flat"]');

        if (typeServiceRadio) {
            typeServiceRadio.checked = true;
            typeServiceRadio.dispatchEvent(new Event('change'));
        }

        if (titleInput) titleInput.value = t.title_template;
        if (descInput) descInput.value = t.description_template;
        if (priceInput && t.suggested_price_min) priceInput.value = t.suggested_price_min;

        if (t.pricing_model === 'hourly' && hourlyRadio) {
            hourlyRadio.checked = true;
            hourlyRadio.dispatchEvent(new Event('change'));
        } else if (flatRadio) {
            flatRadio.checked = true;
            flatRadio.dispatchEvent(new Event('change'));
        }

        // Highlight tags
        if (Array.isArray(t.suggested_tags) && t.suggested_tags.length > 0) {
            t.suggested_tags.forEach(slug => {
                const chk = document.querySelector(`.tag-pill-check[data-slug="${slug}"]`) || document.querySelector(`.tag-pill-check[value="${slug}"]`);
                if (chk) chk.checked = true;
            });
        }

        titleInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        titleInput.focus();
    }

    if (openBtn) {
        openBtn.addEventListener('click', () => {
            catalogModal.style.display = 'flex';
            loadCatalog();
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            catalogModal.style.display = 'none';
        });
    }

    catalogModal.addEventListener('click', (e) => {
        if (e.target === catalogModal) {
            catalogModal.style.display = 'none';
        }
    });

    // Auto-open modal on first switch to Service radio if no title is typed
    const typeServiceRadio = document.getElementById('type-service');
    if (typeServiceRadio) {
        typeServiceRadio.addEventListener('change', () => {
            const titleInput = document.getElementById('title-input');
            if (typeServiceRadio.checked && titleInput && !titleInput.value.trim()) {
                catalogModal.style.display = 'flex';
                loadCatalog();
            }
        });
    }
})();
</script>

<!-- Service Catalog Modal HTML -->
<div id="service-catalog-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div style="background: var(--bg-surface); width: 100%; max-width: 780px; max-height: 90vh; border-radius: var(--radius-xl); border: 1px solid var(--border-light); display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--shadow-xl);">
        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; background: var(--bg-card);">
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: var(--text-main);">✨ Pick a Service Template</h3>
                <p style="margin: 0.2rem 0 0; font-size: 0.82rem; color: var(--text-muted);">Choose a popular template to pre-fill your listing, or customize everything as you go.</p>
            </div>
            <button type="button" id="btn-close-service-catalog" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; line-height: 1; padding: 0.25rem;">&times;</button>
        </div>

        <div style="padding: 0.75rem 1.5rem; border-bottom: 1px solid var(--border-light); display: flex; gap: 0.5rem; overflow-x: auto;" id="catalog-category-tabs">
            <!-- Tabs loaded via JS -->
        </div>

        <div style="padding: 1.5rem; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem;" id="catalog-templates-grid">
            <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 2rem;">Loading templates...</div>
        </div>

        <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; background: var(--bg-card);">
            <span style="font-size: 0.8rem; color: var(--text-muted);">You can always edit every detail after choosing.</span>
            <button type="button" onclick="document.getElementById('service-catalog-modal').style.display='none'" class="btn btn-sm btn-secondary" style="border-radius: var(--radius-lg);">Skip &amp; Write Blank</button>
        </div>
    </div>
</div>

