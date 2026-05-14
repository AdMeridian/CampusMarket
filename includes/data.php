<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$categories = [
    ['name'=>'Books','slug'=>'books','icon'=>'📚','count'=>'10 items'],
    ['name'=>'Electronics','slug'=>'electronics','icon'=>'💻','count'=>'12 items'],
    ['name'=>'Furniture','slug'=>'furniture','icon'=>'🪑','count'=>'8 items'],
    ['name'=>'Kitchen & Dining','slug'=>'kitchen','icon'=>'🍳','count'=>'12 items'],
    ['name'=>"Men's Clothing",'slug'=>'mens-clothing','icon'=>'👔','count'=>'5 items'],
    ['name'=>"Women's Clothing",'slug'=>'womens-clothing','icon'=>'👗','count'=>'5 items'],
    ['name'=>'Snacks & Beverages','slug'=>'snacks','icon'=>'🍫','count'=>'10 items'],
    ['name'=>'Stationery','slug'=>'stationery','icon'=>'✏️','count'=>'9 items'],
    ['name'=>'Bikes & Scooters','slug'=>'transportation','icon'=>'🚲','count'=>'10 items'],
    ['name'=>'iPhones & Tablets','slug'=>'devices','icon'=>'📱','count'=>'8 items'],
    ['name'=>'Personal Care & Hygiene','slug'=>'personal-care','icon'=>'🧴','count'=>'11 items'],
];

$products = [];
$id_counter = 1;

// Books
$book_data = [
    ['title'=>'Calculus Textbook', 'price'=>320, 'img'=>'../public/images/calculus-2.jpeg', 'desc'=>'Stewart Calculus: Early Transcendentals, 9th Edition. Perfect condition.'],
    ['title'=>'Physics 101 Notes', 'price'=>280, 'img'=>'../public/images/intro-to-physics.jpeg', 'desc'=>'Fundamentals of Physics by Halliday & Resnick. Great for exam prep.'],
    ['title'=>'Intro to Psychology', 'price'=>240, 'img'=>'../public/images/psychology.jpeg', 'desc'=>'Psychology: Core Concepts. A great introductory book.'],
    ['title'=>'Organic Chemistry', 'price'=>450, 'img'=>'../public/images/chemistry.jpeg', 'desc'=>'Organic Chemistry by Klein. Includes the solution manual.'],
    ['title'=>'World History', 'price'=>220, 'img'=>'../public/images/world-history.jpeg', 'desc'=>'The Earth and Its Peoples: A Global History. Excellent condition.'],
    ['title'=>'Microeconomics', 'price'=>260, 'img'=>'../public/images/microeconomics.jpeg', 'desc'=>'Microeconomics by Pindyck & Rubinfeld. Clear and concise.'],
    ['title'=>'Computer Science', 'price'=>380, 'img'=>'../public/images/computer-science.jpeg', 'desc'=>'Introduction to Algorithms (CLRS). The "bible" of CS.'],
    ['title'=>'Biology Text', 'price'=>350, 'img'=>'../public/images/biology.jpeg', 'desc'=>'Campbell Biology, 12th Edition. High-quality illustrations.'],
    ['title'=>'Linear Algebra', 'price'=>180, 'img'=>'../public/images/linear-algebra.jpeg', 'desc'=>'Linear Algebra and Its Applications by Gilbert Strang.'],
    ['title'=>'English Literature', 'price'=>150, 'img'=>'../public/images/english-literature.jpeg', 'desc'=>'The Norton Anthology of English Literature. All major works.'],
];
foreach($book_data as $b) {
    $cond = (stripos($b['desc'], 'perfect') !== false || stripos($b['desc'], 'excellent') !== false || stripos($b['desc'], 'new') !== false) ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$b['title'], 'price'=>$b['price'], 'category'=>'Books', 'condition'=>$cond, 'img'=>$b['img'], 'desc'=>$b['desc']];
}

// Electronics (gadgets, screens, computers — not kitchen appliances)
$elec_data = [
    ['title'=>'AirPods Pro (2nd Gen)', 'price'=>5500, 'img'=>'../public/images/airpods-pro.jpeg', 'desc'=>'Active Noise Cancellation and transparency mode. Like new, includes all tip sizes.'],
    ['title'=>'Wireless Headphones', 'price'=>1150, 'img'=>'../public/images/headphones.jpeg', 'desc'=>'Noise-canceling over-ear headphones. Crystal clear sound.'],
    ['title'=>'Gaming Mouse', 'price'=>720, 'img'=>'../public/images/gaming-mouse.jpeg', 'desc'=>'RGB gaming mouse with high DPI precision.'],
    ['title'=>'Mechanical Keyboard', 'price'=>1450, 'img'=>'../public/images/keyboard.jpeg', 'desc'=>'Mechanical keyboard with blue switches. Backlit keys.'],
    ['title'=>'Laptop Stand', 'price'=>380, 'img'=>'../public/images/laptop-stand.jpeg', 'desc'=>'Aluminum adjustable laptop stand. Improves posture.'],
    ['title'=>'Laptop Charger (USB-C)', 'price'=>550, 'img'=>'../public/images/laptop-charger.jpeg', 'desc'=>'Fast-charging USB-C laptop charger. Compatible with most laptops.'],
    ['title'=>'Monitor 24"', 'price'=>2900, 'img'=>'../public/images/monitor-pc.jpeg', 'desc'=>'1080p IPS Monitor with ultra-slim bezels.'],
    ['title'=>'Webcam 1080p', 'price'=>650, 'img'=>'../public/images/webcam.jpeg', 'desc'=>'Full HD webcam with built-in microphone.'],
    ['title'=>'Bluetooth Speaker', 'price'=>980, 'img'=>'../public/images/bluetooth-speaker.jpeg', 'desc'=>'Waterproof portable speaker with deep bass.'],
    ['title'=>'Smart TV 32"', 'price'=>3900, 'img'=>'../public/images/tv.jpeg', 'desc'=>'32-inch Smart TV with Netflix and YouTube.'],
    ['title'=>'MacBook Pro M2', 'price'=>36500, 'img'=>'../public/images/macbook-pro.jpeg', 'desc'=>'Space Gray, 8/256GB. Low battery cycles.'],
    ['title'=>'HP Pavilion Laptop', 'price'=>13800, 'img'=>'../public/images/hp-laptop.jpeg', 'desc'=>'Reliable student laptop, Ryzen 5.'],
];
foreach($elec_data as $e) {
    $cond = (stripos($e['desc'], 'new') !== false) ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$e['title'], 'price'=>$e['price'], 'category'=>'Electronics', 'condition'=>$cond, 'img'=>$e['img'], 'desc'=>$e['desc']];
}

// Furniture (seating, storage, lighting — not appliances or TVs)
$furn_data = [
    ['title'=>'Study Desk', 'price'=>1100, 'img'=>'../public/images/desk.jpeg', 'desc'=>'Modern wooden study desk with built-in storage drawers.'],
    ['title'=>'Bookshelf', 'price'=>650, 'img'=>'../public/images/book-shelf.jpeg', 'desc'=>'4-tier vertical bookshelf. Compact design.'],
    ['title'=>'Comfortable Sofa', 'price'=>3200, 'img'=>'../public/images/sofa.jpeg', 'desc'=>'Comfortable 2-seater sofa for your dorm.'],
    ['title'=>'Clothes Hanger', 'price'=>180, 'img'=>'../public/images/clothes-hanger.jpeg', 'desc'=>'Sturdy metal clothes rack for extra storage.'],
    ['title'=>'Desk Chair', 'price'=>780, 'img'=>'https://upload.wikimedia.org/wikipedia/commons/4/4b/Desk_chair.jpg', 'desc'=>'Ergonomic office chair with adjustable height.'],
    ['title'=>'Floor Lamp', 'price'=>450, 'img'=>'../public/images/floor-lamp.jpeg', 'desc'=>'Modern floor lamp for better room lighting.'],
    ['title'=>'Nightstand', 'price'=>350, 'img'=>'../public/images/night-stand.jpeg', 'desc'=>'Simple bedside table with a drawer.'],
    ['title'=>'Full-length Mirror', 'price'=>550, 'img'=>'../public/images/mirror.jpeg', 'desc'=>'Elegant full-length mirror with black frame.'],
];
foreach($furn_data as $f) {
    $cond = (stripos($f['desc'], 'new') !== false) ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$f['title'], 'price'=>$f['price'], 'category'=>'Furniture', 'condition'=>$cond, 'img'=>$f['img'], 'desc'=>$f['desc']];
}

// Kitchen & Dining (cooking, food prep, small appliances)
$kitchen_data = [
    ['title'=>'Smart Rice Cooker', 'price'=>950, 'img'=>'../public/images/rice-cook.jpeg', 'desc'=>'5L capacity, non-stick pot. Perfect for easy dorm meals.'],
    ['title'=>'Digital Air Fryer', 'price'=>2400, 'img'=>'../public/images/air.jpeg', 'desc'=>'XL capacity. Healthy cooking with little to no oil.'],
    ['title'=>'High-Speed Blender', 'price'=>850, 'img'=>'../public/images/blender.jpeg', 'desc'=>'Powerful motor for smoothies and shakes. Easy to clean.'],
    ['title'=>'Microwave Oven', 'price'=>1950, 'img'=>'../public/images/microwave.jpeg', 'desc'=>'700W Compact Microwave. Perfect for dorm rooms.'],
    ['title'=>'Mini Fridge', 'price'=>2600, 'img'=>'../public/images/minifridge.jpeg', 'desc'=>'Energy-efficient mini fridge with freezer.'],
    ['title'=>'Professional Knife Set', 'price'=>450, 'img'=>'../public/images/knives.jpeg', 'desc'=>'Stainless steel knife set. Very sharp and durable.'],
    ['title'=>'Set of Spoons', 'price'=>120, 'img'=>'../public/images/spoon.jpeg', 'desc'=>'Set of 6 high-quality stainless steel spoons.'],
    ['title'=>'Set of Forks', 'price'=>120, 'img'=>'../public/images/fork.jpeg', 'desc'=>'Set of 6 high-quality stainless steel forks.'],
    ['title'=>'Wooden Cutting Board', 'price'=>185, 'img'=>'../public/images/wood-bord.jpeg', 'desc'=>'Sustainable wood cutting board. Durable and easy on knives.'],
    ['title'=>'Fruit Slicer', 'price'=>95, 'img'=>'../public/images/fruit-slicer.jpeg', 'desc'=>'Quick and even slicing for healthy snacking.'],
    ['title'=>'Vegetable Cutter', 'price'=>140, 'img'=>'../public/images/cutter.jpeg', 'desc'=>'Save time in the kitchen with this versatile tool.'],
    ['title'=>'Mortar & Pestle (Punder)', 'price'=>220, 'img'=>'../public/images/punder.jpeg', 'desc'=>'Classic "punder" for crushing spices and herbs.'],
];
foreach($kitchen_data as $k) {
    $cond = (stripos($k['desc'], 'new') !== false) ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$k['title'], 'price'=>$k['price'], 'category'=>'Kitchen & Dining', 'condition'=>$cond, 'img'=>$k['img'], 'desc'=>$k['desc']];
}

// Clothing — men's and women's as separate categories
$mens_cloth_data = [
    ['title' => "Men's Classic Jeans", 'price' => 580, 'img' => '../public/images/men-jeans.jpeg', 'desc'=>'High-quality denim jeans, straight fit.'],
    ['title' => "Men's Leather Jacket", 'price' => 1950, 'img' => '../public/images/men-leather-jacket.jpeg', 'desc'=>'Stylish faux leather jacket.'],
    ['title' => "Men's Office Attire", 'price' => 1350, 'img' => '../public/images/men-office-wear.jpeg', 'desc'=>'Formal shirt and trousers set.'],
    ['title' => "Men's Casual Sweatshirt", 'price' => 480, 'img' => '../public/images/men-sweat-shirt.jpeg', 'desc'=>'Warm and cozy gray sweatshirt.'],
    ['title' => "Men's Graphic T-Shirt", 'price' => 290, 'img' => '../public/images/t-shirt-men.jpeg', 'desc'=>'Cool graphic tee, 100% cotton.'],
];
foreach ($mens_cloth_data as $c) {
    $cond = (stripos($c['desc'], 'new') !== false) ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$c['title'], 'price'=>$c['price'], 'category'=>"Men's Clothing", 'condition'=>$cond, 'img'=>$c['img'], 'desc'=>$c['desc']];
}

$womens_cloth_data = [
    ['title' => "Women's Black Evening Top", 'price' => 620, 'img' => '../public/images/women-black.jpeg', 'desc'=>'Elegant black top for evenings out.'],
    ['title' => "Women's Casual Trousers", 'price' => 550, 'img' => '../public/images/women-trousers.jpeg', 'desc'=>'Comfortable wide-leg trousers.'],
    ['title' => "Women's White Summer Top", 'price' => 350, 'img' => '../public/images/women-white.jpeg', 'desc'=>'Lightweight white cotton summer top.'],
    ['title' => "Women's Pink Fashion Top", 'price' => 420, 'img' => '../public/images/women-pink.jpeg', 'desc'=>'Cute pink crop top, modern style.'],
    ['title' => "Ladies' Office Cardigan & Skirt Set", 'price' => 380, 'img' => '../public/images/casual-fine-outfit.png', 'desc'=>'Light blue cardigan, striped blouse, and black pencil skirt — clean professional look.'],
];
foreach ($womens_cloth_data as $c) {
    $cond = (stripos($c['desc'], 'new') !== false) ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$c['title'], 'price'=>$c['price'], 'category'=>"Women's Clothing", 'condition'=>$cond, 'img'=>$c['img'], 'desc'=>$c['desc']];
}

// Snacks & Beverages
$snack_data = [
    ['title'=>'Coca-Cola 330ml', 'price'=>35, 'img'=>'../public/images/cocacola.jpeg', 'desc'=>'Cold and refreshing classic Coca-Cola.'],
    ['title'=>'Fanta Orange 330ml', 'price'=>35, 'img'=>'../public/images/fanta.jpeg', 'desc'=>'Fruity and bubbly orange soda.'],
    ['title'=>'Pink Lemonade', 'price'=>120, 'img'=>'../public/images/prime-lemanade.jpeg', 'desc'=>'Refreshing pink lemonade hydration.'],
    ['title'=>'Cappy Apple Juice', 'price'=>45, 'img'=>'../public/images/apple-jiuce.jpeg', 'desc'=>'100% pure apple juice, no added sugar.'],
    ['title'=>'Snickers Chocolate Bar', 'price'=>40, 'img'=>'../public/images/sok.jpeg', 'desc'=>'Satisfy your hunger with a classic Snickers bar.'],
    ['title'=>'Doritos Nacho Cheese', 'price'=>65, 'img'=>'../public/images/doritos.jpeg', 'desc'=>'Classic cheesy crunch for study sessions.'],
    ['title'=>'Popcorn & Chips Pack', 'price'=>55, 'img'=>'../public/images/pop-corn-chips.jpeg', 'desc'=>'The ultimate movie night snack bundle.'],
    ['title'=>'Skittles (Family Size)', 'price'=>85, 'img'=>'../public/images/skittles.jpeg', 'desc'=>'A large bag of chewy, fruity candies.'],
    ['title'=>'Energy Drink (Pack)', 'price'=>180, 'img'=>'../public/images/energy-drink-park.jpeg', 'desc'=>'Keep your energy up with this cold drink pack.'],
    ['title'=>'Granola Bar (Gabdos Bar)', 'price'=>35, 'img'=>'https://upload.wikimedia.org/wikipedia/commons/9/91/Gorp.jpg', 'desc'=>'A healthy and energy-packed snack bar.'],
];
foreach($snack_data as $s) {
    $products[] = ['id'=>$id_counter++, 'title'=>$s['title'], 'price'=>$s['price'], 'category'=>'Snacks & Beverages', 'condition'=>'New', 'img'=>$s['img'], 'desc'=>$s['desc']];
}

// Stationery (paper, pens, school supplies — not health devices)
$stat_data = [
    ['title'=>'A4 Paper Rim (500s)', 'price'=>135, 'img'=>'../public/images/a4-paper.jpeg', 'desc'=>'High-quality white A4 paper for printing and notes.'],
    ['title'=>'Scientific Calculator', 'price'=>450, 'img'=>'../public/images/calcutertor.jpeg', 'desc'=>'Advanced calculator for engineering and math students.'],
    ['title'=>'Colored Pencils Set', 'price'=>120, 'img'=>'../public/images/color-pencil.jpeg', 'desc'=>'24 vibrant colors for art and design projects.'],
    ['title'=>'A5 Spiral Notebook', 'price'=>65, 'img'=>'../public/images/note-book.jpeg', 'desc'=>'Durable cover and high-quality ruled pages.'],
    ['title'=>'Graphite Pencils (6pk)', 'price'=>35, 'img'=>'../public/images/pencil.jpeg', 'desc'=>'Premium HB pencils for sketching and writing.'],
    ['title'=>'Ballpoint Pens (Set)', 'price'=>40, 'img'=>'../public/images/pens.jpeg', 'desc'=>'Smooth-writing blue and black ballpoint pens.'],
    ['title'=>'30cm Steel Ruler', 'price'=>25, 'img'=>'../public/images/ruler.jpeg', 'desc'=>'Precise stainless steel ruler with metric markings.'],
    ['title'=>'Geometry Set Box', 'price'=>180, 'img'=>'../public/images/set-box.jpeg', 'desc'=>'Complete mathematical drawing set in a metal box.'],
    ['title'=>'Eraser & Sharpener Duo', 'price'=>30, 'img'=>'../public/images/sharpaner-and-easerar.jpeg', 'desc'=>'Essential duo for any student pencil case.'],
];
$stat_idx = 0;
foreach($stat_data as $s) {
    $cond = $stat_idx < 5 ? 'New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$s['title'], 'price'=>$s['price'], 'category'=>'Stationery', 'condition'=>$cond, 'img'=>$s['img'], 'desc'=>$s['desc']];
    $stat_idx++;
}

// Bikes & Scooters
$transport_data = [
    ['title' => 'Black Mountain Bike', 'price' => 3200, 'img' => '../public/images/black-bycycle.jpeg', 'desc'=>'All-black mountain bike with 21 speeds.'],
    ['title' => 'Road Speed Bike', 'price' => 3800, 'img' => '../public/images/blue-bike.jpeg', 'desc'=>'Fast road bike with lightweight frame.'],
    ['title' => 'Lightweight City Bike', 'price' => 2400, 'img' => '../public/images/light-bike.jpeg', 'desc'=>'Easy-to-ride city bike with basket.'],
    ['title' => 'Professional Speed Bike', 'price' => 5200, 'img' => '../public/images/speed-bike.jpeg', 'desc'=>'High-performance racing bike.'],
    ['title' => 'Blue Electric Scooter', 'price' => 6900, 'img' => '../public/images/blue-scuytter.jpeg', 'desc'=>'Powerful electric scooter, 25km/h.'],
    ['title' => '2-in-1 Foldable Scooter', 'price' => 1650, 'img' => '../public/images/2-in-1-scotetr.jpeg', 'desc'=>'Foldable and portable design.'],
    ['title' => 'Lightweight Scooter', 'price' => 850, 'img' => '../public/images/light-scoyter.jpeg', 'desc'=>'Simple kick scooter for campus.'],
    ['title' => 'Mini Kids Scooter', 'price' => 550, 'img' => '../public/images/mini-scoter.jpeg', 'desc'=>'Stable three-wheel design.'],
    ['title' => 'Classic Kick Scooter', 'price' => 1100, 'img' => '../public/images/scotter.jpeg', 'desc'=>'Traditional metal kick scooter.'],
    ['title' => 'Sport Pro Scooter', 'price' => 1400, 'img' => '../public/images/scutt.jpeg', 'desc'=>'Professional-grade stunt scooter.'],
];
foreach($transport_data as $t) {
    $products[] = ['id'=>$id_counter++, 'title'=>$t['title'], 'price'=>$t['price'], 'category'=>'Bikes & Scooters', 'condition'=>'Used', 'img'=>$t['img'], 'desc'=>$t['desc']];
}

// iPhones & Tablets (phones and tablets only — laptops live under Electronics)
$device_data = [
    ['title'=>'iPhone 17 Pro Max', 'price'=>48500, 'img'=>'../public/images/iphione-17-pro-maxx.jpeg', 'desc'=>'Titanium Blue, 256GB. 99% Battery.'],
    ['title'=>'iPhone SE (2022)', 'price'=>9800, 'img'=>'../public/images/iphone-se.jpeg', 'desc'=>'Powerful A15 chip in compact size.'],
    ['title'=>'Samsung Galaxy S24 Ultra', 'price'=>39500, 'img'=>'../public/images/samsung-galaxy-s45-ultra.jpeg', 'desc'=>'Titanium Black, 512GB. S-Pen included.'],
    ['title'=>'Huawei P60 Pro', 'price'=>16500, 'img'=>'../public/images/huwaii.jpeg', 'desc'=>'Amazing photography capabilities.'],
    ['title'=>'iPad Pro (White)', 'price'=>22500, 'img'=>'../public/images/i-pad-white.jpeg', 'desc'=>'M2 chip, 11-inch. Perfect for art.'],
    ['title'=>'Samsung Galaxy Tab S10', 'price'=>12800, 'img'=>'../public/images/samsung-tablet.jpeg', 'desc'=>'Stunning AMOLED screen.'],
    ['title'=>'iPad Air (Space Gray)', 'price'=>14200, 'img'=>'../public/images/i-pad-white.jpeg', 'desc'=>'Slim and powerful iPad Air.'],
    ['title'=>'Amazon Fire Tablet (BOGO)', 'price'=>3900, 'img'=>'../public/images/buy-one-get-one-free.jpeg', 'desc'=>'Two tablets for one great price.'],
];
$dev_idx = 0;
foreach($device_data as $d) {
    $cond = $dev_idx < 4 ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$d['title'], 'price'=>$d['price'], 'category'=>'iPhones & Tablets', 'condition'=>$cond, 'img'=>$d['img'], 'desc'=>$d['desc']];
    $dev_idx++;
}

// Personal Care & Hygiene
$care_data = [
    ['title'=>'Shampoo & Conditioner', 'price'=>210, 'img'=>'../public/images/sampo.jpeg', 'desc'=>'Premium hair care set.'],
    ['title'=>'Body Wash', 'price'=>160, 'img'=>'../public/images/body-wash.jpeg', 'desc'=>'Refreshing citrus body wash.'],
    ['title'=>'Toothpaste Pack', 'price'=>115, 'img'=>'../public/images/thoppaste-pack.jpeg', 'desc'=>'Value pack of 3 whitening toothpastes.'],
    ['title'=>'Deodorant', 'price'=>85, 'img'=>'../public/images/deodarant.jpeg', 'desc'=>'48-hour protection. Stay fresh.'],
    ['title'=>'Skincare Set', 'price'=>680, 'img'=>'../public/images/skin-care-prodyct.jpeg', 'desc'=>'Complete 3-step skincare routine.'],
    ['title'=>'Hand Sanitizer', 'price'=>55, 'img'=>'../public/images/hand-saditier.jpeg', 'desc'=>'Pocket-sized germ killer.'],
    ['title'=>'Laundry Detergent', 'price'=>290, 'img'=>'../public/images/laundry-detergent.jpeg', 'desc'=>'Large 5kg pack, effective on stains.'],
    ['title'=>'Toilet Paper (12 pk)', 'price'=>175, 'img'=>'../public/images/toilrt-paper.jpeg', 'desc'=>'Soft and strong 3-ply.'],
    ['title'=>'Shaving Kit', 'price'=>380, 'img'=>'../public/images/shaving-kit.jpeg', 'desc'=>'High-precision razor and foam.'],
    ['title'=>'First Aid Kit', 'price'=>260, 'img'=>'../public/images/fisst-aid-kit.jpeg', 'desc'=>'Comprehensive emergency kit.'],
    ['title'=>'Digital Thermometer', 'price'=>150, 'img'=>'../public/images/termometer.jpeg', 'desc'=>'Fast and accurate digital reading for health tracking.'],
];
foreach($care_data as $c) {
    $cond = (stripos($c['desc'], 'new') !== false) ? 'Like New' : 'Used';
    $products[] = ['id'=>$id_counter++, 'title'=>$c['title'], 'price'=>$c['price'], 'category'=>'Personal Care & Hygiene', 'condition'=>$cond, 'img'=>$c['img'], 'desc'=>$c['desc']];
}

// Include user-created listings stored in session (if any).
if (isset($_SESSION['custom_products']) && is_array($_SESSION['custom_products'])) {
    foreach ($_SESSION['custom_products'] as $custom_product) {
        if (
            is_array($custom_product) &&
            isset($custom_product['id'], $custom_product['title'], $custom_product['price'], $custom_product['category'], $custom_product['condition'], $custom_product['img'], $custom_product['desc'])
        ) {
            array_unshift($products, $custom_product);
        }
    }
}
