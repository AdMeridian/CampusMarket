<?php
// actions/toggle_wishlist.php
require_once __DIR__ . '/../includes/bootstrap.php';

// Check if user is logged in
if (!isLoggedIn()) {
    setFlash('error', 'Please login to save items to your wishlist.');
    redirect(BASE_URL . 'pages/login.php');
}

$user_id = currentUserId();
$product_id = $_POST['product_id'] ?? null;

if (!$product_id) {
    // Redirect immediately to the wishlist page
    redirect(BASE_URL . 'pages/wishlist.php');
}

try {
    // Check if already in wishlist
    $stmt = $pdo->prepare("SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Remove it
        $stmt = $pdo->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        setFlash('success', 'Product removed from your wishlist.');
    } else {
        // Block owner from saving their own product
        $ownerCheck = $pdo->prepare("SELECT 1 FROM products WHERE id = ? AND user_id = ?");
        $ownerCheck->execute([$product_id, $user_id]);
        if ($ownerCheck->fetch()) {
            setFlash('error', 'You cannot save your own listing.');
            redirect($_POST['redirect_to'] ?? (BASE_URL . 'pages/wishlist.php'));
        }
        // Add it
        $stmt = $pdo->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $product_id]);
        setFlash('success', 'Product saved to your wishlist!');
    }

    // Redirect back if requested, otherwise to wishlist
    $redirect = $_POST['redirect_to'] ?? (BASE_URL . 'pages/wishlist.php');
    redirect($redirect);

} catch (PDOException $e) {
    // Log error if needed and show message
    setFlash('error', 'An error occurred while updating your wishlist.');
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
}

