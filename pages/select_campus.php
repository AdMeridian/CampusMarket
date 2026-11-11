<?php
// pages/select_campus.php
// Quick 1-step campus selection for first-time Google OAuth or new sign-ups.

require_once '../config/constants.php';
require_once '../includes/bootstrap.php';
require_once '../includes/functions_member2.php';

requireLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, username, email, university_id, other_university_name FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$currentUser = $stmt->fetch();

if (!$currentUser) {
    redirect(BASE_URL);
}

// If user clicks "Skip for now"
if (isset($_GET['skip']) && $_GET['skip'] === '1') {
    redirect(BASE_URL);
}

// If user already has a university assigned, send them to home
if (!empty($currentUser['university_id']) || !empty($currentUser['other_university_name'])) {
    $_SESSION['university_id'] = $currentUser['university_id'] ? (int)$currentUser['university_id'] : null;
    $_SESSION['other_university_name'] = !empty($currentUser['other_university_name']) ? (string)$currentUser['other_university_name'] : null;
    redirect(BASE_URL);
}

$errors = [];
$availableUniversities = getNorthernCyprusUniversities($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $universitySelection = trim((string)($_POST['university_id'] ?? ''));
    $otherUniversityName = trim((string)($_POST['other_university_name'] ?? ''));

    $uniId = null;
    if ($universitySelection === 'other') {
        if (!preg_match('/\A.{1,200}\z/us', $otherUniversityName)) {
            $errors['university'] = __('profile.university_other_required');
        } else {
            $otherUniversityName = trim($otherUniversityName);
        }
    } elseif ($universitySelection !== '') {
        $validUniversityIds = array_map('intval', array_column($availableUniversities, 'id'));
        if (!ctype_digit($universitySelection) || !in_array((int)$universitySelection, $validUniversityIds, true)) {
            $errors['university'] = __('profile.university_invalid');
        } else {
            $uniId = (int)$universitySelection;
            $otherUniversityName = '';
        }
    } else {
        $errors['university'] = __('profile.university_required');
    }

    if (empty($errors)) {
        $upd = $pdo->prepare('UPDATE users SET university_id = :uid, other_university_name = :other WHERE id = :id');
        $upd->execute([
            ':uid' => $uniId,
            ':other' => $otherUniversityName !== '' ? $otherUniversityName : null,
            ':id' => $userId
        ]);
        $_SESSION['university_id'] = $uniId;
        $_SESSION['other_university_name'] = $otherUniversityName !== '' ? $otherUniversityName : null;
        setFlash('success', __('profile.campus_welcome_saved'));
        redirect(BASE_URL);
    }
}

$pageTitle = __('profile.select_campus_title');
require_once '../includes/header.php';
?>

<div class="auth-page">
    <div class="auth-card" style="max-width: 480px;">
        <div class="auth-head" style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 2.8rem; margin-bottom: 0.5rem;" aria-hidden="true">🎓</div>
            <h1 style="font-size: 1.65rem; margin-bottom: 0.5rem;">
                <?= __('profile.select_campus_title') ?>
            </h1>
            <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5; margin: 0 auto; max-width: 380px;">
                <?= __('profile.select_campus_subtitle') ?>
            </p>
        </div>

        <?php if (!empty($errors['university'])): ?>
            <div class="flash flash-error mb-6">
                <?= sanitize($errors['university']) ?>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrfTokenField() ?>

            <div class="form-row mb-6">
                <label for="university_id" class="form-label" style="font-weight: 600;">
                    <?= __('profile.university_attended') ?>
                </label>
                <select id="university_id" name="university_id" class="premium-input w-full" required autofocus>
                    <option value="" disabled selected><?= __('profile.select_university_placeholder') ?></option>
                    <?php foreach ($availableUniversities as $university): ?>
                        <option value="<?= (int)$university['id'] ?>">
                            <?= htmlspecialchars($university['name'] . (!empty($university['city']) ? ' — ' . $university['city'] : ''), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="other"><?= __('profile.university_other') ?></option>
                </select>

                <div id="other_university_container" class="mt-3" style="display: none;">
                    <input type="text" id="other_university_name" name="other_university_name"
                           maxlength="200" placeholder="<?= htmlspecialchars(__('profile.university_other_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                           class="premium-input w-full">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-full py-4 shadow-sm" style="border-radius: var(--radius-md); font-weight: 600; font-size: 1.05rem;">
                <?= __('profile.select_campus_btn') ?> &rarr;
            </button>

            <div style="text-align: center; margin-top: 1.25rem;">
                <a href="<?= BASE_URL ?>pages/select_campus.php?skip=1" style="font-size: 0.88rem; color: var(--text-muted); text-decoration: underline;">
                    <?= __('profile.skip_for_now') ?>
                </a>
            </div>
        </form>
    </div>
</div>

<script>
  var universitySelect = document.getElementById('university_id');
  var otherUniversityContainer = document.getElementById('other_university_container');
  var otherUniversityName = document.getElementById('other_university_name');
  if (universitySelect && otherUniversityContainer) {
    function updateOtherUniversityVisibility() {
      otherUniversityContainer.style.display = universitySelect.value === 'other' ? 'block' : 'none';
      if (otherUniversityName) {
        otherUniversityName.required = universitySelect.value === 'other';
        if (universitySelect.value === 'other') {
          otherUniversityName.focus();
        }
      }
    }
    universitySelect.addEventListener('change', updateOtherUniversityVisibility);
  }
</script>

<?php require_once '../includes/footer.php'; ?>

