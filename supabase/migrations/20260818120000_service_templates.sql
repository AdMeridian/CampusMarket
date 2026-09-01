-- Migration: 20260818120000_service_templates.sql
-- Description: Service templates table and generalized freelance catalog (without icons)

CREATE TABLE IF NOT EXISTS public.service_templates (
    id BIGSERIAL PRIMARY KEY,
    category_id BIGINT NOT NULL REFERENCES public.categories(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    title_template VARCHAR(200) NOT NULL,
    description_template TEXT NOT NULL,
    suggested_price_min NUMERIC(10,2) NULL,
    suggested_price_max NUMERIC(10,2) NULL,
    pricing_model VARCHAR(10) NOT NULL DEFAULT 'hourly'
        CHECK (pricing_model IN ('flat', 'hourly')),
    suggested_tags TEXT[] NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_service_templates_category ON public.service_templates(category_id);
CREATE INDEX IF NOT EXISTS idx_service_templates_active ON public.service_templates(is_active);

-- Seed: 34 generalized professional freelance templates across 6 domains
INSERT INTO public.service_templates
    (category_id, name, title_template, description_template, suggested_price_min,
     suggested_price_max, pricing_model, suggested_tags, sort_order) VALUES

-- 1. Web, Software & Tech Support (Category 15)
(15, 'Custom Website Development', 'Custom Website Development — Responsive & Modern',
 'Full-stack website development tailored to your requirements. Tech stack includes React, Next.js, WordPress, Shopify, or clean semantic HTML/CSS with fast load speeds and mobile responsiveness.',
 150, 600, 'flat', ARRAY['tech'], 1),
(15, 'Mobile App Development', 'Cross-Platform Mobile App Development',
 'Custom mobile app development for iOS and Android using React Native or Flutter. Clean architecture, seamless API integration, and modern UI design.',
 250, 900, 'flat', ARRAY['tech'], 2),
(15, 'Python Scripting & Automation', 'Python Automation, Web Scraping & Scripting',
 'Custom Python scripts to automate repetitive tasks, scrape web data, process spreadsheets, and build custom bots or API connectors.',
 25, 60, 'hourly', ARRAY['tech'], 3),
(15, 'UI/UX Design & Figma Prototyping', 'UI/UX Interface Design & Interactive Figma Prototypes',
 'Modern, user-centered UI/UX design for web applications and mobile apps. Includes wireframing, component design systems, and clickable Figma prototypes.',
 100, 400, 'flat', ARRAY['tech'], 4),
(15, 'Database Design & SQL Optimization', 'Database Schema Design & SQL Query Optimization',
 'Relational database architecture, performance tuning, indexing, and complex SQL query optimization in PostgreSQL and MySQL.',
 25, 65, 'hourly', ARRAY['tech'], 5),
(15, 'IT & Computer Troubleshooting', 'Computer Setup, OS Formatting & IT Troubleshooting',
 'Hands-on IT technical support including clean OS reinstallation, virus/malware removal, driver configuration, and hardware performance diagnostics.',
 20, 50, 'flat', ARRAY['tech'], 6),

-- 2. Design, Photography & Media (Category 14)
(14, 'Brand Identity & Logo Design', 'Professional Logo Design & Brand Identity Package',
 'High-impact vector logo design, brand typography rules, color palettes, and brand guidelines delivered in SVG, PNG, and PDF formats.',
 40, 150, 'flat', ARRAY[], 1),
(14, 'Social Media & Ad Creatives', 'Social Media Graphics & High-Converting Ad Banners',
 'Engaging custom graphics for Instagram, TikTok, LinkedIn, and Facebook ad campaigns. Tailored to drive clicks and brand recognition.',
 25, 80, 'flat', ARRAY[], 2),
(14, 'Video Editing & Post-Production', 'Professional Video Editing & Post-Production',
 'High-quality video editing for YouTube, Reels, TikTok, podcasts, and commercial presentations with seamless transitions, color grading, and subtitles.',
 20, 50, 'hourly', ARRAY['tech'], 3),
(14, '3D Modeling & Product Rendering', 'Photorealistic 3D Modeling & Product Rendering',
 'High-detail 3D assets and product mockups modeled in Blender or SolidWorks for marketing, e-commerce, and technical visualization.',
 50, 200, 'flat', ARRAY['tech'], 4),
(14, 'Audio Editing & Music Production', 'Podcast Audio Cleanup, Voiceover & Music Mixing',
 'Professional sound editing including background noise removal, EQ balancing, compression, audio mastering, and voiceover post-production.',
 30, 100, 'flat', ARRAY['tech'], 5),
(14, 'Custom Illustration & Digital Art', 'Custom Vector Illustrations & Digital Artwork',
 'Original digital illustrations for websites, book covers, merchandise, and creative media projects.',
 35, 120, 'flat', ARRAY[], 6),

-- 3. Writing, Translation & Admin (Category 16)
(16, 'SEO Blog & Article Writing', 'SEO-Optimized Articles & Blog Content Writing',
 'Well-researched, engaging, and search engine optimized articles designed to rank on Google and provide high value to readers.',
 20, 70, 'flat', ARRAY['study-guides'], 1),
(16, 'Website Copywriting & Landing Pages', 'High-Converting Website Copy & Sales Pages',
 'Compelling copywriting for landing pages, hero banners, product descriptions, and email marketing campaigns that turn visitors into customers.',
 50, 180, 'flat', ARRAY[], 2),
(16, 'Document Proofreading & Editing', 'Academic & Professional Document Proofreading',
 'Thorough editing of essays, reports, and manuscripts for grammar, clarity, readability, academic tone, and citation compliance (APA/MLA/Harvard).',
 15, 50, 'flat', ARRAY['study-guides'], 3),
(16, 'Language Translation & Localization', 'Accurate Language Translation & Localization',
 'Human translation between English, Turkish, French, German, Arabic, and Russian with cultural nuance and technical accuracy.',
 15, 45, 'hourly', ARRAY['study-guides'], 4),
(16, 'Resume / CV & LinkedIn Optimization', 'Professional Resume Rewrite & LinkedIn Profile Revamp',
 'Modern, ATS-friendly resume formatting and LinkedIn profile restructuring to help you stand out to recruiters and hiring managers.',
 25, 60, 'flat', ARRAY[], 5),
(16, 'Virtual Assistant & Admin Support', 'Virtual Assistant, Calendar & Email Management',
 'Reliable remote administrative assistance including inbox management, scheduling, data organization, research, and customer communication.',
 10, 25, 'hourly', ARRAY[], 6),
(16, 'Excel & Spreadsheet Data Modeling', 'Excel & Google Sheets Automation, Formulas & Dashboards',
 'Advanced spreadsheet modeling, custom formula automation, pivot analysis, and automated business tracking dashboards.',
 20, 60, 'flat', ARRAY[], 7),

-- 4. Tutoring & Education (Category 11)
(11, 'Mathematics & Statistics Tutoring', 'Mathematics, Calculus & Statistics Tutoring',
 'Personalized 1-on-1 tutoring sessions in Algebra, Calculus I/II/III, Linear Algebra, Probability, and Statistics for all levels.',
 15, 40, 'hourly', ARRAY['study-guides', 'books'], 1),
(11, 'Programming & Computer Science Mentorship', 'Computer Science Coaching & Code Mentorship',
 'Hands-on programming assistance in Python, Java, C++, JavaScript, Data Structures, Algorithms, and software engineering principles.',
 20, 50, 'hourly', ARRAY['tech', 'study-guides'], 2),
(11, 'Foreign Language Tutoring', 'Conversational & Academic Language Tutoring',
 'Interactive 1-on-1 language lessons focused on conversational fluency, grammar mastery, and accent reduction.',
 12, 30, 'hourly', ARRAY['study-guides'], 3),
(11, 'Standardized Exam Prep (IELTS/TOEFL)', 'IELTS & TOEFL Test Preparation Coaching',
 'Strategic test preparation covering reading, writing, speaking, and listening sections with structured feedback and mock practice.',
 18, 45, 'hourly', ARRAY['study-guides'], 4),
(11, 'Physics & Engineering Mechanics', 'Engineering Mechanics & Physics Problem Solving',
 'Detailed academic coaching for Statics, Dynamics, Thermodynamics, and University Physics problem sets.',
 18, 45, 'hourly', ARRAY['study-guides'], 5),
(11, 'Music & Instrument Lessons', 'Beginner to Intermediate Instrument Lessons',
 'Practical music instruction for acoustic/electric guitar, piano keyboard, and music theory fundamentals.',
 15, 40, 'hourly', ARRAY[], 6),

-- 5. Moving & Handyman Services (Category 13)
(13, 'Furniture & Desk Assembly', 'Flatpack Furniture & Desk Assembly Help',
 'Efficient and sturdy assembly of desks, beds, wardrobes, shelving, and office chairs. Equipped with necessary tools.',
 15, 35, 'hourly', ARRAY['dorms'], 1),
(13, 'Moving Help & Heavy Lifting', 'Local Moving & Heavy Lifting Assistance',
 'Reliable physical moving assistance for loading, unloading, box carrying, and transport help between flats or rooms.',
 15, 35, 'hourly', ARRAY['dorms'], 2),
(13, 'Bicycle Repair & Tune-Up', 'Bicycle Tune-Up, Brake & Tire Repair',
 'On-site bicycle maintenance including gear tuning, brake adjustment, tube replacement, and chain lubrication.',
 10, 30, 'flat', ARRAY[], 3),
(13, 'Airport Luggage Transport & Assistance', 'Airport Luggage Transport & Ride Assistance',
 'Helpful luggage handling, transport accompaniment, and arrival/departure coordination.',
 20, 60, 'flat', ARRAY[], 4),

-- 6. Cleaning & Domestic Services (Category 12)
(12, 'Home & Office Deep Cleaning', 'Comprehensive Room & Apartment Deep Clean',
 'Thorough cleaning service including dusting, vacuuming, mopping, bathroom descaling, surface sanitization, and trash removal.',
 30, 80, 'flat', ARRAY['dorms'], 1),
(12, 'End of Tenancy Move-Out Clean', 'End of Tenancy & Move-Out Deposit Return Clean',
 'Detailed deep clean tailored to property handover and deposit-return inspection standards.',
 50, 140, 'flat', ARRAY['dorms'], 2),
(12, 'Kitchen & Appliance Sanitation', 'Kitchen, Oven & Shared Space Deep Clean',
 'Deep grease removal and sanitation for stoves, ovens, microwaves, countertops, and refrigerators.',
 15, 40, 'flat', ARRAY['kitchen'], 3),
(12, 'Commercial & Event Photography', 'Commercial, Event & Portrait Photography',
 'Professional high-resolution photo coverage for corporate events, parties, portraits, and product campaigns with digital edits included.',
 40, 150, 'flat', ARRAY[], 4),
(12, 'Event DJ & Audio Setup', 'Live Event DJ & Sound System Operation',
 'Live music mixing, sound setup, and party entertainment with curated playlists and sound equipment management.',
 35, 80, 'hourly', ARRAY[], 5)
ON CONFLICT DO NOTHING;
