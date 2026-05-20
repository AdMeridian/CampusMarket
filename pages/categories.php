<?php
// pages/categories.php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = __('categories.browse_categories');

require_once __DIR__ . '/../includes/header.php';

// Fetch all categories from the database
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

$catIcons = [
    'Textbooks' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>',
    'Electronics' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>',
    'Furniture' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
    'Clothing' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>',
    'Services' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
    'Kitchen' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line stroke-linecap="round" stroke-linejoin="round" stroke-width="2" x1="6" y1="1" x2="6" y2="4"></line><line stroke-linecap="round" stroke-linejoin="round" stroke-width="2" x1="10" y1="1" x2="10" y2="4"></line><line stroke-linecap="round" stroke-linejoin="round" stroke-width="2" x1="14" y1="1" x2="14" y2="4"></line></svg>',
    'Decor' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>',
    'Tickets' => '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>'
];
?>

<div class="container min-h-screen pt-12 pb-20 relative">
    <div class="mb-10 text-center lg:text-left">
        <h1 class="font-bold text-4xl mb-3 text-main"><?= __('categories.explore_title') ?></h1>
        <p class="text-muted text-lg max-w-2xl"><?= __('categories.explore_desc') ?></p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mt-8">
        <?php if (count($categories) > 0): ?>
            <?php foreach ($categories as $cat): ?>
                <?php $icon = $catIcons[$cat['name']] ?? '<svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>'; ?>
                <a href="browse.php?category=<?php echo $cat['id']; ?>" class="glass-panel hover-scale relative group overflow-hidden" style="text-decoration: none; color: inherit; border-radius: var(--radius-xl); padding: 2.5rem 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; border: 1px solid rgba(255,255,255,0.6); box-shadow: var(--shadow-sm); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: rgba(255,255,255,0.7);">
                    
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: var(--primary); opacity: 0; transition: opacity 0.3s ease;" class="group-hover:opacity-100"></div>
                    
                    <div style="width: 70px; height: 70px; border-radius: var(--radius-lg); background: var(--bg-surface); display: flex; align-items: center; justify-content: center; box-shadow: inset 0 2px 5px rgba(0,0,0,0.02), 0 4px 10px rgba(0,0,0,0.05); transition: transform 0.3s ease;" class="group-hover:scale-110">
                        <?php echo $icon; ?>
                    </div>
                    
                    <div>
                        <h3 class="mb-1 font-bold text-main" style="font-size: 1.25rem; transition: color 0.3s ease;" class="group-hover:text-primary"><?php echo sanitize(translateCategory($cat['name'])); ?></h3>
                        <span class="text-primary font-bold text-sm opacity-0 group-hover:opacity-100 transition-opacity" style="transform: translateY(10px); display: block;"><?= __('categories.browse_items') ?> →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full glass-panel p-16 text-center" style="border-radius: var(--radius-xl); border: 2px dashed rgba(0,0,0,0.05);">
                <div class="mb-4 opacity-30 flex justify-center">
                    <svg class="w-16 h-16 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                </div>
                <h3 class="text-2xl font-bold text-main mb-2"><?= __('categories.no_categories') ?></h3>
                <p class="text-muted"><?= __('categories.no_categories_desc') ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-16 text-center">
        <a href="../index.php" class="btn btn-secondary shadow-sm hover-scale items-center gap-2" style="border-radius: var(--radius-lg); padding: 0.8rem 2rem; display: inline-flex; font-weight: bold;">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            <?= __('categories.back_to_home') ?>
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
