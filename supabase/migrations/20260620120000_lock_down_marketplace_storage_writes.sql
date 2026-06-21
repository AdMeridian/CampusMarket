-- Lock down marketplace storage writes.
-- Product images are uploaded server-side via the service role (bypasses RLS).
-- Public clients should only read objects from this bucket.

DROP POLICY IF EXISTS "Allow Public Insert" ON storage.objects;
DROP POLICY IF EXISTS "Allow Public Update" ON storage.objects;
DROP POLICY IF EXISTS "Allow Public Delete" ON storage.objects;
DROP POLICY IF EXISTS "Users can view own objects in marketplace" ON storage.objects;

DROP POLICY IF EXISTS "Allow Public Select" ON storage.objects;
CREATE POLICY "Allow Public Select" ON storage.objects
FOR SELECT TO public
USING (bucket_id = 'marketplace');
