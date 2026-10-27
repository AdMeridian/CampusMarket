BEGIN;

SELECT plan(4);

SELECT ok(
    EXISTS (
        SELECT 1
        FROM pg_policies
        WHERE schemaname = 'public'
          AND tablename = 'provider_profiles'
          AND policyname = 'provider_profiles_read_published'
    ),
    'published provider profile policy exists'
);

SELECT ok(
    NOT EXISTS (
        SELECT 1
        FROM pg_policies
        WHERE schemaname = 'public'
          AND tablename = 'provider_profiles'
          AND policyname = 'provider_profiles_read_published'
          AND qual LIKE '%service_expires_at%'
    ),
    'published provider profile policy does not expire listings'
);

SELECT ok(
    NOT EXISTS (
        SELECT 1
        FROM public.products
        WHERE listing_type = 'service'
          AND service_expires_at IS NOT NULL
    ),
    'existing service listings have no expiration date'
);

SELECT ok(
    to_regclass('public.idx_products_service_expiry') IS NULL,
    'service expiration index is removed'
);

SELECT * FROM finish();
ROLLBACK;
