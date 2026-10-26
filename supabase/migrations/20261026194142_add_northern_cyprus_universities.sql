ALTER TABLE public.universities
    ADD COLUMN IF NOT EXISTS is_northern_cyprus BOOLEAN NOT NULL DEFAULT FALSE;

INSERT INTO public.universities
    (country_code, name, slug, domain_pattern, city, is_northern_cyprus)
VALUES
    ('TR', 'Arkın University of Creative Arts and Design', 'arucad', 'arucad.edu.tr', 'Kyrenia', TRUE),
    ('TR', 'Bahçeşehir Cyprus University', 'bau-cyprus', 'baucyprus.edu.tr', 'Nicosia', TRUE),
    ('TR', 'Cyprus Health and Social Sciences University', 'kstu', 'kstu.edu.tr', 'Güzelyurt', TRUE),
    ('TR', 'Cyprus International University', 'ciu', 'ciu.edu.tr', 'Nicosia', TRUE),
    ('TR', 'Eastern Mediterranean University', 'emu', 'emu.edu.tr', 'Famagusta', TRUE),
    ('TR', 'European University of Lefke', 'eul', 'eul.edu.tr', 'Lefke', TRUE),
    ('TR', 'Final International University', 'final', 'final.edu.tr', 'Kyrenia', TRUE),
    ('TR', 'Girne American University', 'gau', 'gau.edu.tr', 'Kyrenia', TRUE),
    ('TR', 'Middle East Technical University Northern Cyprus Campus', 'metu-ncc', 'ncc.metu.edu.tr', 'Güzelyurt', TRUE),
    ('TR', 'Near East University', 'neu', 'neu.edu.tr', 'Nicosia', TRUE),
    ('TR', 'University of Kyrenia', 'kyrenia', 'kyrenia.edu.tr', 'Kyrenia', TRUE)
ON CONFLICT (slug) DO UPDATE
SET country_code = EXCLUDED.country_code,
    name = EXCLUDED.name,
    domain_pattern = EXCLUDED.domain_pattern,
    city = EXCLUDED.city,
    is_active = TRUE,
    is_northern_cyprus = TRUE;

CREATE INDEX IF NOT EXISTS idx_universities_northern_cyprus_active
    ON public.universities (name)
    WHERE is_active = TRUE AND is_northern_cyprus = TRUE;