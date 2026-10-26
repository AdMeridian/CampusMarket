<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$currentUserId = isLoggedIn() ? (int) currentUserId() : 0;
$jobTowns = array_values(array_filter(locationTownSlugs(), static fn (string $town): bool => $town !== 'other'));
$showMine = isset($_GET['mine']) && $currentUserId > 0;
$showPostForm = isset($_GET['post']) && $currentUserId > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireLogin();
    verifyCsrfToken();

    $action = (string) ($_POST['action'] ?? '');
    $returnTo = BASE_URL . 'pages/service_jobs.php' . (!empty($_POST['mine']) ? '?mine=1' : '');

    if ($action === 'close') {
        $jobId = (int) ($_POST['job_id'] ?? 0);
        $stmt = $pdo->prepare(
            "UPDATE service_job_posts SET status = 'closed' WHERE id = ? AND poster_id = ? AND status = 'open'"
        );
        $stmt->execute([$jobId, $currentUserId]);

        if ($stmt->rowCount() !== 1) {
            setFlash('error', 'That request could not be closed. It may already be closed or no longer available.');
        } else {
            setFlash('success', 'Your service request is now closed.');
        }
        redirect($returnTo);
    }

    if ($action !== 'create') {
        setFlash('error', 'Please choose a valid action.');
        redirect(BASE_URL . 'pages/service_jobs.php?post=1');
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $locationTown = strtolower(trim((string) ($_POST['location_town'] ?? '')));
    $budgetInput = trim((string) ($_POST['budget_max'] ?? ''));
    $organizationName = trim((string) ($_POST['organization_name'] ?? ''));

    if (mb_strlen($title) < 8 || mb_strlen($title) > 120) {
        setFlash('error', 'Use a title between 8 and 120 characters.');
        redirect(BASE_URL . 'pages/service_jobs.php?post=1');
    }
    if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) {
        setFlash('error', 'Describe the work in 20 to 5,000 characters.');
        redirect(BASE_URL . 'pages/service_jobs.php?post=1');
    }
    if (mb_strlen($organizationName) > 120) {
        setFlash('error', 'Keep the business or team name under 120 characters.');
        redirect(BASE_URL . 'pages/service_jobs.php?post=1');
    }
    if (!in_array($locationTown, $jobTowns, true)) {
        setFlash('error', 'Please choose a valid town or Remote / Online.');
        redirect(BASE_URL . 'pages/service_jobs.php?post=1');
    }

    $categoryStmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? AND type = 'service' LIMIT 1");
    $categoryStmt->execute([$categoryId]);
    if (!$categoryStmt->fetchColumn()) {
        setFlash('error', 'Please select a valid service category.');
        redirect(BASE_URL . 'pages/service_jobs.php?post=1');
    }

    $budgetMax = null;
    if ($budgetInput !== '') {
        if (!is_numeric($budgetInput) || !is_finite((float) $budgetInput) || (float) $budgetInput <= 0 || (float) $budgetInput > 1000000) {
            setFlash('error', 'Enter a budget greater than zero and no more than ₺1,000,000, or leave it blank.');
            redirect(BASE_URL . 'pages/service_jobs.php?post=1');
        }
        $budgetMax = round((float) $budgetInput, 2);
    }

    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
    $insertStmt = $pdo->prepare(
        "INSERT INTO service_job_posts
            (poster_id, category_id, organization_name, title, description, location_town, budget_max, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insertStmt->execute([
        $currentUserId,
        $categoryId,
        $organizationName !== '' ? $organizationName : null,
        $title,
        $description,
        $locationTown,
        $budgetMax,
        $expiresAt,
    ]);

    setFlash('success', 'Your service request is posted for 30 days. Providers can contact you through CampusMarket messages.');
    redirect(BASE_URL . 'pages/service_jobs.php');
}

$search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$categoryId = (int) ($_GET['category'] ?? 0);
$location = strtolower(trim((string) ($_GET['location'] ?? '')));
if (!in_array($location, $jobTowns, true)) {
    $location = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$params = [];
$where = [];

if ($showMine) {
    $where[] = 'j.poster_id = ?';
    $params[] = $currentUserId;
} else {
    $where[] = "j.status = 'open' AND j.expires_at > NOW()";
}
if ($search !== '') {
    $where[] = '(LOWER(j.title) LIKE ? OR LOWER(j.description) LIKE ?)';
    $searchPattern = '%' . mb_strtolower($search) . '%';
    $params[] = $searchPattern;
    $params[] = $searchPattern;
}
if ($categoryId > 0) {
    $where[] = 'j.category_id = ?';
    $params[] = $categoryId;
}
if ($location !== '' && isValidLocationTown($location)) {
    $where[] = 'j.location_town = ?';
    $params[] = $location;
}

$whereSql = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM service_job_posts j WHERE {$whereSql}");
$countStmt->execute($params);
$totalJobs = (int) $countStmt->fetchColumn();

$pageSize = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 12;
$offset = ($page - 1) * $pageSize;
$jobsStmt = $pdo->prepare(
    "SELECT j.id, j.poster_id, j.category_id, j.organization_name, j.title, j.description, j.location_town,
            j.budget_max, j.status, j.expires_at, j.created_at,
            c.name AS category_name, u.username AS poster_name, u.avatar AS poster_avatar
     FROM service_job_posts j
     JOIN categories c ON c.id = j.category_id
     JOIN users u ON u.id = j.poster_id
     WHERE {$whereSql}
     ORDER BY CASE WHEN j.status = 'open' AND j.expires_at > NOW() THEN 0 ELSE 1 END,
              j.created_at DESC
     LIMIT {$pageSize} OFFSET {$offset}"
);
$jobsStmt->execute($params);
$jobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);

$serviceCategories = $pdo->query(
    "SELECT id, name FROM categories WHERE type = 'service' ORDER BY name ASC"
)->fetchAll(PDO::FETCH_ASSOC);
$hasFilters = $search !== '' || $categoryId > 0 || $location !== '';
$pageTitle = $showMine ? 'My Service Requests' : 'Service Requests';
$pageDescription = 'Find one-off service requests from people and businesses, or post work for student providers.';
include __DIR__ . '/../includes/header.php';
?>

<style>
.service-jobs-hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1.25rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-light);
}
.service-job-card {
    padding: 1.35rem;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    background: var(--bg-surface);
}
.service-job-card__meta {
    display: flex;
    gap: .5rem;
    flex-wrap: wrap;
    color: var(--text-muted);
    font-size: .84rem;
}
@media (max-width: 640px) {
    .service-job-card { padding: 1rem; }
    .service-jobs-hero .btn { width: 100%; justify-content: center; }
}
</style>

<main class="min-h-screen pt-24 pb-16">
    <div class="container" style="max-width: 1120px;">
        <header class="service-jobs-hero">
            <div>
                <span class="badge" style="background:var(--service-light);color:var(--service);">SERVICE REQUESTS</span>
                <h1 class="page-hero-title mt-3 mb-2"><?= $showMine ? 'My service requests' : 'Find service work on campus' ?></h1>
                <p class="page-subtitle mb-0" style="max-width:680px;">
                    Browse one-off requests from students, people, and businesses—or post a job and hear from providers through CampusMarket messages.
                </p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <?php if (isLoggedIn()): ?>
                    <a class="btn btn-secondary" href="<?= BASE_URL ?>pages/service_jobs.php?mine=1">My requests</a>
                    <a class="btn btn-primary" href="<?= BASE_URL ?>pages/service_jobs.php?post=1">+ Post a request</a>
                <?php else: ?>
                    <a class="btn btn-primary" href="<?= BASE_URL ?>pages/login.php?redirect=pages/service_jobs.php%3Fpost%3D1">Sign in to post a request</a>
                <?php endif; ?>
            </div>
        </header>

        <nav class="flex gap-3 mb-6" aria-label="Service marketplace">
            <a class="btn btn-secondary btn-sm" href="<?= BASE_URL ?>pages/services.php">Browse services</a>
            <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>pages/service_jobs.php">Service requests</a>
        </nav>

        <?php if ($showPostForm): ?>
        <section class="card p-6 mb-8" aria-labelledby="post-service-job-title">
            <div class="flex justify-between items-start gap-4 mb-5">
                <div>
                    <h2 id="post-service-job-title" class="page-section-title mb-1">Post a service request</h2>
                    <p class="text-muted mb-0">Tell providers what you need. Requests stay open for 30 days.</p>
                </div>
                <a class="text-muted" href="<?= BASE_URL ?>pages/service_jobs.php" aria-label="Cancel">Cancel</a>
            </div>
            <form method="POST" action="<?= BASE_URL ?>pages/service_jobs.php?post=1">
                <?= csrfTokenField() ?>
                <input type="hidden" name="action" value="create">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block font-bold mb-2" for="job-title">What do you need? <span class="text-danger">*</span></label>
                        <input class="premium-input w-full" id="job-title" name="title" minlength="8" maxlength="120" required placeholder="e.g. Need a photographer for a graduation portrait session">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block font-bold mb-2" for="job-description">Describe the work <span class="text-danger">*</span></label>
                        <textarea class="premium-input w-full" id="job-description" name="description" rows="5" minlength="20" maxlength="5000" required placeholder="Share the scope, preferred timing, and any details providers should know."></textarea>
                        <small class="text-muted">Keep contact details and payments on-platform until you’ve agreed on the work.</small>
                    </div>
                    <div>
                        <label class="block font-bold mb-2" for="job-category">Service category <span class="text-danger">*</span></label>
                        <select class="premium-input w-full" id="job-category" name="category_id" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($serviceCategories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>"><?= htmlspecialchars(translateCategory($category['name']), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-2" for="job-location">Location <span class="text-danger">*</span></label>
                        <select class="premium-input w-full" id="job-location" name="location_town" required>
                            <option value="">Choose a town</option>
                            <?php foreach ($jobTowns as $town): ?>
                                <option value="<?= htmlspecialchars($town, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(formatLocationTown($town), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold mb-2" for="job-organization">Business or team name <span class="text-muted font-normal">(optional)</span></label>
                        <input class="premium-input w-full" id="job-organization" name="organization_name" maxlength="120" placeholder="Shown with your account name">
                        <small class="text-muted">This name is self-reported and not a verification badge.</small>
                    </div>
                    <div>
                        <label class="block font-bold mb-2" for="job-budget">Maximum budget in TRY <span class="text-muted font-normal">(optional)</span></label>
                        <input class="premium-input w-full" id="job-budget" name="budget_max" type="number" min="1" max="1000000" step="0.01" inputmode="decimal" placeholder="e.g. 500">
                    </div>
                </div>
                <div class="flex justify-end mt-5">
                    <button class="btn btn-primary" type="submit">Post request</button>
                </div>
            </form>
        </section>
        <?php endif; ?>

        <form method="GET" action="<?= BASE_URL ?>pages/service_jobs.php" class="card p-4 mb-6">
            <?php if ($showMine): ?><input type="hidden" name="mine" value="1"><?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div class="md:col-span-2">
                    <label class="block font-bold mb-1" for="job-search">Search requests</label>
                    <input class="premium-input w-full" id="job-search" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search by service or skill">
                </div>
                <div>
                    <label class="block font-bold mb-1" for="job-filter-category">Category</label>
                    <select class="premium-input w-full" id="job-filter-category" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($serviceCategories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars(translateCategory($category['name']), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block font-bold mb-1" for="job-filter-location">Location</label>
                    <select class="premium-input w-full" id="job-filter-location" name="location">
                        <option value="">All locations</option>
                        <?php foreach ($jobTowns as $town): ?>
                            <option value="<?= htmlspecialchars($town, ENT_QUOTES, 'UTF-8') ?>" <?= $location === $town ? 'selected' : '' ?>><?= htmlspecialchars(formatLocationTown($town), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="md:col-span-4 flex gap-2">
                    <button type="submit" class="btn btn-primary">Search requests</button>
                    <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= BASE_URL ?>pages/service_jobs.php<?= $showMine ? '?mine=1' : '' ?>">Clear filters</a><?php endif; ?>
                </div>
            </div>
        </form>

        <div class="flex justify-between items-center gap-3 mb-4">
            <h2 class="page-section-title mb-0"><?= $showMine ? 'Your requests' : 'Open requests' ?></h2>
            <span class="text-muted"><?= number_format($totalJobs) ?> <?= $totalJobs === 1 ? 'request' : 'requests' ?></span>
        </div>

        <?php if ($jobs === []): ?>
            <section class="glass-panel p-10 text-center" style="border:1px dashed var(--border-light);border-radius:var(--radius-xl);">
                <div aria-hidden="true" style="font-size:2.5rem;">🧰</div>
                <h3 class="empty-state-title mt-3 mb-2"><?= $showMine ? 'No requests posted yet' : ($hasFilters ? 'No matching requests' : 'No open service requests yet') ?></h3>
                <p class="text-muted max-w-md mx-auto mb-5">
                    <?= $showMine
                        ? 'Post the work you need and local providers can contact you.'
                        : ($hasFilters
                            ? 'Try another category or location, or clear your filters to see all open requests.'
                            : 'Check back soon, or post a request to let providers know what you need.') ?>
                </p>
                <div class="flex justify-center gap-2 flex-wrap">
                    <?php if ($hasFilters): ?><a class="btn btn-secondary" href="<?= BASE_URL ?>pages/service_jobs.php">Clear filters</a><?php endif; ?>
                    <?php if (isLoggedIn()): ?><a class="btn btn-primary" href="<?= BASE_URL ?>pages/service_jobs.php?post=1">Post a request</a><?php endif; ?>
                    <a class="btn btn-secondary" href="<?= BASE_URL ?>pages/services.php">Browse services</a>
                </div>
            </section>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <?php foreach ($jobs as $job): ?>
                    <?php
                    $isOpen = $job['status'] === 'open' && strtotime((string) $job['expires_at']) > time();
                    $isOwner = $currentUserId > 0 && $currentUserId === (int) $job['poster_id'];
                    ?>
                    <article class="service-job-card">
                        <div class="flex justify-between items-start gap-3 mb-3">
                            <span class="badge" style="background:var(--service-light);color:var(--service);"><?= htmlspecialchars(translateCategory($job['category_name']), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!$isOpen): ?><span class="badge"><?= $job['status'] === 'closed' ? 'Closed' : 'Expired' ?></span><?php endif; ?>
                        </div>
                        <h3 class="mb-2 text-main" style="font-size:1.08rem;font-weight:750;"><?= htmlspecialchars($job['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <p class="text-muted mb-4" style="white-space:pre-line;"><?= htmlspecialchars($job['description'], ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="service-job-card__meta mb-4">
                            <span><?= htmlspecialchars(formatLocationTown($job['location_town']), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($job['budget_max'] !== null): ?><span>·</span><span>Budget up to ₺<?= number_format((float) $job['budget_max'], 2) ?></span><?php endif; ?>
                            <span>·</span>
                            <span>Posted by <a href="<?= BASE_URL ?>pages/profile.php?id=<?= (int) $job['poster_id'] ?>"><?= !empty($job['organization_name']) ? htmlspecialchars($job['organization_name'], ENT_QUOTES, 'UTF-8') . ' · ' : '' ?>@<?= htmlspecialchars($job['poster_name'], ENT_QUOTES, 'UTF-8') ?></a></span>
                            <span>·</span>
                            <span><?= htmlspecialchars(date('M j, Y', strtotime((string) $job['created_at'])), ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="flex gap-2 flex-wrap">
                            <?php if ($isOpen && !$isOwner): ?>
                                <?php if (isLoggedIn()): ?>
                                    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>pages/messages.php?other_user_id=<?= (int) $job['poster_id'] ?>&amp;product_id=0&amp;job_id=<?= (int) $job['id'] ?>">Message about this request</a>
                                <?php else: ?>
                                    <a class="btn btn-primary btn-sm" href="<?= BASE_URL ?>pages/login.php?redirect=<?= rawurlencode('pages/messages.php?other_user_id=' . (int) $job['poster_id'] . '&product_id=0&job_id=' . (int) $job['id']) ?>">Sign in to contact</a>
                                <?php endif; ?>
                            <?php elseif ($isOpen && $isOwner): ?>
                                <form method="POST" action="<?= BASE_URL ?>pages/service_jobs.php?mine=1" onsubmit="return confirm('Close this service request?');">
                                    <?= csrfTokenField() ?>
                                    <input type="hidden" name="action" value="close">
                                    <input type="hidden" name="job_id" value="<?= (int) $job['id'] ?>">
                                    <input type="hidden" name="mine" value="1">
                                    <button class="btn btn-secondary btn-sm" type="submit">Close request</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($totalJobs > $pageSize): ?>
                <?php
                $paginationQuery = $_GET;
                unset($paginationQuery['page']);
                $paginationBase = 'service_jobs.php' . ($paginationQuery ? '?' . http_build_query($paginationQuery) : '');
                ?>
                <div class="mt-6"><?= paginationLinks($totalJobs, $page, $paginationBase) ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
