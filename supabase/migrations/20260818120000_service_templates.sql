-- Migration: 20260818120000_service_templates.sql
-- Description: Service templates table and initial seed catalog for the two-step service creation flow

CREATE TABLE IF NOT EXISTS public.service_templates (
    id BIGSERIAL PRIMARY KEY,
    category_id BIGINT NOT NULL REFERENCES public.categories(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(10) NOT NULL DEFAULT '🛠️',
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

-- Initial seed: 16 templates covering Tutoring (11), Cleaning (12), Moving (13), Photography (14)
INSERT INTO public.service_templates
    (category_id, name, icon, title_template, description_template, suggested_price_min,
     suggested_price_max, pricing_model, suggested_tags, sort_order) VALUES
-- Tutoring (category 11)
(11, 'Math & Science Tutoring', '📐', 'Math & Science Tutoring — All Levels',
 'I offer personalised 1-on-1 tutoring sessions in mathematics, statistics, and science topics. Flexible scheduling on campus or online.',
 15, 40, 'hourly', ARRAY['study-guides', 'books'], 1),
(11, 'Language Lessons', '🗣️', 'Conversational & Academic Language Tutoring',
 'Native/fluent speaker offering foreign language practice, grammar support, and exam preparation for students.',
 12, 35, 'hourly', ARRAY['study-guides'], 2),
(11, 'Coding & CS Coaching', '💻', 'Programming & Computer Science Help',
 'Practical assistance with coding assignments, algorithms, web development, and debugging in Python, Java, C++, or JS.',
 20, 50, 'hourly', ARRAY['tech', 'study-guides'], 3),
(11, 'Essay & Writing Review', '✍️', 'Essay Proofreading & Academic Writing Feedback',
 'Detailed review of academic essays, lab reports, and presentations for structure, grammar, clarity, and citations.',
 25, 80, 'flat', ARRAY['study-guides'], 4),

-- Photography & Media (category 14)
(14, 'Event Photography', '📸', 'Event Photography & Coverage',
 'High-quality photography for campus events, student club activities, parties, or sports. Edited digital photos included.',
 80, 300, 'flat', ARRAY['tech'], 1),
(14, 'Student Portrait & Headshots', '🤳', 'Student Portrait & Professional Headshot Session',
 'Quick 30-minute campus photo session for LinkedIn, CVs, graduation, and profile pictures. 5 edited retouched photos.',
 30, 80, 'flat', ARRAY[], 2),
(14, 'Marketplace Product Photos', '📦', 'Product Photography for Campus Sellers',
 'Clean, well-lit photos for your marketplace listings, notes, and crafts to help you sell faster.',
 20, 60, 'flat', ARRAY[], 3),
(14, 'Video Editing & Social Content', '🎬', 'Video Editing & Content Creation',
 'Fast turnaround video editing for YouTube, TikTok, presentations, and course projects.',
 15, 45, 'hourly', ARRAY['tech'], 4),

-- Cleaning Services (category 12)
(12, 'Dorm Room Deep Clean', '🧹', 'Dorm Room & Studio Cleaning',
 'Full dorm or apartment clean including dusting, vacuuming, mopping, bathroom sanitation, and trash removal.',
 20, 50, 'flat', ARRAY['dorms'], 1),
(12, 'Shared Kitchen Cleaning', '🍳', 'Shared Kitchen & Appliances Clean',
 'Thorough cleaning of shared student kitchens, stoves, microwave, countertops, and sinks.',
 15, 40, 'flat', ARRAY['kitchen'], 2),
(12, 'End of Semester Move-Out Clean', '🏠', 'End of Tenancy / Move-Out Deep Clean',
 'Complete room cleaning tailored to deposit-return standards for campus dorms and student apartments.',
 60, 150, 'flat', ARRAY['dorms'], 3),
(12, 'Laundry & Ironing Service', '👕', 'Laundry Wash, Dry & Fold Service',
 'Convenient pickup and delivery laundry service on campus. Price per wash load.',
 10, 25, 'flat', ARRAY[], 4),

-- Moving & Packing (category 13)
(13, 'Campus Move-in / Move-out Help', '📦', 'Campus Moving & Heavy Lifting Help',
 'Friendly extra hands to help carry boxes, luggage, and belongings into or out of your campus accommodation.',
 15, 30, 'hourly', ARRAY['dorms'], 1),
(13, 'IKEA & Furniture Assembly', '🔧', 'Flatpack & Desk Furniture Assembly',
 'Fast and correct assembly for desks, beds, shelves, and chairs. Bring own screwdrivers and tools.',
 20, 45, 'hourly', ARRAY['dorms'], 2),
(13, 'Packing & Box Organisation', '📦', 'Luggage & Packing Assistance',
 'Help organizing, bubble-wrapping, and packing your room belongings for summer storage or relocation.',
 15, 25, 'hourly', ARRAY['dorms'], 3),
(13, 'Campus Delivery & Courier', '🚲', 'On-Campus Small Item Delivery by Foot/Bike',
 'Fast same-day delivery of documents, books, or small packages between campuses and dorm buildings.',
 5, 20, 'flat', ARRAY[], 4)
ON CONFLICT DO NOTHING;
