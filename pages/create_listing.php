<?php
// pages/create_listing.php
require_once '../includes/bootstrap.php';
requireLogin();

// Admins are moderators only — they cannot create listings
if (isAdmin()) {
    setFlash('error', 'Administrators cannot create listings. Use the Admin Panel to manage the marketplace.');
    redirect(BASE_URL . 'admin/index.php');
}

$pageTitle = "Create New Listing";
include '../includes/header.php';

$success = false;
$error = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $title       = sanitize($_POST['title']);
    $categoryId  = (int)$_POST['category_id'];
    $price       = (float)$_POST['price'];
    $condition   = sanitize($_POST['condition']);
    $description = sanitize($_POST['description']);
    $userId      = currentUserId();

    try {
        $pdo->beginTransaction();

        // 1. Insert Product
        $stmt = $pdo->prepare('INSERT INTO products (user_id, category_id, title, description, price, "condition", status) VALUES (?, ?, ?, ?, ?, ?, \'active\')');
        $stmt->execute([$userId, $categoryId, $title, $description, $price, $condition]);
        $productId = $pdo->lastInsertId();

        // 2. Handle Image Uploads
        if (!empty($_FILES['images']['name'][0])) {
            $files = $_FILES['images'];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($i >= MAX_IMAGES) break; // Limit per listing

                $fileData = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i]
                ];

                $upload = handleUpload($fileData, 'products/');
                if ($upload['success']) {
                    $isPrimary = ($i === 0);
                    $stmtImg = $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_primary) VALUES (?, ?, ?)");
                    $stmtImg->execute([$productId, $upload['path'], $isPrimary]);
                }
            }
        }

        $pdo->commit();
        $success = true;
        setFlash('success', 'Your listing is live!');
        
        // Redirect to promotions page to offer an up-sell
        redirect('promotions.php?product_id=' . $productId . '&new_listing=1');

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to create listing: " . $e->getMessage();
    }
}

// Fetch Categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>

<div class="container relative mt-24 mb-20 flex justify-center">
    <!-- Decorative elements -->



    <div class="w-full max-w-3xl">
        <div class="text-center mb-8">
            <h1 class="mb-2" style="font-size: 2.75rem;">List an Item</h1>
            <p class="text-muted text-lg">Reach thousands of students instantly</p>
        </div>

        <?php if ($error): ?>
            <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; color: #b91c1c; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 2rem; font-weight: 500;">
                <?php echo sanitize($error); ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel" style="padding: 2.5rem; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); z-index: 10;">
            <form action="create_listing.php" method="POST" enctype="multipart/form-data" class="grid gap-6">
                <?php echo csrfTokenField(); ?>
                
                <div class="form-group">
                    <label class="font-bold mb-2 block" style="color: var(--text-main);">What are you selling? *</label>
                    <input type="text" name="title" placeholder="e.g. Macbeth Textbook, 10th Edition" class="w-full premium-input" style="padding: 0.8rem 1rem;" required>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="form-group">
                        <label class="font-bold mb-2 block" style="color: var(--text-main);">Category *</label>
                        <select name="category_id" class="w-full premium-input" style="padding: 0.8rem 1rem;" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo sanitize($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="font-bold mb-2 block" style="color: var(--text-main);">Price (<?php echo APP_CURRENCY; ?>) *</label>
                        <div class="relative">
                            <input type="number" name="price" step="0.01" placeholder="0.00" class="w-full premium-input" style="padding: 0.8rem 1rem;" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-bold mb-2 block" style="color: var(--text-main);">Condition *</label>
                    <div class="flex flex-wrap gap-6">
                        <?php 
                        $opts = [
                            'new' => 'New',
                            'like_new' => 'Like New',
                            'used' => 'Used'
                        ];
                        $default = 'used';
                        foreach($opts as $val => $label):
                        ?>
                        <label class="condition-label group flex items-center gap-3 cursor-pointer glass-panel transition-all duration-200" style="border-radius: var(--radius-full); border: 2px solid transparent; padding: 0.55rem 1.25rem; min-width: 100px; justify-content: center;">
                            <input type="radio" name="condition" value="<?php echo $val; ?>" <?php echo $val == $default ? 'checked' : ''; ?> class="hidden-radio">
                            <span class="custom-radio"></span>
                            <span class="font-semibold text-main"><?php echo $label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="font-bold mb-2 block" style="color: var(--text-main);">Description *</label>
                    <textarea name="description" rows="5" placeholder="Mention age, defects, or why you're selling..." class="w-full premium-input" style="padding: 1rem; border-radius: var(--radius-lg);" required></textarea>
                </div>

                <div class="form-group">
                    <label class="font-bold mb-2 block" style="color: var(--text-main);">Photos (Max 5)</label>
                    <div class="border-2 border-dashed rounded-lg text-center cursor-pointer transition-colors" style="border-color: rgba(99,102,241,0.3); background: rgba(99,102,241,0.03); padding: 3rem 2rem; min-height: 180px; display: flex; flex-direction: column; align-items: center; justify-content: center;" onclick="document.getElementById('imgInput').click()" onmouseover="this.style.background='rgba(99,102,241,0.06)'" onmouseout="this.style.background='rgba(99,102,241,0.03)'">
                        <div class="mb-3 flex justify-center text-primary opacity-80">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <p class="font-bold mb-1" style="color: var(--primary); font-size: 1.05rem;">Click to Upload Images</p>
                        <p class="text-muted small">PNG, JPG up to 5MB &nbsp;·&nbsp; Max 5 photos</p>
                        <input type="file" id="imgInput" name="images[]" multiple accept="image/*" class="hidden">
                    </div>
                    <div id="preview" class="flex flex-wrap gap-4 mt-5"></div>
                </div>

                <hr style="border-color: rgba(0,0,0,0.05); margin: 1rem 0;">

                <div class="flex justify-between items-center">
                    <a href="browse.php" class="btn btn-secondary hover-scale shadow-sm flex items-center gap-2" style="padding: 0.75rem 1.5rem; border-radius: var(--radius-lg); font-weight: bold; font-size: 1.1rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" style="width: 18px; height: 18px;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-8 py-3 hover-scale shadow-lg" style="border-radius: var(--radius-lg); font-weight: bold; font-size: 1.1rem;">Publish Listing</button>
                </div>

            </form>
        </div>
    </div>
</div>

<style>
    .hidden-radio { position: absolute; opacity: 0; width: 0; height: 0; }
    
    .custom-radio {
        width: 20px;
        height: 20px;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-md);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: white;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .custom-radio::after {
        content: '';
        width: 10px;
        height: 10px;
        background: var(--primary);
        border-radius: var(--radius-lg);
        transform: scale(0);
        transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    /* Selected State */
    .hidden-radio:checked + .custom-radio {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
    }
    
    .hidden-radio:checked + .custom-radio::after {
        transform: scale(1);
    }

    /* Card highlighting */
    .condition-label:has(.hidden-radio:checked) {
        background: rgba(99, 102, 241, 0.05);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .condition-label:hover {
        background: rgba(99, 102, 241, 0.02);
        transform: translateY(-1px);
    }

    textarea { resize: vertical; }
</style>

<script>
let uploadedFiles = [];
const maxFiles = 5;

function updateFileInput() {
    const dt = new DataTransfer();
    uploadedFiles.forEach(file => dt.items.add(file));
    document.getElementById('imgInput').files = dt.files;
}

function renderPreviews() {
    const preview = document.getElementById('preview');
    preview.innerHTML = '';
    
    uploadedFiles.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = (re) => {
            const div = document.createElement('div');
            div.style = "position: relative; width:120px; height:120px; border-radius: var(--radius-md); overflow:hidden; border: 2px solid var(--primaryLight); box-shadow: var(--shadow-sm); flex-shrink: 0;";
            
            const img = document.createElement('img');
            img.src = re.target.result;
            img.style = "width:100%; height:100%; object-fit:cover;";
            
            const removeBtn = document.createElement('button');
            removeBtn.type = "button";
            removeBtn.innerHTML = "&times;";
            removeBtn.style = "position: absolute; top: 4px; right: 4px; background: rgba(0,0,0,0.6); color: white; border: none; border-radius: 50%; width: 24px; height: 24px; font-size: 16px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 10;";
            removeBtn.onclick = function(e) {
                e.stopPropagation();
                e.preventDefault();
                uploadedFiles.splice(index, 1);
                updateFileInput();
                renderPreviews();
            };
            
            div.appendChild(img);
            div.appendChild(removeBtn);
            preview.appendChild(div);
        }
        reader.readAsDataURL(file);
    });
}

document.getElementById('imgInput').addEventListener('change', function(e) {
    const newFiles = [...e.target.files];
    
    // Check if new selection was just the internal update
    if (newFiles.length === uploadedFiles.length) {
        let same = true;
        for (let i = 0; i < newFiles.length; i++) {
            if (newFiles[i] !== uploadedFiles[i]) {
                same = false; break;
            }
        }
        if (same) return;
    }
    
    for (let i = 0; i < newFiles.length; i++) {
        // Skip if already in array
        if (uploadedFiles.some(f => f.name === newFiles[i].name && f.size === newFiles[i].size)) continue;
        
        if (uploadedFiles.length < maxFiles) {
            uploadedFiles.push(newFiles[i]);
        } else {
            alert('You can only upload a maximum of ' + maxFiles + ' images.');
            break;
        }
    }
    
    updateFileInput();
    renderPreviews();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
