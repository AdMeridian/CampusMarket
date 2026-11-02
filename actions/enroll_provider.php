<?php
// actions/enroll_provider.php
// Registers or updates a student provider profile and publishes their pilot service listing

require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'pages/provider_onboard.php');
}

verifyCsrfToken();

$userId = currentUserId();

$bio = trim((string)($_POST['bio'] ?? ''));
$rawSkillsInput = trim((string)($_POST['raw_skills_input'] ?? ''));
$portfolioLink = trim((string)($_POST['portfolio_link'] ?? ''));
$categoryId = (int)($_POST['category_id'] ?? 0);

$serviceTitle = trim((string)($_POST['service_title'] ?? ''));
$serviceDescription = trim((string)($_POST['service_description'] ?? ''));

$validTowns = ['remote', 'lefkosa', 'girne', 'gazimagusa', 'guzelyurt', 'lefke', 'iskele'];
$locationTown = strtolower(trim((string)($_POST['location_town'] ?? '')));
if (!in_array($locationTown, $validTowns, true)) {
    setFlash('error', 'Please select a valid campus town or "Remote / Online".');
    redirect(BASE_URL . 'pages/provider_onboard.php');
}

$priceInput = trim((string)($_POST['price'] ?? ''));
$price = null;
if ($priceInput !== '') {
    if (!is_numeric($priceInput) || (float)$priceInput <= 0 || (float)$priceInput > 10000) {
        setFlash('error', 'Enter a rate greater than 0 and no more than 10,000 TRY, or leave it blank.');
        redirect(BASE_URL . 'pages/provider_onboard.php');
    }
    $price = round((float)$priceInput, 2);
}

$pricingModelInput = trim((string)($_POST['pricing_model'] ?? ''));
if ($price !== null && !in_array($pricingModelInput, ['flat', 'hourly'], true)) {
    setFlash('error', 'Choose a rate basis or leave the rate blank.');
    redirect(BASE_URL . 'pages/provider_onboard.php');
}
$pricingModel = $price !== null && $pricingModelInput === 'hourly' ? 'hourly' : 'flat';

// Hourly services do not have turnaround delivery days; clear to null
$deliveryDays = ($price === null || $pricingModel === 'hourly') ? null : max(1, min(30, (int)($_POST['delivery_days'] ?? 2)));
$availabilityStatus = in_array($_POST['availability_status'] ?? '', ['available', 'busy', 'unavailable'], true) ? $_POST['availability_status'] : 'available';

// Capture student-edited fields to track AI accuracy
$editedFieldsRaw = $_POST['edited_fields'] ?? '';
$editedFields = [];
if (!empty($editedFieldsRaw)) {
    $decoded = json_decode($editedFieldsRaw, true);
    if (is_array($decoded)) {
        $editedFields = array_values(array_filter($decoded, 'is_string'));
    }
}

// Process skills tags
$rawSkills = $_POST['skills'] ?? '';
$parsedSkills = [];
if (is_array($rawSkills)) {
    $parsedSkills = array_filter(array_map('trim', $rawSkills));
} elseif (is_string($rawSkills) && $rawSkills !== '') {
    $parsedSkills = array_filter(array_map('trim', explode(',', $rawSkills)));
}

// Basic validation
if (mb_strlen($serviceTitle) < 5) {
    setFlash('error', 'Please provide a clear service title (at least 5 characters).');
    redirect(BASE_URL . 'pages/provider_onboard.php');
}
if (mb_strlen($serviceDescription) < 10) {
    setFlash('error', 'Please provide a descriptive explanation of your service.');
    redirect(BASE_URL . 'pages/provider_onboard.php');
}
if ($categoryId <= 0) {
    setFlash('error', 'Please select a service category.');
    redirect(BASE_URL . 'pages/provider_onboard.php');
}

// Validate portfolio URL if provided
if ($portfolioLink !== '' && !preg_match('~^https?://~i', $portfolioLink)) {
    $portfolioLink = 'https://' . $portfolioLink;
}

try {
    $pdo->beginTransaction();

    // 1. Upsert provider profile
    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    $parsedSkillsJson = json_encode(array_values($parsedSkills), JSON_UNESCAPED_UNICODE);

    if ($driver === 'pgsql') {
        $profileSql = "INSERT INTO provider_profiles (user_id, bio, raw_skills_input, parsed_skills, primary_category_id, portfolio_link, enrollment_status, is_verified, updated_at) " .
                      "VALUES (?, ?, ?, ?::jsonb, ?, ?, 'pilot', TRUE, NOW()) " .
                      "ON CONFLICT (user_id) DO UPDATE SET bio = EXCLUDED.bio, raw_skills_input = EXCLUDED.raw_skills_input, parsed_skills = EXCLUDED.parsed_skills, " .
                      "primary_category_id = EXCLUDED.primary_category_id, portfolio_link = EXCLUDED.portfolio_link, enrollment_status = 'pilot', updated_at = NOW()";
    } else {
        $profileSql = "INSERT INTO provider_profiles (user_id, bio, raw_skills_input, parsed_skills, primary_category_id, portfolio_link, enrollment_status, is_verified) " .
                      "VALUES (?, ?, ?, ?, ?, ?, 'pilot', 1) " .
                      "ON DUPLICATE KEY UPDATE bio = VALUES(bio), raw_skills_input = VALUES(raw_skills_input), parsed_skills = VALUES(parsed_skills), " .
                      "primary_category_id = VALUES(primary_category_id), portfolio_link = VALUES(portfolio_link), enrollment_status = 'pilot'";
    }

    $profileStmt = $pdo->prepare($profileSql);
    $profileStmt->execute([$userId, $bio, $rawSkillsInput, $parsedSkillsJson, $categoryId, $portfolioLink]);

    // 2. Fetch seller university and country details
    $sellerMarketplace = function_exists('getUserUniversityAndCountry') ? getUserUniversityAndCountry($pdo, $userId) : null;
    $sellerCountry = $sellerMarketplace['country_code'] ?? 'TR';
    $sellerUniversityId = $sellerMarketplace['university_id'] ?? null;

    // 3. Create or update the pilot service listing
    $conditionQuote = ($driver === 'mysql') ? '`condition`' : '"condition"';
    
    // Check if user targeted an existing service listing or has an active pilot service listing
    $requestedListingId = (int)($_POST['existing_product_id'] ?? 0);
    $existingProductId = null;
    if ($requestedListingId > 0) {
        $checkReq = $pdo->prepare("SELECT id FROM products WHERE id = ? AND user_id = ? AND listing_type = 'service' AND status != 'deleted' LIMIT 1");
        $checkReq->execute([$requestedListingId, $userId]);
        $existingProductId = $checkReq->fetchColumn();
    }
    if (!$existingProductId) {
        $checkExisting = $pdo->prepare("SELECT id FROM products WHERE user_id = ? AND listing_type = 'service' AND status != 'deleted' LIMIT 1");
        $checkExisting->execute([$userId]);
        $existingProductId = $checkExisting->fetchColumn();
    }

    // Check for optional university/country columns
    $hasCountryCol = false;
    $hasUnivCol = false;
    try {
        if ($driver === 'pgsql') {
            $chkCol = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'products' AND column_name IN ('country_code', 'university_id')");
            $foundCols = $chkCol->fetchAll(PDO::FETCH_COLUMN);
            $hasCountryCol = in_array('country_code', $foundCols, true);
            $hasUnivCol = in_array('university_id', $foundCols, true);
        } else {
            $chkCol = $pdo->query("SHOW COLUMNS FROM products WHERE Field IN ('country_code', 'university_id')");
            $foundCols = $chkCol->fetchAll(PDO::FETCH_COLUMN);
            $hasCountryCol = in_array('country_code', $foundCols, true);
            $hasUnivCol = in_array('university_id', $foundCols, true);
        }
    } catch (Throwable $ignoreColChk) {}

    if ($existingProductId) {
        $productId = (int)$existingProductId;
        $updateSql = "UPDATE products SET category_id = ?, title = ?, description = ?, price = ?, pricing_model = ?, " .
                     "delivery_days = ?, location_town = ?, availability_status = ?, portfolio_link = ?, status = 'pending_approval', " .
                     "service_expires_at = NULL, updated_at = NOW() WHERE id = ?";
        $updateStmt = $pdo->prepare($updateSql);
        $updateStmt->execute([$categoryId, $serviceTitle, $serviceDescription, $price, $pricingModel, $deliveryDays, $locationTown, $availabilityStatus, $portfolioLink, $productId]);
    } else {
        $conditionVal = ($driver === 'mysql') ? 'new' : null;
        $cols = ["user_id", "category_id", "title", "description", "price", "price_currency", $conditionQuote, "status", "listing_type", "pricing_model", "location_town"];
        $vals = [$userId, $categoryId, $serviceTitle, $serviceDescription, $price, 'TRY', $conditionVal, 'pending_approval', 'service', $pricingModel, $locationTown];
        $placeholders = ["?", "?", "?", "?", "?", "?", "?", "?", "?", "?", "?"];

        if ($hasCountryCol) {
            $cols[] = "country_code";
            $vals[] = $sellerCountry;
            $placeholders[] = "?";
        }
        if ($hasUnivCol) {
            $cols[] = "university_id";
            $vals[] = $sellerUniversityId;
            $placeholders[] = "?";
        }

        $cols[] = "delivery_days";
        $vals[] = $deliveryDays;
        $placeholders[] = "?";

        $cols[] = "availability_status";
        $vals[] = $availabilityStatus;
        $placeholders[] = "?";

        $cols[] = "portfolio_link";
        $vals[] = $portfolioLink;
        $placeholders[] = "?";

        $cols[] = "service_expires_at";
        $placeholders[] = "NULL";
        $cols[] = "created_at";
        $placeholders[] = "NOW()";
        $cols[] = "updated_at";
        $placeholders[] = "NOW()";

        $insertSql = "INSERT INTO products (" . implode(", ", $cols) . ") VALUES (" . implode(", ", $placeholders) . ")";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute($vals);
        $productId = (int)$pdo->lastInsertId();
    }

    // 4. Update product_categories junction table
    try {
        if ($driver === 'pgsql') {
            $catJuncSql = "INSERT INTO product_categories (product_id, category_id, is_primary) VALUES (?, ?, 1) " .
                          "ON CONFLICT (product_id, category_id) DO UPDATE SET is_primary = 1";
        } else {
            $catJuncSql = "INSERT INTO product_categories (product_id, category_id, is_primary) VALUES (?, ?, 1) " .
                          "ON DUPLICATE KEY UPDATE is_primary = 1";
        }
        $pdo->prepare($catJuncSql)->execute([$productId, $categoryId]);
    } catch (Throwable $ignoreCat) {}

    // 5. Handle image upload if user uploaded a primary cover/portfolio image
    if (!empty($_FILES['cover_image']['name'])) {
        $fileData = [
            'name'     => $_FILES['cover_image']['name'],
            'type'     => $_FILES['cover_image']['type'],
            'tmp_name' => $_FILES['cover_image']['tmp_name'],
            'error'    => $_FILES['cover_image']['error'],
            'size'     => $_FILES['cover_image']['size']
        ];
        $upload = handleUpload($fileData, 'products/');
        if (!$upload['success']) {
            throw new Exception("Cover photo error: " . $upload['error']);
        }
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$productId]);
        $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, 1)");
        $stmtImg->execute([$productId, $upload['path']]);
    }

    // 6. Save skill tags to tags & product_tags table
    if (!empty($parsedSkills)) {
        foreach ($parsedSkills as $skillName) {
            $cleanTag = trim(preg_replace('/^#+/', '', (string)$skillName));
            if (mb_strlen($cleanTag) < 2) continue;
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $cleanTag), '-'));
            if (empty($slug)) $slug = 'tag-' . substr(md5($cleanTag), 0, 8);

            try {
                if ($driver === 'pgsql') {
                    $tagStmt = $pdo->prepare("INSERT INTO tags (name, slug) VALUES (?, ?) ON CONFLICT (slug) DO NOTHING RETURNING id");
                    $tagStmt->execute([$cleanTag, $slug]);
                    $tagId = $tagStmt->fetchColumn();
                } else {
                    $tagStmt = $pdo->prepare("INSERT IGNORE INTO tags (name, slug) VALUES (?, ?)");
                    $tagStmt->execute([$cleanTag, $slug]);
                    $tagId = $pdo->lastInsertId();
                }
                if (!$tagId) {
                    $fStmt = $pdo->prepare("SELECT id FROM tags WHERE slug = ? OR LOWER(name) = LOWER(?) LIMIT 1");
                    $fStmt->execute([$slug, $cleanTag]);
                    $tagId = $fStmt->fetchColumn();
                }
                if ($tagId) {
                    if ($driver === 'pgsql') {
                        $pdo->prepare("INSERT INTO product_tags (product_id, tag_id) VALUES (?, ?) ON CONFLICT DO NOTHING")->execute([$productId, $tagId]);
                    } else {
                        $pdo->prepare("INSERT IGNORE INTO product_tags (product_id, tag_id) VALUES (?, ?)")->execute([$productId, $tagId]);
                    }
                }
            } catch (Throwable $ignoreTag) {}
        }
    }

    $pdo->commit();

    if (function_exists('queuePostHogEvent')) {
        queuePostHogEvent('provider_enrolled', [
            'user_id' => $userId,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'pricing_model' => $pricingModel,
            'price' => $price,
            'location_town' => $locationTown,
            'edited_fields' => $editedFields,
            'edited_count' => count($editedFields),
            'is_update' => !empty($existingProductId)
        ]);
    }

    setFlash('success', '✅ Your pilot service listing has been submitted for review. We will notify you once it is approved and live on the campus directory. This usually takes up to 48 hours.');
    redirect(BASE_URL . 'pages/product.php?id=' . $productId);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("[enroll_provider] Enrollment failed: " . $e->getMessage());
    setFlash('error', 'Could not complete enrollment: ' . $e->getMessage());
    redirect(BASE_URL . 'pages/provider_onboard.php');
}
