-- Migration: Create marketplace storage bucket and setup public storage RLS policies

-- 1. Create a storage bucket for product images if it doesn't exist
INSERT INTO storage.buckets (id, name, public)
VALUES ('marketplace', 'marketplace', true)
ON CONFLICT (id) DO NOTHING;

-- 2. Configure RLS policies for storage.objects on the marketplace bucket
-- Policy to allow anyone to select objects in the marketplace bucket
DROP POLICY IF EXISTS "Allow Public Select" ON storage.objects;
CREATE POLICY "Allow Public Select" ON storage.objects
FOR SELECT TO public
USING (bucket_id = 'marketplace');

-- Policy to allow anyone (public/anon) to insert objects into the marketplace bucket
DROP POLICY IF EXISTS "Allow Public Insert" ON storage.objects;
CREATE POLICY "Allow Public Insert" ON storage.objects
FOR INSERT TO public
WITH CHECK (bucket_id = 'marketplace');

-- Policy to allow anyone to update objects in the marketplace bucket
DROP POLICY IF EXISTS "Allow Public Update" ON storage.objects;
CREATE POLICY "Allow Public Update" ON storage.objects
FOR UPDATE TO public
USING (bucket_id = 'marketplace');

-- Policy to allow anyone to delete objects in the marketplace bucket
DROP POLICY IF EXISTS "Allow Public Delete" ON storage.objects;
CREATE POLICY "Allow Public Delete" ON storage.objects
FOR DELETE TO public
USING (bucket_id = 'marketplace');
