<?php
// api/service_templates.php
// Returns active service templates grouped by category for the create listing catalog modal.

require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$fallbackTemplates = [
    [
        'category_id' => 11,
        'category_name' => 'Tutoring & Academic Help',
        'templates' => [
            [
                'id' => 1,
                'category_id' => 11,
                'name' => 'Math & Science Tutoring',
                'icon' => '📐',
                'title_template' => 'Math & Science Tutoring — All Levels',
                'description_template' => 'I offer personalised 1-on-1 tutoring sessions in mathematics, statistics, and science topics. Flexible scheduling on campus or online.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 40,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['study-guides', 'books']
            ],
            [
                'id' => 2,
                'category_id' => 11,
                'name' => 'Language Lessons',
                'icon' => '🗣️',
                'title_template' => 'Conversational & Academic Language Tutoring',
                'description_template' => 'Native/fluent speaker offering foreign language practice, grammar support, and exam preparation for students.',
                'suggested_price_min' => 12,
                'suggested_price_max' => 35,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['study-guides']
            ],
            [
                'id' => 3,
                'category_id' => 11,
                'name' => 'Coding & CS Coaching',
                'icon' => '💻',
                'title_template' => 'Programming & Computer Science Help',
                'description_template' => 'Practical assistance with coding assignments, algorithms, web development, and debugging in Python, Java, C++, or JS.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 50,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['tech', 'study-guides']
            ],
            [
                'id' => 4,
                'category_id' => 11,
                'name' => 'Essay & Writing Review',
                'icon' => '✍️',
                'title_template' => 'Essay Proofreading & Academic Writing Feedback',
                'description_template' => 'Detailed review of academic essays, lab reports, and presentations for structure, grammar, clarity, and citations.',
                'suggested_price_min' => 25,
                'suggested_price_max' => 80,
                'pricing_model' => 'flat',
                'suggested_tags' => ['study-guides']
            ]
        ]
    ],
    [
        'category_id' => 14,
        'category_name' => 'Photography & Media',
        'templates' => [
            [
                'id' => 5,
                'category_id' => 14,
                'name' => 'Event Photography',
                'icon' => '📸',
                'title_template' => 'Event Photography & Coverage',
                'description_template' => 'High-quality photography for campus events, student club activities, parties, or sports. Edited digital photos included.',
                'suggested_price_min' => 80,
                'suggested_price_max' => 300,
                'pricing_model' => 'flat',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 6,
                'category_id' => 14,
                'name' => 'Student Portrait & Headshots',
                'icon' => '🤳',
                'title_template' => 'Student Portrait & Professional Headshot Session',
                'description_template' => 'Quick 30-minute campus photo session for LinkedIn, CVs, graduation, and profile pictures. 5 edited retouched photos.',
                'suggested_price_min' => 30,
                'suggested_price_max' => 80,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 7,
                'category_id' => 14,
                'name' => 'Marketplace Product Photos',
                'icon' => '📦',
                'title_template' => 'Product Photography for Campus Sellers',
                'description_template' => 'Clean, well-lit photos for your marketplace listings, notes, and crafts to help you sell faster.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 60,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 8,
                'category_id' => 14,
                'name' => 'Video Editing & Content',
                'icon' => '🎬',
                'title_template' => 'Video Editing & Content Creation',
                'description_template' => 'Fast turnaround video editing for YouTube, TikTok, presentations, and course projects.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 45,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['tech']
            ]
        ]
    ],
    [
        'category_id' => 12,
        'category_name' => 'Cleaning Services',
        'templates' => [
            [
                'id' => 9,
                'category_id' => 12,
                'name' => 'Dorm Room Deep Clean',
                'icon' => '🧹',
                'title_template' => 'Dorm Room & Studio Cleaning',
                'description_template' => 'Full dorm or apartment clean including dusting, vacuuming, mopping, bathroom sanitation, and trash removal.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 50,
                'pricing_model' => 'flat',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 10,
                'category_id' => 12,
                'name' => 'Shared Kitchen Cleaning',
                'icon' => '🍳',
                'title_template' => 'Shared Kitchen & Appliances Clean',
                'description_template' => 'Thorough cleaning of shared student kitchens, stoves, microwave, countertops, and sinks.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 40,
                'pricing_model' => 'flat',
                'suggested_tags' => ['kitchen']
            ],
            [
                'id' => 11,
                'category_id' => 12,
                'name' => 'Move-Out Deep Clean',
                'icon' => '🏠',
                'title_template' => 'End of Tenancy / Move-Out Deep Clean',
                'description_template' => 'Complete room cleaning tailored to deposit-return standards for campus dorms and student apartments.',
                'suggested_price_min' => 60,
                'suggested_price_max' => 150,
                'pricing_model' => 'flat',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 12,
                'category_id' => 12,
                'name' => 'Laundry & Ironing Service',
                'icon' => '👕',
                'title_template' => 'Laundry Wash, Dry & Fold Service',
                'description_template' => 'Convenient pickup and delivery laundry service on campus. Price per wash load.',
                'suggested_price_min' => 10,
                'suggested_price_max' => 25,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ]
        ]
    ],
    [
        'category_id' => 13,
        'category_name' => 'Moving & Packing',
        'templates' => [
            [
                'id' => 13,
                'category_id' => 13,
                'name' => 'Campus Moving Help',
                'icon' => '📦',
                'title_template' => 'Campus Moving & Heavy Lifting Help',
                'description_template' => 'Friendly extra hands to help carry boxes, luggage, and belongings into or out of your campus accommodation.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 30,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 14,
                'category_id' => 13,
                'name' => 'Furniture Assembly',
                'icon' => '🔧',
                'title_template' => 'Flatpack & Desk Furniture Assembly',
                'description_template' => 'Fast and correct assembly for desks, beds, shelves, and chairs. Bring own screwdrivers and tools.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 45,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 15,
                'category_id' => 13,
                'name' => 'Packing & Box Organisation',
                'icon' => '📦',
                'title_template' => 'Luggage & Packing Assistance',
                'description_template' => 'Help organizing, bubble-wrapping, and packing your room belongings for summer storage or relocation.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 25,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 16,
                'category_id' => 13,
                'name' => 'Campus Delivery & Courier',
                'icon' => '🚲',
                'title_template' => 'On-Campus Small Item Delivery by Foot/Bike',
                'description_template' => 'Fast same-day delivery of documents, books, or small packages between campuses and dorm buildings.',
                'suggested_price_min' => 5,
                'suggested_price_max' => 20,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ]
        ]
    ]
];

try {
    $stmt = $pdo->query("
        SELECT st.*, c.name AS category_name
        FROM service_templates st
        JOIN categories c ON c.id = st.category_id
        WHERE st.is_active = TRUE
        ORDER BY c.name ASC, st.sort_order ASC
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo json_encode(['success' => true, 'categories' => $fallbackTemplates]);
        exit;
    }

    $grouped = [];
    foreach ($rows as $row) {
        $cid = (int)$row['category_id'];
        if (!isset($grouped[$cid])) {
            $grouped[$cid] = [
                'category_id' => $cid,
                'category_name' => $row['category_name'],
                'templates' => []
            ];
        }
        
        $tags = $row['suggested_tags'];
        if (is_string($tags)) {
            // Parse PostgreSQL array literal {tag1,tag2} or JSON
            $tags = trim($tags, '{}');
            $tags = $tags !== '' ? array_map('trim', explode(',', $tags)) : [];
        }

        $grouped[$cid]['templates'][] = [
            'id' => (int)$row['id'],
            'category_id' => $cid,
            'name' => $row['name'],
            'icon' => $row['icon'],
            'title_template' => $row['title_template'],
            'description_template' => $row['description_template'],
            'suggested_price_min' => (float)$row['suggested_price_min'],
            'suggested_price_max' => (float)$row['suggested_price_max'],
            'pricing_model' => $row['pricing_model'],
            'suggested_tags' => is_array($tags) ? $tags : []
        ];
    }

    echo json_encode(['success' => true, 'categories' => array_values($grouped)]);
} catch (Throwable $e) {
    // If table doesn't exist yet, return full fallback catalog seamlessly
    echo json_encode(['success' => true, 'categories' => $fallbackTemplates]);
}
