ALTER TABLE public.users
    ADD COLUMN IF NOT EXISTS other_university_name VARCHAR(200) NULL;