-- Multi-Country & Universities Architecture Migration
-- Scopes marketplaces strictly by User -> University -> Country

-- 1. Create Countries Table
CREATE TABLE IF NOT EXISTS public.countries (
    code CHAR(2) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    default_currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    currency_symbol VARCHAR(5) NOT NULL DEFAULT '$',
    symbol_position VARCHAR(10) NOT NULL DEFAULT 'before' CHECK (symbol_position IN ('before', 'after')),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 2. Create Universities Table
CREATE TABLE IF NOT EXISTS public.universities (
    id BIGSERIAL PRIMARY KEY,
    country_code CHAR(2) NOT NULL REFERENCES public.countries(code) ON DELETE RESTRICT,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    domain_pattern VARCHAR(150) NOT NULL,
    city VARCHAR(100) NULL,
    logo_url VARCHAR(255) NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- 3. Modify Users Table
ALTER TABLE public.users 
    ADD COLUMN IF NOT EXISTS university_id BIGINT REFERENCES public.universities(id) ON DELETE SET NULL;

-- Remove country_code from users if it exists (country is strictly derived from university)
DO $$ 
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_schema = 'public' AND table_name = 'users' AND column_name = 'country_code'
    ) THEN
        ALTER TABLE public.users DROP COLUMN country_code;
    END IF;
END $$;

-- 4. Modify Products Table
ALTER TABLE public.products 
    ADD COLUMN IF NOT EXISTS country_code CHAR(2) REFERENCES public.countries(code) ON DELETE RESTRICT,
    ADD COLUMN IF NOT EXISTS university_id BIGINT REFERENCES public.universities(id) ON DELETE SET NULL;

-- 5. Modify Orders Table (Historical Transaction Isolation)
ALTER TABLE public.orders 
    ADD COLUMN IF NOT EXISTS country_code CHAR(2) REFERENCES public.countries(code) ON DELETE RESTRICT,
    ADD COLUMN IF NOT EXISTS currency VARCHAR(3) NOT NULL DEFAULT 'USD';

-- 6. Performance Indexes
CREATE INDEX IF NOT EXISTS idx_universities_country ON public.universities(country_code);
CREATE INDEX IF NOT EXISTS idx_universities_domain ON public.universities(domain_pattern);
CREATE INDEX IF NOT EXISTS idx_users_university ON public.users(university_id);
CREATE INDEX IF NOT EXISTS idx_products_country_status ON public.products(country_code, status, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_products_university_status ON public.products(university_id, status, created_at DESC);

-- 7. Row Level Security (RLS)
ALTER TABLE public.countries ENABLE ROW LEVEL SECURITY;
ALTER TABLE public.universities ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS "Allow public read active countries" ON public.countries;
CREATE POLICY "Allow public read active countries" 
    ON public.countries FOR SELECT 
    USING (is_active = true);

DROP POLICY IF EXISTS "Allow public read active universities" ON public.universities;
CREATE POLICY "Allow public read active universities" 
    ON public.universities FOR SELECT 
    USING (is_active = true);

-- 8. Trigger Function: Automatically assign Product country & university from Seller's profile
CREATE OR REPLACE FUNCTION public.set_product_country_and_university()
RETURNS TRIGGER 
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
BEGIN
    SELECT u.country_code, usr.university_id 
    INTO NEW.country_code, NEW.university_id
    FROM public.users usr
    JOIN public.universities u ON u.id = usr.university_id
    WHERE usr.id = NEW.user_id;

    -- Fallback default for existing legacy users if needed
    IF NEW.country_code IS NULL THEN
        NEW.country_code := 'TR';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_set_product_country ON public.products;
CREATE TRIGGER trg_set_product_country
BEFORE INSERT ON public.products
FOR EACH ROW
EXECUTE FUNCTION public.set_product_country_and_university();

-- 9. RPC Function: University and Country Lookup by Email Domain
CREATE OR REPLACE FUNCTION public.get_university_by_email(email_address TEXT)
RETURNS TABLE (
    university_id BIGINT,
    university_name VARCHAR,
    country_code CHAR(2),
    currency VARCHAR(3),
    currency_symbol VARCHAR(5)
) 
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public, pg_temp
AS $$
DECLARE
    domain_part TEXT;
BEGIN
    domain_part := LOWER(SPLIT_PART(email_address, '@', 2));
    
    RETURN QUERY
    SELECT 
        u.id AS university_id,
        u.name AS university_name,
        u.country_code,
        c.default_currency AS currency,
        c.currency_symbol
    FROM public.universities u
    JOIN public.countries c ON c.code = u.country_code
    WHERE u.is_active = true
      AND (
          u.domain_pattern = domain_part
          OR domain_part LIKE REPLACE(u.domain_pattern, '*', '%')
      )
    ORDER BY LENGTH(u.domain_pattern) DESC
    LIMIT 1;
END;
$$;

-- 10. Seed Initial Countries and Top Universities
INSERT INTO public.countries (code, name, default_currency, currency_symbol, symbol_position) VALUES
    ('TR', 'Turkey', 'TRY', '₺', 'after'),
    ('US', 'United States', 'USD', '$', 'before'),
    ('GB', 'United Kingdom', 'GBP', '£', 'before'),
    ('DE', 'Germany', 'EUR', '€', 'before'),
    ('CA', 'Canada', 'CAD', '$', 'before')
ON CONFLICT (code) DO NOTHING;

INSERT INTO public.universities (country_code, name, slug, domain_pattern, city) VALUES
    ('TR', 'Middle East Technical University', 'metu', 'metu.edu.tr', 'Ankara'),
    ('TR', 'Bilkent University', 'bilkent', 'bilkent.edu.tr', 'Ankara'),
    ('GB', 'University of Oxford', 'oxford', 'ox.ac.uk', 'Oxford'),
    ('GB', 'University of Cambridge', 'cambridge', 'cam.ac.uk', 'Cambridge'),
    ('US', 'Harvard University', 'harvard', 'harvard.edu', 'Cambridge'),
    ('US', 'Stanford University', 'stanford', 'stanford.edu', 'Stanford'),
    ('DE', 'Technical University of Munich', 'tum', 'tum.de', 'Munich')
ON CONFLICT (slug) DO NOTHING;
