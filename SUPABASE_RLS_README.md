This repository contains a migration to enable Row-Level Security (RLS) on the
`product_categories` table and a recommended policy configuration.

Summary
- Migration file: supabase/migrations/20260802121500_product_categories_rls.sql
- Purpose: Enable RLS and permit read-only SELECTs for `anon` and `authenticated` roles.
- Writes (INSERT/UPDATE/DELETE): Intentionally not allowed for client roles. Use the
  `service_role` key for server-side writes, or add policies that validate ownership
  if you require client-side writes.

How to apply
- Use the Supabase SQL editor or `supabase` CLI to apply migrations.

Notes on ownership-based policies (optional)
- If you want authenticated users to insert product-category rows only for products
  they own, you can add an INSERT policy like:

  CREATE POLICY insert_if_owner ON public.product_categories
    FOR INSERT
    TO authenticated
    WITH CHECK (
      EXISTS (
        SELECT 1 FROM public.products p
        WHERE p.id = product_categories.product_id
          AND p.user_id = current_setting('request.jwt.claims.user_id')::int
      )
    );

- Adjust `request.jwt.claims.user_id` mapping according to your JWT claim config.
