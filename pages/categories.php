<?php
// pages/categories.php
require_once '../config/constants.php';
require_once '../includes/header.php';

$pageTitle = "Browse Categories";

// Fetch all categories from the database
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

// Optional: array of emojis mappings based on some common categories
$catEmojis = [
    'Textbooks' => '📚',
    'Electronics' => '💻',
    'Furniture' => '🛋️',
    'Clothing' => '👕',
    'Services' => '🛠️',
    'Kitchen' => '🍳',
    'Decor' => '🪴',
    'Tickets' => '🎟️'
];
?>

<div class="container min-h-screen pt-12 pb-20 relative">
    <!-- Background Accents -->


    <div class="mb-10 text-center lg:text-left">
        <h1 class="font-bold text-4xl mb-3 gradient-text" style="background: linear-gradient(135deg, var(--text-main), var(--primary)); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Explore Categories</h1>
        <p class="text-muted text-lg max-w-2xl">Find exactly what you need by browsing our organized campus marketplace categories.</p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mt-8">
        <?php if (count($categories) > 0): ?>
            <?php foreach ($categories as $cat): ?>
                <?php $emoji = $catEmojis[$cat['name']] ?? '📦'; ?>
                <a href="browse.php?category=<?php echo $cat['id']; ?>" class="glass-panel hover-scale relative group overflow-hidden" style="text-decoration: none; color: inherit; border-radius: var(--radius-xl); padding: 2.5rem 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; border: 1px solid rgba(255,255,255,0.6); box-shadow: var(--shadow-sm); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); background: rgba(255,255,255,0.7);">
                    
                    <div style="position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(90deg, var(--primary), var(--secondary)); opacity: 0; transition: opacity 0.3s ease;" class="group-hover:opacity-100"></div>
                    
                    <div style="width: 70px; height: 70px; border-radius: var(--radius-lg); background: linear-gradient(135deg, var(--bg-main), white); display: flex; align-items: center; justify-content: center; font-size: 2rem; box-shadow: inset 0 2px 5px rgba(0,0,0,0.02), 0 4px 10px rgba(0,0,0,0.05); transition: transform 0.3s ease;" class="group-hover:scale-110">
                        <?php echo $emoji; ?>
                    </div>
                    
                    <div>
                        <h3 class="mb-1 font-bold text-main" style="font-size: 1.25rem; transition: color 0.3s ease;" class="group-hover:text-primary"><?php echo sanitize($cat['name']); ?></h3>
                        <span class="text-primary font-bold text-sm opacity-0 group-hover:opacity-100 transition-opacity" style="transform: translateY(10px); display: block;">Browse Items →</span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-span-full glass-panel p-16 text-center" style="border-radius: var(--radius-xl); border: 2px dashed rgba(0,0,0,0.05);">
                <div class="text-6xl mb-4 opacity-30">🗂️</div>
                <h3 class="text-2xl font-bold text-main mb-2">No categories yet</h3>
                <p class="text-muted">Check back later once the administator sets up the categories.</p>
            </div>
        <?php endif; ?>
    </div>

    <div class="mt-16 text-center">
        <a href="../index.php" class="btn btn-secondary shadow-sm hover-scale items-center gap-2" style="border-radius: var(--radius-lg); padding: 0.8rem 2rem; display: inline-flex; font-weight: bold;">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Home
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
