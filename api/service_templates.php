<?php
// api/service_templates.php
// Returns active generalized service templates grouped by category (no icons).

require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$fallbackTemplates = [
    [
        'category_id' => 15,
        'category_name' => 'Web, Software & Tech Support',
        'templates' => [
            [
                'id' => 1,
                'category_id' => 15,
                'name' => 'Custom Website Development',
                'title_template' => 'Custom Website Development — Responsive & Modern',
                'description_template' => 'Full-stack website development tailored to your requirements. Tech stack includes React, Next.js, WordPress, Shopify, or clean semantic HTML/CSS with fast load speeds and mobile responsiveness.',
                'suggested_price_min' => 150,
                'suggested_price_max' => 600,
                'pricing_model' => 'flat',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 2,
                'category_id' => 15,
                'name' => 'Mobile App Development',
                'title_template' => 'Cross-Platform Mobile App Development',
                'description_template' => 'Custom mobile app development for iOS and Android using React Native or Flutter. Clean architecture, seamless API integration, and modern UI design.',
                'suggested_price_min' => 250,
                'suggested_price_max' => 900,
                'pricing_model' => 'flat',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 3,
                'category_id' => 15,
                'name' => 'Python Scripting & Automation',
                'title_template' => 'Python Automation, Web Scraping & Scripting',
                'description_template' => 'Custom Python scripts to automate repetitive tasks, scrape web data, process spreadsheets, and build custom bots or API connectors.',
                'suggested_price_min' => 25,
                'suggested_price_max' => 60,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 4,
                'category_id' => 15,
                'name' => 'UI/UX Design & Figma Prototyping',
                'title_template' => 'UI/UX Interface Design & Interactive Figma Prototypes',
                'description_template' => 'Modern, user-centered UI/UX design for web applications and mobile apps. Includes wireframing, component design systems, and clickable Figma prototypes.',
                'suggested_price_min' => 100,
                'suggested_price_max' => 400,
                'pricing_model' => 'flat',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 5,
                'category_id' => 15,
                'name' => 'Database Design & SQL Optimization',
                'title_template' => 'Database Schema Design & SQL Query Optimization',
                'description_template' => 'Relational database architecture, performance tuning, indexing, and complex SQL query optimization in PostgreSQL and MySQL.',
                'suggested_price_min' => 25,
                'suggested_price_max' => 65,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 6,
                'category_id' => 15,
                'name' => 'IT & Computer Troubleshooting',
                'title_template' => 'Computer Setup, OS Formatting & IT Troubleshooting',
                'description_template' => 'Hands-on IT technical support including clean OS reinstallation, virus/malware removal, driver configuration, and hardware performance diagnostics.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 50,
                'pricing_model' => 'flat',
                'suggested_tags' => ['tech']
            ]
        ]
    ],
    [
        'category_id' => 14,
        'category_name' => 'Design, Photography & Media',
        'templates' => [
            [
                'id' => 7,
                'category_id' => 14,
                'name' => 'Brand Identity & Logo Design',
                'title_template' => 'Professional Logo Design & Brand Identity Package',
                'description_template' => 'High-impact vector logo design, brand typography rules, color palettes, and brand guidelines delivered in SVG, PNG, and PDF formats.',
                'suggested_price_min' => 40,
                'suggested_price_max' => 150,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 8,
                'category_id' => 14,
                'name' => 'Social Media & Ad Creatives',
                'title_template' => 'Social Media Graphics & High-Converting Ad Banners',
                'description_template' => 'Engaging custom graphics for Instagram, TikTok, LinkedIn, and Facebook ad campaigns. Tailored to drive clicks and brand recognition.',
                'suggested_price_min' => 25,
                'suggested_price_max' => 80,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 9,
                'category_id' => 14,
                'name' => 'Video Editing & Post-Production',
                'title_template' => 'Professional Video Editing & Post-Production',
                'description_template' => 'High-quality video editing for YouTube, Reels, TikTok, podcasts, and commercial presentations with seamless transitions, color grading, and subtitles.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 50,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 10,
                'category_id' => 14,
                'name' => '3D Modeling & Product Rendering',
                'title_template' => 'Photorealistic 3D Modeling & Product Rendering',
                'description_template' => 'High-detail 3D assets and product mockups modeled in Blender or SolidWorks for marketing, e-commerce, and technical visualization.',
                'suggested_price_min' => 50,
                'suggested_price_max' => 200,
                'pricing_model' => 'flat',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 11,
                'category_id' => 14,
                'name' => 'Audio Editing & Music Production',
                'title_template' => 'Podcast Audio Cleanup, Voiceover & Music Mixing',
                'description_template' => 'Professional sound editing including background noise removal, EQ balancing, compression, audio mastering, and voiceover post-production.',
                'suggested_price_min' => 30,
                'suggested_price_max' => 100,
                'pricing_model' => 'flat',
                'suggested_tags' => ['tech']
            ],
            [
                'id' => 12,
                'category_id' => 14,
                'name' => 'Custom Illustration & Digital Art',
                'title_template' => 'Custom Vector Illustrations & Digital Artwork',
                'description_template' => 'Original digital illustrations for websites, book covers, merchandise, and creative media projects.',
                'suggested_price_min' => 35,
                'suggested_price_max' => 120,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ]
        ]
    ],
    [
        'category_id' => 16,
        'category_name' => 'Writing, Translation & Admin',
        'templates' => [
            [
                'id' => 13,
                'category_id' => 16,
                'name' => 'SEO Blog & Article Writing',
                'title_template' => 'SEO-Optimized Articles & Blog Content Writing',
                'description_template' => 'Well-researched, engaging, and search engine optimized articles designed to rank on Google and provide high value to readers.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 70,
                'pricing_model' => 'flat',
                'suggested_tags' => ['study-guides']
            ],
            [
                'id' => 14,
                'category_id' => 16,
                'name' => 'Website Copywriting & Landing Pages',
                'title_template' => 'High-Converting Website Copy & Sales Pages',
                'description_template' => 'Compelling copywriting for landing pages, hero banners, product descriptions, and email marketing campaigns that turn visitors into customers.',
                'suggested_price_min' => 50,
                'suggested_price_max' => 180,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 15,
                'category_id' => 16,
                'name' => 'Document Proofreading & Editing',
                'title_template' => 'Academic & Professional Document Proofreading',
                'description_template' => 'Thorough editing of essays, reports, and manuscripts for grammar, clarity, readability, academic tone, and citation compliance (APA/MLA/Harvard).',
                'suggested_price_min' => 15,
                'suggested_price_max' => 50,
                'pricing_model' => 'flat',
                'suggested_tags' => ['study-guides']
            ],
            [
                'id' => 16,
                'category_id' => 16,
                'name' => 'Language Translation & Localization',
                'title_template' => 'Accurate Language Translation & Localization',
                'description_template' => 'Human translation between English, Turkish, French, German, Arabic, and Russian with cultural nuance and technical accuracy.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 45,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['study-guides']
            ],
            [
                'id' => 17,
                'category_id' => 16,
                'name' => 'Resume / CV & LinkedIn Optimization',
                'title_template' => 'Professional Resume Rewrite & LinkedIn Profile Revamp',
                'description_template' => 'Modern, ATS-friendly resume formatting and LinkedIn profile restructuring to help you stand out to recruiters and hiring managers.',
                'suggested_price_min' => 25,
                'suggested_price_max' => 60,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 18,
                'category_id' => 16,
                'name' => 'Virtual Assistant & Admin Support',
                'title_template' => 'Virtual Assistant, Calendar & Email Management',
                'description_template' => 'Reliable remote administrative assistance including inbox management, scheduling, data organization, research, and customer communication.',
                'suggested_price_min' => 10,
                'suggested_price_max' => 25,
                'pricing_model' => 'hourly',
                'suggested_tags' => []
            ],
            [
                'id' => 19,
                'category_id' => 16,
                'name' => 'Excel & Spreadsheet Data Modeling',
                'title_template' => 'Excel & Google Sheets Automation, Formulas & Dashboards',
                'description_template' => 'Advanced spreadsheet modeling, custom formula automation, pivot analysis, and automated business tracking dashboards.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 60,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ]
        ]
    ],
    [
        'category_id' => 11,
        'category_name' => 'Tutoring & Education',
        'templates' => [
            [
                'id' => 20,
                'category_id' => 11,
                'name' => 'Mathematics & Statistics Tutoring',
                'title_template' => 'Mathematics, Calculus & Statistics Tutoring',
                'description_template' => 'Personalized 1-on-1 tutoring sessions in Algebra, Calculus I/II/III, Linear Algebra, Probability, and Statistics for all levels.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 40,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['study-guides', 'books']
            ],
            [
                'id' => 21,
                'category_id' => 11,
                'name' => 'Programming & CS Mentorship',
                'title_template' => 'Computer Science Coaching & Code Mentorship',
                'description_template' => 'Hands-on programming assistance in Python, Java, C++, JavaScript, Data Structures, Algorithms, and software engineering principles.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 50,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['tech', 'study-guides']
            ],
            [
                'id' => 22,
                'category_id' => 11,
                'name' => 'Foreign Language Tutoring',
                'title_template' => 'Conversational & Academic Language Tutoring',
                'description_template' => 'Interactive 1-on-1 language lessons focused on conversational fluency, grammar mastery, and accent reduction.',
                'suggested_price_min' => 12,
                'suggested_price_max' => 30,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['study-guides']
            ],
            [
                'id' => 23,
                'category_id' => 11,
                'name' => 'Exam Prep (IELTS/TOEFL)',
                'title_template' => 'IELTS & TOEFL Test Preparation Coaching',
                'description_template' => 'Strategic test preparation covering reading, writing, speaking, and listening sections with structured feedback and mock practice.',
                'suggested_price_min' => 18,
                'suggested_price_max' => 45,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['study-guides']
            ],
            [
                'id' => 24,
                'category_id' => 11,
                'name' => 'Physics & Engineering Mechanics',
                'title_template' => 'Engineering Mechanics & Physics Problem Solving',
                'description_template' => 'Detailed academic coaching for Statics, Dynamics, Thermodynamics, and University Physics problem sets.',
                'suggested_price_min' => 18,
                'suggested_price_max' => 45,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['study-guides']
            ],
            [
                'id' => 25,
                'category_id' => 11,
                'name' => 'Music & Instrument Lessons',
                'title_template' => 'Beginner to Intermediate Instrument Lessons',
                'description_template' => 'Practical music instruction for acoustic/electric guitar, piano keyboard, and music theory fundamentals.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 40,
                'pricing_model' => 'hourly',
                'suggested_tags' => []
            ]
        ]
    ],
    [
        'category_id' => 13,
        'category_name' => 'Moving & Handyman Services',
        'templates' => [
            [
                'id' => 26,
                'category_id' => 13,
                'name' => 'Furniture & Desk Assembly',
                'title_template' => 'Flatpack Furniture & Desk Assembly Help',
                'description_template' => 'Efficient and sturdy assembly of desks, beds, wardrobes, shelving, and office chairs. Equipped with necessary tools.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 35,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 27,
                'category_id' => 13,
                'name' => 'Moving Help & Heavy Lifting',
                'title_template' => 'Local Moving & Heavy Lifting Assistance',
                'description_template' => 'Reliable physical moving assistance for loading, unloading, box carrying, and transport help between flats or rooms.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 35,
                'pricing_model' => 'hourly',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 28,
                'category_id' => 13,
                'name' => 'Bicycle Repair & Tune-Up',
                'title_template' => 'Bicycle Tune-Up, Brake & Tire Repair',
                'description_template' => 'On-site bicycle maintenance including gear tuning, brake adjustment, tube replacement, and chain lubrication.',
                'suggested_price_min' => 10,
                'suggested_price_max' => 30,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 29,
                'category_id' => 13,
                'name' => 'Airport Luggage Transport',
                'title_template' => 'Airport Luggage Transport & Ride Assistance',
                'description_template' => 'Helpful luggage handling, transport accompaniment, and arrival/departure coordination.',
                'suggested_price_min' => 20,
                'suggested_price_max' => 60,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ]
        ]
    ],
    [
        'category_id' => 12,
        'category_name' => 'Cleaning & Domestic Services',
        'templates' => [
            [
                'id' => 30,
                'category_id' => 12,
                'name' => 'Home & Office Deep Cleaning',
                'title_template' => 'Comprehensive Room & Apartment Deep Clean',
                'description_template' => 'Thorough cleaning service including dusting, vacuuming, mopping, bathroom descaling, surface sanitization, and trash removal.',
                'suggested_price_min' => 30,
                'suggested_price_max' => 80,
                'pricing_model' => 'flat',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 31,
                'category_id' => 12,
                'name' => 'End of Tenancy Move-Out Clean',
                'title_template' => 'End of Tenancy & Move-Out Deposit Return Clean',
                'description_template' => 'Detailed deep clean tailored to property handover and deposit-return inspection standards.',
                'suggested_price_min' => 50,
                'suggested_price_max' => 140,
                'pricing_model' => 'flat',
                'suggested_tags' => ['dorms']
            ],
            [
                'id' => 32,
                'category_id' => 12,
                'name' => 'Kitchen & Appliance Sanitation',
                'title_template' => 'Kitchen, Oven & Shared Space Deep Clean',
                'description_template' => 'Deep grease removal and sanitation for stoves, ovens, microwaves, countertops, and refrigerators.',
                'suggested_price_min' => 15,
                'suggested_price_max' => 40,
                'pricing_model' => 'flat',
                'suggested_tags' => ['kitchen']
            ],
            [
                'id' => 33,
                'category_id' => 12,
                'name' => 'Commercial & Event Photography',
                'title_template' => 'Commercial, Event & Portrait Photography',
                'description_template' => 'Professional high-resolution photo coverage for corporate events, parties, portraits, and product campaigns with digital edits included.',
                'suggested_price_min' => 40,
                'suggested_price_max' => 150,
                'pricing_model' => 'flat',
                'suggested_tags' => []
            ],
            [
                'id' => 34,
                'category_id' => 12,
                'name' => 'Event DJ & Audio Setup',
                'title_template' => 'Live Event DJ & Sound System Operation',
                'description_template' => 'Live music mixing, sound setup, and party entertainment with curated playlists and sound equipment management.',
                'suggested_price_min' => 35,
                'suggested_price_max' => 80,
                'pricing_model' => 'hourly',
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
