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

// Load current user (support older local schemas that may not yet have preferred_language).
try {
    $stmt = $pdo->prepare('SELECT id, username, email, phone, avatar, preferred_language FROM users WHERE id = :id');
    $stmt->execute([':id' => $uid]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    $stmt = $pdo->prepare('SELECT id, username, email, phone, avatar FROM users WHERE id = :id');
    $stmt->execute([':id' => $uid]);
    $user = $stmt->fetch();
    if ($user) {
        $user['preferred_language'] = DEFAULT_LANGUAGE;
    }
}

if (!$user) {
    // Session points at a user that no longer exists — nuke it.
    $_SESSION = [];
    session_destroy();
    redirect(BASE_URL . '/pages/login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    verifyCsrfToken();

    $username = trim($_POST['username'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $preferredLanguage = trim($_POST['preferred_language'] ?? '');

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

    // Language validation
    if (empty($preferredLanguage) || !array_key_exists($preferredLanguage, SUPPORTED_LANGUAGES)) {
        $preferredLanguage = DEFAULT_LANGUAGE;
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
    } elseif (!empty($_POST['selected_preset'])) {
        $preset = sanitize($_POST['selected_preset']);
        $validPresets = [
            'images/avatars/avatar_scholar.svg',
            'images/avatars/avatar_coder.svg',
            'images/avatars/avatar_artist.svg',
            'images/avatars/avatar_athlete.svg',
            'images/avatars/avatar_gamer.svg',
            'images/avatars/avatar_bookworm.svg'
        ];
        if (in_array($preset, $validPresets, true)) {
            $newAvatar = $preset;
        }
    }

    // Persist
    if (!$errors) {
        if ($newAvatar !== null) {
            // Best-effort cleanup of old uploaded files (skip preset SVGs).
            if (!empty($user['avatar']) && strpos($user['avatar'], 'uploads/') === 0) {
                $oldAbs = ROOT_PATH . 'public/' . $user['avatar'];
                if (is_file($oldAbs)) @unlink($oldAbs);
            }
            try {
                $upd = $pdo->prepare('
                    UPDATE users
                    SET username = :u, phone = :p, avatar = :a, preferred_language = :lang
                    WHERE id = :id
                ');
                $upd->execute([
                    ':u'  => $username,
                    ':p'  => $phone !== '' ? $phone : null,
                    ':a'  => $newAvatar,
                    ':lang' => $preferredLanguage,
                    ':id' => $uid,
                ]);
            } catch (PDOException $e) {
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
            }
        } else {
            try {
                $upd = $pdo->prepare('
                    UPDATE users
                    SET username = :u, phone = :p, preferred_language = :lang
                    WHERE id = :id
                ');
                $upd->execute([
                    ':u'  => $username,
                    ':p'  => $phone !== '' ? $phone : null,
                    ':lang' => $preferredLanguage,
                    ':id' => $uid,
                ]);
            } catch (PDOException $e) {
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
        }

        // Keep session in sync with the new username and language preference.
        $_SESSION['username'] = $username;
        $_SESSION['preferred_language'] = $preferredLanguage;
        i18nInit($preferredLanguage);

        setFlash('success', __('profile.updated_success'));
        redirect(BASE_URL . '/pages/profile.php');
    }

    // On error, keep typed values visible.
    $user['username'] = $username;
    $user['phone']    = $phone;
    $user['preferred_language'] = $preferredLanguage;
}

$pageTitle = __('profile.edit_title');
require_once '../includes/header.php';
?>

<div class="container mt-12 mb-20 flex justify-center">
  <div class="glass-panel" style="width: 100%; max-width: 650px; border-radius: var(--radius-lg); overflow: hidden;">
      <div style="background: var(--bg-surface); padding: 2rem; border-bottom: 1px solid var(--border-light);">
        <div class="flex items-center justify-between">
            <h1 class="mb-0 text-main font-bold" style="letter-spacing: -0.5px;"><?= __('profile.edit_title') ?></h1>
            <a href="<?php echo BASE_URL; ?>/pages/profile.php" class="btn btn-secondary btn-sm hover-scale shadow-sm flex items-center gap-1" style="border-radius: var(--radius-lg); display: inline-flex;">
                <svg xmlns="http://www.w3.org/2000/svg" style="width: 14px; height: 14px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                <?= __('profile.cancel') ?>
            </a>
        </div>
        <p class="text-muted mt-2 mb-0"><?= __('profile.update_desc') ?></p>
      </div>

      <div style="padding: 2.5rem;">
        <form method="post" enctype="multipart/form-data" novalidate>
            <?php echo csrfTokenField(); ?>
            <!-- Avatar Section -->
            <div class="flex flex-col gap-6 mb-8 p-6" style="background: rgba(255,255,255,0.5); border-radius: var(--radius-md); border: 1px dashed var(--border-focus);">
                <div class="flex items-center gap-6">
                    <img id="avatar-preview" src="<?php echo sanitize(avatarUrl($user['avatar'])); ?>" alt="Avatar" class="shadow-sm" style="width: 90px; height: 90px; border-radius: var(--radius-xl); object-fit: cover; border: 3px solid white; transition: var(--transition);">
                    <div class="flex-grow">
                        <label class="form-label font-bold mb-2"><?= __('profile.upload_picture') ?></label>
                        <input type="file" id="avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control premium-input p-2 <?php echo isset($errors['avatar']) ? 'border-accent' : ''; ?>" style="font-size: 0.9rem;">
                        <?php if (isset($errors['avatar'])): ?>
                            <div class="text-sm mt-2 font-medium" style="color: #dc2626;"><?php echo sanitize($errors['avatar']); ?></div>
                        <?php else: ?>
                            <div class="text-muted small mt-2"><?= __('profile.avatar_formats') ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-light); padding-top: 1.5rem;">
                    <label class="form-label font-bold mb-3 block text-main"><?= __('profile.choose_preset') ?></label>
                    <input type="hidden" id="selected-preset" name="selected_preset" value="">
                    
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-4">
                        <?php
                        $presets = [
                            ['path' => 'images/avatars/avatar_scholar.svg', 'name' => 'Scholar'],
                            ['path' => 'images/avatars/avatar_coder.svg', 'name' => 'Coder'],
                            ['path' => 'images/avatars/avatar_artist.svg', 'name' => 'Artist'],
                            ['path' => 'images/avatars/avatar_athlete.svg', 'name' => 'Athlete'],
                            ['path' => 'images/avatars/avatar_gamer.svg', 'name' => 'Gamer'],
                            ['path' => 'images/avatars/avatar_bookworm.svg', 'name' => 'Bookworm']
                        ];
                        foreach ($presets as $preset):
                            $isActive = ($user['avatar'] === $preset['path']);
                        ?>
                            <div class="avatar-preset-option flex flex-col items-center gap-2 <?php echo $isActive ? 'active' : ''; ?>" 
                                 data-path="<?php echo $preset['path']; ?>" 
                                 data-url="<?php echo avatarUrl($preset['path']); ?>"
                                 style="cursor: pointer; border: 3px solid <?php echo $isActive ? 'var(--primary)' : 'transparent'; ?>; border-radius: var(--radius-lg); padding: 0.5rem; transition: all 0.3s ease; background: <?php echo $isActive ? 'white' : '#f8fafc'; ?>; text-align: center; box-shadow: <?php echo $isActive ? '0 4px 12px rgba(99, 102, 241, 0.15)' : 'none'; ?>;">
                                <img src="<?php echo avatarUrl($preset['path']); ?>" alt="<?php echo $preset['name']; ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                <span class="font-bold text-main" style="font-size: 0.75rem;"><?php echo $preset['name']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Fields -->
            <div class="grid gap-6">
                <div>
                    <label for="username" class="form-label font-bold"><?= __('profile.username') ?></label>
                    <input type="text" id="username" name="username" value="<?php echo sanitize($user['username']); ?>" maxlength="50" required class="form-control premium-input <?php echo isset($errors['username']) ? 'border-accent' : ''; ?>">
                    <?php if (isset($errors['username'])): ?>
                        <div class="text-sm mt-2 font-medium" style="color: #dc2626;"><?php echo sanitize($errors['username']); ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="phone" class="form-label font-bold"><?= __('profile.contact_number') ?></label>
                    <input type="tel" id="phone" name="phone" value="<?php echo sanitize($user['phone'] ?? ''); ?>" maxlength="20" class="form-control premium-input <?php echo isset($errors['phone']) ? 'border-accent' : ''; ?>">
                    <?php if (isset($errors['phone'])): ?>
                        <div class="text-sm mt-2 font-medium" style="color: #dc2626;"><?php echo sanitize($errors['phone']); ?></div>
                    <?php endif; ?>
                </div>

                <div>
                    <label for="preferred_language" class="form-label font-bold"><?= __('profile.preferred_language') ?></label>
                    <select id="preferred_language" name="preferred_language" class="form-control premium-input" style="background: var(--bg-surface); color: var(--text-main); border: 1px solid var(--border-light); padding: 0.75rem; border-radius: var(--radius-md);">
                        <?php foreach (SUPPORTED_LANGUAGES as $code => $name): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= ($user['preferred_language'] ?? DEFAULT_LANGUAGE) === $code ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-muted small mt-2"><?= __('profile.language_note') ?></div>
                </div>

                <div>
                    <label class="form-label font-bold"><?= __('profile.university_email') ?></label>
                    <input type="email" value="<?php echo sanitize($user['email']); ?>" disabled class="form-control" style="background: rgba(241, 245, 249, 0.6); color: var(--text-muted); cursor: not-allowed; border: 1px solid var(--border-light); padding: 0.75rem; border-radius: var(--radius-md);">
                    <div class="text-muted small mt-2"><?= __('profile.email_note') ?></div>
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid var(--border-light); margin: 2rem 0;">

            <div class="flex justify-end">
                <button type="submit" class="btn btn-primary px-8 py-3 hover-scale shadow-lg font-bold" style="border-radius: var(--radius-lg);"><?= __('profile.save_changes') ?></button>
            </div>
        </form>
      </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const presetOptions = document.querySelectorAll('.avatar-preset-option');
    const selectedPresetInput = document.getElementById('selected-preset');
    const avatarFileInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('avatar-preview');

    presetOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active styles from all presets
            presetOptions.forEach(opt => {
                opt.classList.remove('active');
                opt.style.borderColor = 'transparent';
                opt.style.background = '#f8fafc';
                opt.style.boxShadow = 'none';
            });

            // Set active style for this preset
            this.classList.add('active');
            this.style.borderColor = 'var(--primary)';
            this.style.background = 'white';
            this.style.boxShadow = '0 4px 12px rgba(99, 102, 241, 0.15)';

            // Update hidden input & preview
            const path = this.dataset.path;
            const url = this.dataset.url;
            selectedPresetInput.value = path;
            avatarPreview.src = url;

            // Clear file input
            avatarFileInput.value = '';
        });
    });

    // If they choose a file, clear selected preset active styling
    avatarFileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            presetOptions.forEach(opt => {
                opt.classList.remove('active');
                opt.style.borderColor = 'transparent';
                opt.style.background = '#f8fafc';
                opt.style.boxShadow = 'none';
            });
            selectedPresetInput.value = '';
            
            // Show local preview of uploaded file
            const reader = new FileReader();
            reader.onload = function(e) {
                avatarPreview.src = e.target.result;
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
