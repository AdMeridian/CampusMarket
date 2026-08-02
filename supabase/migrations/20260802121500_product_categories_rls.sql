-- Enable Row-Level Security for product_categories and allow read access via the Data API
-- Modifications to this table (INSERT/UPDATE/DELETE) should be performed server-side
-- using the Supabase service_role key (which bypasses RLS). If you intend to allow
-- client-side writes, you must replace the INSERT/UPDATE/DELETE policies with
-- appropriate checks that verify ownership (auth.uid() mapping to your app user).

ALTER TABLE public.product_categories ENABLE ROW LEVEL SECURITY;

-- Allow anonymous (Data API) selects if you expose this table publicly. If you
-- prefer only authenticated users to read, remove the anon policy below.
CREATE POLICY allow_select_for_anon ON public.product_categories
  FOR SELECT
  TO anon
  USING (true);

CREATE POLICY allow_select_for_authenticated ON public.product_categories
  FOR SELECT
  TO authenticated
  USING (true);

-- No INSERT/UPDATE/DELETE policies: only the service_role key can modify rows.
-- If you need client-side create/update/delete, add policies that validate the
-- current_user's ownership, e.g. USING (exists(select 1 from products p where p.id = product_id and p.user_id = current_setting('request.jwt.claims.user_id')::int))
