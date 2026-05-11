<?php
/**
 * Product Card Template
 * Used in browse.php, index.php, and other listing pages.
 * 
 * Variables required:
 * @var array $prod The product data row
 */
global $pdo; // Ensure PDO is available if included inside a function scope
?>
<div class="card card-hover flex flex-col h-full" style="position: relative; border-radius: var(--radius-lg); border: 1px solid var(--border-light); background: var(--bg-surface); overflow: hidden; padding: 1.5rem; transition: var(--transition);">
    <!-- Main Product Link -->
    <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/product.php?id=<?php echo $prod['id']; ?>" style="text-decoration: none; display: flex; flex-direction: column; height: 100%;">
        <!-- Product Image Container -->
        <div class="product-card-image-wrap" style="border-radius: var(--radius-md); margin-bottom: 1.5rem;">
            <?php 
                $imgUrl = sanitize(rtrim(BASE_URL, '/') . '/public/images/default-product.png');
                if (!empty($prod['image_path'])) {
                    // Normalize path: if it starts with 'uploads/', it's already including the 'public/' relative root in some contexts
                    // but usually it's stored relative to 'public/'
                    $path = ltrim($prod['image_path'], '/');
                    $imgUrl = sanitize(rtrim(BASE_URL, '/') . '/public/' . $path);
                }
            ?>
            <img src="<?php echo $imgUrl; ?>" alt="<?php echo sanitize($prod['title']); ?>">
            
            <!-- Condition Badge -->
            <div style="position: absolute; top: 1rem; left: 1rem; z-index: 5;">
                <?php 
                $cond = $prod['condition'] ?? 'used';
                $badge = conditionBadge($cond); 
                ?>
                <span class="badge <?php echo $badge['class']; ?> shadow-sm" style="font-size: 0.7rem; padding: 0.4rem 0.85rem; backdrop-filter: blur(4px);">
                    <?php echo $badge['label']; ?>
                </span>
            </div>

            <!-- Featured / Ad Badge -->
            <?php if (!empty($prod['is_featured']) && (int)$prod['is_featured'] === 1): ?>
            <div style="position: absolute; top: 1rem; right: 1rem; z-index: 5;">
                <span class="badge" style="background: var(--primary); color: white; font-size: 0.7rem; padding: 0.4rem 0.85rem; box-shadow: 0 0 15px rgba(99, 102, 241, 0.4); border: 1px solid rgba(255,255,255,0.2); font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.25rem;">
                    <span style="display:inline-block; width: 6px; height: 6px; background: white; border-radius: 50%; animation: pulse 2s infinite;"></span>
                    Featured
                </span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Product Info -->
        <div class="flex flex-col flex-grow px-1">
            <p class="mb-2" style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.25rem;">(<?php echo sanitize($prod['category_name'] ?? ($prod['category'] ?? 'General')); ?>)</p>
            <h4 class="mb-3 text-main" style="font-size: 1.15rem; font-weight: 700; line-height: 1.3; margin-bottom: 1rem; flex-grow: 1;"><?php echo sanitize($prod['title']); ?></h4>
            
            <div class="mt-auto flex items-center gap-4">
                <span style="font-weight: 800; color: var(--text-main); font-size: 1.15rem; white-space: nowrap;"><?php echo renderProductPrice($prod); ?></span>
                <div style="height: 4px; width: 32px; background: var(--primary); border-radius: 3px;"></div>
            </div>
        </div>
    </a>

    <!-- Save for Later Button (Wishlist) -->
    <div style="position: absolute; bottom: 1.5rem; right: 1.5rem; z-index: 20;">
        <form action="<?php echo BASE_URL; ?>actions/toggle_wishlist.php" method="POST" style="margin: 0;">
            <input type="hidden" name="product_id" value="<?php echo $prod['id']; ?>">
            <?php 
                $isSaved = false;
                if (isLoggedIn()) {
                    global $userWishlistIds;
                    if (!isset($userWishlistIds)) {
                        $stmt = $pdo->prepare("SELECT product_id FROM wishlists WHERE user_id = ?");
                        $stmt->execute([currentUserId()]);
                        $userWishlistIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                    $isSaved = in_array($prod['id'], $userWishlistIds);
                }
            ?>
            <button type="submit" style="background: <?php echo $isSaved ? 'var(--error-bg)' : 'var(--bg-main)'; ?>; border: 1px solid var(--border-light); color: <?php echo $isSaved ? 'var(--error)' : 'var(--text-muted)'; ?>; padding: 0.6rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition);" title="<?php echo $isSaved ? 'Remove from Wishlist' : 'Save for Later'; ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
            </button>
        </form>
    </div>
</div>
