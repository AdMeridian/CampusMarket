-- Migration: Add preferred_language column to users and create message_translations table safely

-- 1. Add preferred_language column to public.users if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 
        FROM information_schema.columns 
        WHERE table_schema = 'public' 
          AND table_name = 'users' 
          AND column_name = 'preferred_language'
    ) THEN
        ALTER TABLE public.users ADD COLUMN preferred_language VARCHAR(5) NOT NULL DEFAULT 'en';
    END IF;
END $$;

-- 2. Create message_translations table if it doesn't exist
CREATE TABLE IF NOT EXISTS public.message_translations (
    id SERIAL PRIMARY KEY,
    message_id INT NOT NULL,
    target_lang VARCHAR(5) NOT NULL,
    translated_text TEXT NOT NULL,
    source_lang VARCHAR(5) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES public.messages(id) ON DELETE CASCADE,
    UNIQUE (message_id, target_lang)
);

-- 3. Create index on message_id if not exists
CREATE INDEX IF NOT EXISTS idx_msg_translations_msg ON public.message_translations(message_id);
