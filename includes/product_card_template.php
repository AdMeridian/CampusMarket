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
<?php
$isOwner = isLoggedIn() && (int)currentUserId() === (int)$prod['user_id'];
?>
<div class="card card-hover flex flex-col h-full" style="position: relative; border-radius: var(--radius-lg); border: 1px solid var(--border-light); background: var(--bg-surface); overflow: hidden; padding: 1.5rem; transition: var(--transition);">
    
    <!-- Main Product Link -->
    <a href="<?php echo rtrim(BASE_URL, '/'); ?>/pages/product.php?id=<?php echo $prod['id']; ?>" style="text-decoration: none; display: flex; flex-direction: column; height: 100%;">
        <!-- Product Image Container -->
        <div class="product-card-image-wrap" style="border-radius: var(--radius-md); margin-bottom: 1.5rem; position: relative;">
            <?php 
                $imgUrl = getProductImage($prod['image_path'] ?? null);
            ?>
            <img src="<?php echo $imgUrl; ?>" alt="<?php echo sanitize($prod['title']); ?>">
            
            <!-- Seller Badge (Top Right) -->
            <?php if ($isOwner): ?>
                <div style="position: absolute; top: 0.75rem; right: 0.75rem; background: var(--bg-surface); color: var(--primary); padding: 0.35rem 0.6rem; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; border-radius: var(--radius-md); z-index: 10; box-shadow: var(--shadow-sm); border: 1px solid var(--border-light); display: flex; align-items: center; gap: 4px;">
                    <svg class="w-2 h-2" fill="currentColor" viewBox="0 0 20 20" style="width: 10px; height: 10px;"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/><path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                    <?= __('product.your_listing') ?>
                </div>
            <?php elseif (!empty($prod['seller_name'])): ?>
                <div style="position: absolute; top: 0.75rem; right: 0.75rem; background: var(--glass-bg); color: var(--text-main); padding: 0.35rem 0.6rem; font-size: 0.65rem; font-weight: 600; border-radius: var(--radius-md); z-index: 10; border: 1px solid var(--glass-border); display: flex; align-items: center; gap: 4px; backdrop-filter: blur(4px);">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" style="width: 12px; height: 12px; opacity: 0.7;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    @<?php echo sanitize($prod['seller_name']); ?>
                </div>
            <?php endif; ?>

            <!-- Status Badges Container (Top Left) -->
            <div style="position: absolute; top: 0.75rem; left: 0.75rem; z-index: 5; display: flex; gap: 0.4rem; flex-wrap: wrap; max-width: calc(100% - 8.5rem);">
                <?php 
                $cond = $prod['condition'] ?? 'used';
                $badge = conditionBadge($cond); 
                ?>
                <span class="badge <?php echo $badge['class']; ?> shadow-sm" style="font-size: 0.7rem; padding: 0.35rem 0.75rem; backdrop-filter: blur(4px);">
                    <?php echo $badge['label']; ?>
                </span>
                
                <?php if (!empty($prod['discount_percent']) && (int)$prod['discount_percent'] > 0): ?>
                <span class="badge shadow-sm" style="background: #ef4444; color: white; font-size: 0.7rem; padding: 0.35rem 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                    <?= __('product.discounted') ?>
                </span>
                <?php endif; ?>

                <?php if (!empty($prod['is_featured']) && (int)$prod['is_featured'] === 1): ?>
                <span class="badge shadow-sm" style="background: var(--primary); color: white; font-size: 0.7rem; padding: 0.35rem 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; gap: 0.25rem;">
                    <svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                    <?= __('product.featured') ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Product Info -->
        <div class="flex flex-col flex-grow px-1">
            <?php
                $cardMeta = [];
                if (!empty($prod['location_town']) && $prod['location_town'] !== 'other') {
                    $cardMeta[] = formatLocationTown($prod['location_town']);
                }
                $cardMeta[] = translateCategory($prod['category_name'] ?? ($prod['category'] ?? 'General'));
            ?>
            <p class="mb-2" style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500; margin-bottom: 0.25rem;"><?php echo sanitize(implode(' · ', $cardMeta)); ?></p>
            <h4 class="mb-3 text-main" style="font-size: 1.15rem; font-weight: 600; line-height: 1.3; margin-bottom: 1rem; flex-grow: 1;"><?php echo sanitize($prod['title']); ?></h4>
            
            <div class="mt-auto flex flex-col gap-1">
                <div class="flex items-center gap-3">
                    <span style="font-weight: 700; color: var(--text-main); font-size: 1.15rem; white-space: nowrap;"><?php echo renderProductPrice($prod); ?></span>
                    <span class="text-muted" style="font-size: 0.75rem; opacity: 0.7;">• <?= __('product.listed_time', ['time' => timeAgo($prod['created_at'])]) ?></span>
                </div>
            </div>
        </div>
    </a>

    <!-- Save for Later Button (Wishlist) -->
    <div style="position: absolute; bottom: 1.5rem; right: 1.5rem; z-index: 20;">
        <form action="<?php echo BASE_URL; ?>actions/toggle_wishlist.php" method="POST" style="margin: 0;">
            <?php echo csrfTokenField(); ?>
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
            <button type="submit" style="background: <?php echo $isSaved ? 'var(--error-bg)' : 'var(--bg-main)'; ?>; border: 1px solid <?php echo $isSaved ? 'var(--error)' : 'var(--border-light)'; ?>; color: <?php echo $isSaved ? 'var(--error)' : 'var(--text-muted)'; ?>; padding: 0.6rem; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: var(--transition);" title="<?php echo $isSaved ? __('product.remove_wishlist') : __('product.save_later'); ?>">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="<?php echo $isSaved ? 'currentColor' : 'none'; ?>" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                </svg>
            </button>
        </form>
    </div>
</div>
