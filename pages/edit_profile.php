<?php
// pages/edit_profile.php — Member 2
// Update your own username, phone, and avatar.
// Email + password are NOT editable here (per project spec).

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';

requireLogin();

// Admins are moderators only — no personal profile to edit
if (isAdmin()) {
    setFlash('error', 'Administrators do not have a user profile. Use the Admin Panel.');
    redirect(BASE_URL . 'admin/index.php');
}

$uid = (int) currentUserId();

// Load current user.
$stmt = $pdo->prepare('SELECT id, username, email, phone, avatar FROM users WHERE id = :id');
$stmt->execute([':id' => $uid]);
$user = $stmt->fetch();

if (!$user) {
    // Session points at a user that no longer exists — nuke it.
    $_SESSION = [];
    session_destroy();
    redirect(BASE_URL . '/pages/login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    // Username
    if ($username === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors['username'] = '3–50 characters. Letters, numbers, underscores only.';
    } elseif (strcasecmp($username, $user['username']) !== 0) {
        // Only hit DB when the value actually changed.
        $chk = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1');
        $chk->execute([':u' => $username, ':id' => $uid]);
        if ($chk->fetch()) {
            $errors['username'] = 'That username is already taken.';
        }
    }

    // Phone (optional)
    if ($phone !== '' && !preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $phone)) {
        $errors['phone'] = 'Phone number looks invalid.';
    }

    // Avatar upload — use Member 1's uploadImage() helper.
    $newAvatar = null;
    if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploaded = uploadImage($_FILES['avatar'], 'avatars');
        if ($uploaded === false) {
            $errors['avatar'] = 'Avatar upload failed. Use JPEG/PNG/WebP/GIF under 5 MB.';
        } else {
            $newAvatar = $uploaded;
        }
    }

    // Persist
    if (!$errors) {
        if ($newAvatar !== null) {
            // Best-effort cleanup of the old avatar file.
            if (!empty($user['avatar'])) {
                $oldAbs = ROOT_PATH . 'public/' . $user['avatar'];
                if (is_file($oldAbs)) @unlink($oldAbs);
            }
            $upd = $pdo->prepare('
                UPDATE users
                SET username = :u, phone = :p, avatar = :a
                WHERE id = :id
            ');
            $upd->execute([
                ':u'  => $username,
                ':p'  => $phone !== '' ? $phone : null,
                ':a'  => $newAvatar,
                ':id' => $uid,
            ]);
        } else {
            $upd = $pdo->prepare('
                UPDATE users
                SET username = :u, phone = :p
                WHERE id = :id
            ');
            $upd->execute([
                ':u'  => $username,
                ':p'  => $phone !== '' ? $phone : null,
                ':id' => $uid,
            ]);
        }

        // Keep session in sync with the new username.
        $_SESSION['username'] = $username;

        setFlash('success', 'Profile updated successfully!');
        redirect(BASE_URL . '/pages/profile.php');
    }

    // On error, keep typed values visible.
    $user['username'] = $username;
    $user['phone']    = $phone;
}

$pageTitle = 'Edit profile';
require_once '../includes/header.php';
?>

<div class="container mt-12 mb-20 flex justify-center">
  <div class="glass-panel" style="width: 100%; max-width: 650px; border-radius: var(--radius-lg); overflow: hidden;">
      <div style="background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(16,185,129,0.1)); padding: 2rem; border-bottom: 1px solid rgba(0,0,0,0.05);">
        <div class="flex items-center justify-between">
            <h1 class="mb-0 text-main font-bold" style="letter-spacing: -0.5px;">Edit Profile</h1>
            <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="btn btn-secondary btn-sm hover-scale shadow-sm" style="border-radius: var(--radius-lg);">Cancel</a>
        </div>
        <p class="text-muted mt-2 mb-0">Update your public identity and contact details.</p>
      </div>

      <div style="padding: 2.5rem;">
        <form method="post" enctype="multipart/form-data" novalidate>
            <!-- Avatar Section -->
            <div class="flex items-center gap-6 mb-8 p-4" style="background: rgba(255,255,255,0.5); border-radius: var(--radius-md); border: 1px dashed var(--border-focus);">
                <img src="<?php echo sanitize(avatarUrl($user['avatar'])); ?>" alt="Avatar" class="shadow-sm" style="width: 90px; height: 90px; border-radius: var(--radius-xl); object-fit: cover; border: 3px solid white;">
                <div class="flex-grow">
                    <label class="form-label font-bold mb-2">Profile Picture</label>
                    <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control premium-input p-2 <?php echo isset($errors['avatar']) ? 'border-accent' : ''; ?>" style="font-size: 0.9rem;">
                    <?php if (isset($errors['avatar'])): ?>
                        <div class="text-sm mt-2 font-medium" style="color: #dc2626;"><?php echo sanitize($errors['avatar']); ?></div>
                    <?php else: ?>
                        <div class="text-muted small mt-2">JPEG, PNG, WebP, or GIF · Max 5MB</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Fields -->
            <div class="grid gap-6">
                <div>
                    <label for="username" class="form-label font-bold">Username</label>
                    <input type="text" id="username" name="username" value="<?php echo sanitize($user['username']); ?>" maxlength="50" required class="form-control premium-input <?php echo isset($errors['username']) ? 'border-accent' : ''; ?>">
                    <?php if (isset($errors['username'])): ?>
                        <div class="text-sm mt-2 font-medium" style="color: #dc2626;"><?php echo sanitize($errors['username']); ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="phone" class="form-label font-bold">Contact Number (Optional)</label>
                    <input type="tel" id="phone" name="phone" value="<?php echo sanitize($user['phone'] ?? ''); ?>" maxlength="20" class="form-control premium-input <?php echo isset($errors['phone']) ? 'border-accent' : ''; ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <div class="text-sm mt-2 font-medium" style="color: #dc2626;"><?php echo sanitize($errors['phone']); ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <label class="form-label font-bold">University Email</label>
                    <input type="email" value="<?php echo sanitize($user['email']); ?>" disabled class="form-control" style="background: rgba(241, 245, 249, 0.6); color: var(--text-muted); cursor: not-allowed; border: 1px border-light;">
                    <div class="text-muted small mt-2">Verified university emails cannot be changed. Contact support if you lost access.</div>
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid var(--border-light); margin: 2rem 0;">

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary px-8 py-3 hover-scale shadow-lg font-bold" style="border-radius: var(--radius-lg);">Save Changes</button>
            </div>
        </form>
      </div>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
