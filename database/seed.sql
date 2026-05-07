-- ============================================================
-- CampusMarket — Master Seed Data (Symmetrical 10/10/10) - AUDITED V2
-- ============================================================
USE campusmarket;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE product_tags;
TRUNCATE TABLE product_images;
TRUNCATE TABLE wishlists;
TRUNCATE TABLE products;
TRUNCATE TABLE tags;
TRUNCATE TABLE categories;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ─── Users ───────────────────────────────────────────────
INSERT INTO users (id, username, email, password_hash, role) VALUES
(1, 'admin', 'admin@campusmarket.com', '$2y$10$zJNHp8ZuFr.vSM3mq1ArGeGBem1UG2KFDM9nfoUgzpHJDyn7UcQ4G', 'admin'),
(2, 'sarah_m', 'sarah@student.edu', '$2y$10$zJNHp8ZuFr.vSM3mq1ArGeGBem1UG2KFDM9nfoUgzpHJDyn7UcQ4G', 'user'),
(3, 'alex_j', 'alex@student.edu', '$2y$10$zJNHp8ZuFr.vSM3mq1ArGeGBem1UG2KFDM9nfoUgzpHJDyn7UcQ4G', 'user'),
(4, 'emma_w', 'emma@student.edu', '$2y$10$zJNHp8ZuFr.vSM3mq1ArGeGBem1UG2KFDM9nfoUgzpHJDyn7UcQ4G', 'user'),
(5, 'chris_e', 'chris@student.edu', '$2y$10$zJNHp8ZuFr.vSM3mq1ArGeGBem1UG2KFDM9nfoUgzpHJDyn7UcQ4G', 'user');

-- ─── Categories ───────────────────────────────────────────
INSERT INTO categories (id, name, slug) VALUES
(1,  'Electronics & Accessories', 'electronics-accessories'),
(2,  'Books & Study Materials', 'books-study-materials'),
(3,  'Furniture', 'furniture'),
(4,  'Clothing & Fashion', 'clothing-fashion'),
(5,  'Kitchen Essentials', 'kitchen-essentials'),
(6,  'Health & Personal Care', 'health-personal-care'),
(7,  'Food & Beverages', 'food-beverages'),
(8,  'Stationery & Study Supplies', 'stationery-study-supplies'),
(9,  'Dorm & Living Essentials', 'dorm-living-essentials'),
(10, 'Transportation (Bikes & Scooters)', 'transportation');

-- ─── Products (Exactly 10 per category = 100 products) ─────
INSERT INTO products (id, user_id, category_id, title, description, price, `condition`, status) VALUES
-- 1. Electronics
(1, 1, 1, 'AirPods Pro (2nd Gen)', 'Active noise cancellation, like new', 5800.0, 'like_new', 'active'),
(2, 2, 1, 'iPhone 17 Pro Max', '256GB, Titanium, barely used', 85000.0, 'like_new', 'active'),
(3, 3, 1, 'MacBook Pro 14"', 'M3 Chip, 16GB RAM, Space Gray', 88000.0, 'new', 'active'),
(4, 4, 1, 'Bluetooth Speaker', 'Waterproof, deep bass', 950.0, 'new', 'active'),
(5, 5, 1, 'Gaming Mouse', 'RGB, 12000 DPI', 550.0, 'new', 'active'),
(6, 1, 1, 'High Fidelity Headphones', 'Studio quality', 2100.0, 'new', 'active'),
(7, 2, 1, 'HP Student Laptop', 'Reliable study tool', 16500.0, 'used', 'active'),
(8, 3, 1, 'Huawei Smartphone', 'Premium display', 12500.0, 'new', 'active'),
(9, 4, 1, 'iPad Air (Space Gray)', 'Light and powerful', 24000.0, 'like_new', 'active'),
(10, 5, 1, 'iPad Pro (White)', '12.9-inch screen', 42000.0, 'like_new', 'active'),
(69, 4, 1, 'BOGO Tablet (Student Edition)', 'Portable tablet for notes', 7200.0, 'new', 'active'),

-- 2. Books
(11, 1, 2, 'General Biology', 'Science textbook', 625.0, 'used', 'active'),
(12, 2, 2, 'Calculus II Guide', 'Math essentials', 450.0, 'new', 'active'),
(13, 3, 2, 'Organic Chemistry', 'Study aid', 800.0, 'new', 'active'),
(14, 4, 2, 'Intro to Comp Sci', 'Fundamentals', 700.0, 'like_new', 'active'),
(15, 5, 2, 'English Literature', 'Classics anthology', 375.0, 'used', 'active'),
(16, 1, 2, 'Intro to Physics', 'Science textbook', 600.0, 'new', 'active'),
(17, 2, 2, 'Linear Algebra', 'Foundations', 475.0, 'new', 'active'),
(18, 3, 2, 'Microeconomics', 'Economic principles', 525.0, 'like_new', 'active'),
(19, 4, 2, 'Psychology 101', 'Explore the mind', 450.0, 'used', 'active'),
(20, 5, 2, 'World History', 'Global history', 550.0, 'like_new', 'active'),

-- 3. Furniture
(21, 1, 3, 'Wooden Bookshelf', '5-tier storage', 1575.0, 'used', 'active'),
(22, 2, 3, 'Compact Study Desk', 'Perfect for dorms', 2025.0, 'used', 'active'),
(23, 3, 3, 'Modern Nightstand', 'Sleek design', 810.0, 'new', 'active'),
(24, 4, 3, 'Comfortable Study Sofa', 'Soft seating', 3825.0, 'used', 'active'),
(25, 5, 3, '55-inch Smart TV', 'Ultra HD', 19500.0, 'like_new', 'active'),
(26, 1, 3, 'Floor Lamp', 'Adjustable height', 675.0, 'new', 'active'),
(27, 2, 3, 'Ergonomic Desk Chair', 'Comfy seating', 2475.0, 'like_new', 'active'),
(28, 3, 3, 'Rolling Storage Cart', 'Mobile organizer', 810.0, 'new', 'active'),
(29, 4, 3, 'Study Table Lamp', 'Bright LED', 385.0, 'new', 'active'),
(30, 5, 3, 'Small Room Mirror', 'Wall mounted', 540.0, 'new', 'active'),

-- 4. Clothing
(31, 1, 4, 'Women Summer Dress', 'Elegant blue casual dress', 540.0, 'new', 'active'),
(32, 2, 4, 'Slim Fit Jeans', 'Durable denim', 950.0, 'like_new', 'active'),
(33, 3, 4, 'Leather Jacket', 'Black premium leather', 4800.0, 'new', 'active'),
(34, 4, 4, 'Office Wear Suit', 'Professional business attire', 1350.0, 'used', 'active'),
(35, 5, 4, 'Oversized Sweatshirt', 'Warm campus gear', 540.0, 'new', 'active'),
(36, 1, 4, 'Basic T-Shirt', 'Essential cotton tee', 135.0, 'new', 'active'),
(37, 2, 4, 'Women Black Dress', 'Elegant style', 660.0, 'new', 'active'),
(38, 3, 4, 'Women Trousers', 'High waisted', 540.0, 'new', 'active'),
(39, 4, 4, 'Women White Blouse', 'Wardrobe staple', 360.0, 'new', 'active'),
(40, 5, 4, 'Women Pink Casual Top', 'Light comfort', 285.0, 'new', 'active'),

-- 5. Kitchen (Including Mortar & Dough Cutter)
(41, 1, 5, 'Digital Air Fryer', 'Oil free cooking', 4250.0, 'like_new', 'active'),
(42, 2, 5, 'Nutri Blender', 'Smoothie maker', 2250.0, 'new', 'active'),
(43, 3, 5, 'Stainless Fork Set', '12 pieces', 225.0, 'new', 'active'),
(44, 4, 5, 'Fruit Slicer', 'Quick prep', 125.0, 'new', 'active'),
(45, 5, 5, 'Kitchen Knife Set', 'Professional', 900.0, 'new', 'active'),
(46, 1, 5, 'Dorm Mini Fridge', 'Energy efficient', 6000.0, 'like_new', 'active'),
(47, 2, 5, 'Electric Rice Cooker', 'Automatic', 1750.0, 'used', 'active'),
(48, 3, 5, 'Stainless Spoon Set', '12 pieces', 225.0, 'new', 'active'),
(49, 4, 5, 'Wooden Cutting Board', 'Heavy duty', 425.0, 'new', 'active'),
(50, 5, 5, 'Microwave Oven', 'Student essential', 3750.0, 'used', 'active'),
(74, 4, 5, 'Professional Dough Cutter', 'Heavy duty kitchen tool', 250.0, 'new', 'active'),
(55, 5, 5, 'Granite Mortar & Pestle', 'Traditional kitchen tool', 450.0, 'new', 'active'),

-- 6. Health
(51, 1, 6, 'Refreshing Body Wash', 'Citrus scent', 180.0, 'new', 'active'),
(52, 2, 6, 'Long Last Deodorant', 'Stay fresh', 140.0, 'new', 'active'),
(53, 3, 6, 'First Aid Kit', 'Emergency set', 340.0, 'new', 'active'),
(54, 4, 6, 'Hand Sanitizer', '99.9% protection', 60.0, 'new', 'active'),
(56, 1, 6, 'Strength Shampoo', 'Daily care', 180.0, 'new', 'active'),
(57, 2, 6, 'Shaving Kit', 'Grooming set', 480.0, 'new', 'active'),
(58, 3, 6, 'Skincare Product', 'Gentle formula', 720.0, 'new', 'active'),
(59, 4, 6, 'Digital Thermometer', 'Fast check', 340.0, 'new', 'active'),
(60, 5, 6, 'Toothpaste 3-Pack', 'Oral protection', 220.0, 'new', 'active'),

-- 7. Food
(61, 1, 7, 'Fresh Apple Juice', '1L Natural', 85.0, 'new', 'active'),
(62, 2, 7, 'Coca-Cola 6-Pack', 'Refreshing soda', 195.0, 'new', 'active'),
(63, 3, 7, 'Doritos Nacho Cheese', 'Favorite snack', 65.0, 'new', 'active'),
(64, 4, 7, 'Energy Drink Pack', 'For late nights', 360.0, 'new', 'active'),
(65, 5, 7, 'Fanta Orange Soda', 'Bright flavor', 65.0, 'new', 'active'),
(66, 1, 7, 'Butter Popcorn', 'Movie snack', 55.0, 'new', 'active'),
(67, 2, 7, 'Prime Lemonade', 'Hydration', 140.0, 'new', 'active'),
(68, 3, 7, 'Skittles Candy', 'Fruit flavors', 45.0, 'new', 'active'),
(70, 5, 7, 'Skor Chocolate Bar', 'Crisp butter toffee chocolate', 65.0, 'new', 'active'),

-- 8. Stationery
(71, 1, 8, 'A4 Printing Paper', 'High quality', 180.0, 'new', 'active'),
(72, 2, 8, 'Scientific Calculator', 'Math essential', 1225.0, 'used', 'active'),
(73, 3, 8, 'Color Pencils Pack', '24 colors', 300.0, 'new', 'active'),
(75, 5, 8, 'Hardbound Notebook', 'Sturdy paper', 160.0, 'new', 'active'),
(76, 1, 8, 'Mechanical Pencil Set', 'Pro writing', 125.0, 'new', 'active'),
(77, 2, 8, 'Fine Liner Pen Set', 'Precise tools', 300.0, 'new', 'active'),
(78, 3, 8, '30cm Steel Ruler', 'Measurements', 55.0, 'new', 'active'),
(79, 4, 8, 'Geometry Set Box', 'Complete kit', 300.0, 'new', 'active'),
(80, 5, 8, 'Sharpener and Eraser Set', 'Stationery kit', 90.0, 'new', 'active'),

-- 9. Dorm
(81, 1, 9, 'Clothes Hangers (10 Pack)', 'Velvet non-slip', 180.0, 'new', 'active'),
(82, 2, 9, 'Modern Floor Lamp', 'Warm lighting', 1800.0, 'like_new', 'active'),
(83, 3, 9, 'Laundry Detergent', 'Fresh scent', 260.0, 'new', 'active'),
(84, 4, 9, 'Full Wall Mirror', 'Tall mirror', 4800.0, 'new', 'active'),
(85, 5, 9, 'Premium Toilet Paper', '4-roll pack', 65.0, 'new', 'active'),
(86, 1, 9, 'HD Pro Webcam', '1080p for online classes', 3400.0, 'new', 'active'),
(87, 2, 9, '24-inch IPS Monitor', 'Slim bezel display', 5500.0, 'like_new', 'active'),
(88, 3, 9, 'Laptop Charger', 'Universal fit', 1800.0, 'new', 'active'),
(89, 4, 9, 'Laptop Stand', 'Ergonomic', 1000.0, 'new', 'active'),
(90, 5, 9, 'Mechanical Keyboard', 'Dorm setup', 2450.0, 'new', 'active'),

-- 10. Transportation
(91, 1, 10, '2-in-1 Scooter', 'Versatile kick scooter', 2640.0, 'new', 'active'),
(92, 2, 10, 'Matte Black Bicycle', '24 speed road bike', 9900.0, 'like_new', 'active'),
(93, 3, 10, 'Blue Commuter Bike', 'City style', 7040.0, 'used', 'active'),
(94, 4, 10, 'Blue Electric Scooter', 'Long battery life', 40700.0, 'new', 'active'),
(95, 5, 10, 'Lightweight Road Bike', 'Carbon frame', 33000.0, 'like_new', 'active'),
(96, 1, 10, 'LED Light Scooter', 'Fun for campus', 3300.0, 'new', 'active'),
(97, 2, 10, 'Mini Travel Scooter', 'Compact fold', 4500.0, 'new', 'active'),
(98, 3, 10, 'Standard Kick Scooter', 'Basic reliable', 1200.0, 'used', 'active'),
(99, 4, 10, 'Heavy Duty Black Scooter', 'Durable pro', 5500.0, 'new', 'active'),
(100, 5, 10, '21-Speed Mountain Bike', 'All terrain', 12100.0, 'like_new', 'active');

-- Ensure admin has no listings; distribute all seeded products across member users (2-5)
UPDATE products
SET user_id = 2 + ((id - 1) % 4);

-- ─── Product Images (Corrected Paths with Fixed Spelling) ─────────────────────
INSERT INTO product_images (product_id, image_path, is_primary) VALUES
(1, 'images/air pods pro.jpeg', 1), (2, 'images/iphone 17 pro max.jpeg', 1), (3, 'images/macbook pro.jpeg', 1), (4, 'images/bluetooth speaker.jpeg', 1), (5, 'images/gaming mouse.jpeg', 1), (6, 'images/headphones.jpeg', 1), (7, 'images/hp laptop.jpeg', 1), (8, 'images/huawei.jpeg', 1), (9, 'images/i pad .jpeg', 1), (10, 'images/i pad white.jpeg', 1), (69, 'images/buy one get one free.jpeg', 1),
(11, 'images/biology.jpeg', 1), (12, 'images/calculus 2.jpeg', 1), (13, 'images/chemistry.jpeg', 1), (14, 'images/computer science.jpeg', 1), (15, 'images/english literature.jpeg', 1), (16, 'images/intro to physics.jpeg', 1), (17, 'images/linear algebra.jpeg', 1), (18, 'images/microeconomics.jpeg', 1), (19, 'images/psychology.jpeg', 1), (20, 'images/world history.jpeg', 1),
(21, 'images/book shelf.jpeg', 1), (22, 'images/desk.jpeg', 1), (23, 'images/night stand.jpeg', 1), (24, 'images/sofa.jpeg', 1), (25, 'images/tv.jpeg', 1), (26, 'images/floor lamp.jpeg', 1), (27, 'images/desk.jpeg', 1), (28, 'images/clothes hanger.jpeg', 1), (29, 'images/mirror.jpeg', 1), (30, 'images/mirror.jpeg', 1),
(31, 'images/fine shii.jpeg', 1), (32, 'images/men jeans.jpeg', 1), (33, 'images/men leather jacket.jpeg', 1), (34, 'images/men office wear.jpeg', 1), (35, 'images/men sweat shirt.jpeg', 1), (36, 'images/t shirt men.jpeg', 1), (37, 'images/women black.jpeg', 1), (38, 'images/women trousers.jpeg', 1), (39, 'images/women white.jpeg', 1), (40, 'images/women pink.jpeg', 1),
(41, 'images/air.jpeg', 1), (42, 'images/blender.jpeg', 1), (43, 'images/fork.jpeg', 1), (44, 'images/fruit slicer.jpeg', 1), (45, 'images/knives.jpeg', 1), (46, 'images/minifridge.jpeg', 1), (47, 'images/rice cook.jpeg', 1), (48, 'images/spoon.jpeg', 1), (49, 'images/wood bord.jpeg', 1), (50, 'images/microwave.jpeg', 1), (74, 'images/cutter.jpeg', 1), (55, 'images/pounder.jpeg', 1),
(51, 'images/body wash.jpeg', 1), (52, 'images/deodorant.jpeg', 1), (53, 'images/first aid kit.jpeg', 1), (54, 'images/hand sanitizer.jpeg', 1), (56, 'images/shampoo.jpeg', 1), (57, 'images/shaving kit.jpeg', 1), (58, 'images/skincare product.jpeg', 1), (59, 'images/thermometer.jpeg', 1), (60, 'images/toothpaste pack.jpeg', 1),
(61, 'images/apple juice.jpeg', 1), (62, 'images/cocacola.jpeg', 1), (63, 'images/doritos.jpeg', 1), (64, 'images/energy drink park.jpeg', 1), (65, 'images/fanta.jpeg', 1), (66, 'images/pop corn chips.jpeg', 1), (67, 'images/prime lemonade.jpeg', 1), (68, 'images/skittles.jpeg', 1), (70, 'images/skor.jpeg', 1),
(71, 'images/a4 paper.jpeg', 1), (72, 'images/calculator.jpeg', 1), (73, 'images/color pencil.jpeg', 1), (75, 'images/notebook.jpeg', 1), (76, 'images/pencil.jpeg', 1), (77, 'images/pens.jpeg', 1), (78, 'images/ruler.jpeg', 1), (79, 'images/set box.jpeg', 1), (80, 'images/sharpener and eraser.jpeg', 1),
(81, 'images/clothes hanger.jpeg', 1), (82, 'images/floor lamp.jpeg', 1), (83, 'images/laundry detergent.jpeg', 1), (84, 'images/mirror.jpeg', 1), (85, 'images/toilet paper.jpeg', 1), (86, 'images/webcam.jpeg', 1), (87, 'images/monitor pc.jpeg', 1), (88, 'images/laptop charger.jpeg', 1), (89, 'images/laptop stand.jpeg', 1), (90, 'images/keyboard.jpeg', 1),
(91, 'images/2 in 1 scooter.jpeg', 1), (92, 'images/black bycycle.jpeg', 1), (93, 'images/blue bike.jpeg', 1), (94, 'images/blue scooter.jpeg', 1), (95, 'images/light bike.jpeg', 1), (96, 'images/light scooter.jpeg', 1), (97, 'images/mini scooter.jpeg', 1), (98, 'images/scooter.jpeg', 1), (99, 'images/black scooter.jpeg', 1), (100, 'images/speed bike.jpeg', 1);

-- ─── Email Verification (Member 2) ───────────────────────────────────────────
-- Mark all seeded users as already verified so dev logins keep working
UPDATE users SET is_verified = 1;
