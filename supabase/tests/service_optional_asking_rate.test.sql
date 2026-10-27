BEGIN;

SELECT plan(2);

SELECT ok(
    (
        SELECT is_nullable = 'YES'
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'products'
          AND column_name = 'price'
    ),
    'service asking rates can be null'
);

SELECT ok(
    EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conrelid = 'public.products'::regclass
          AND conname = 'products_price_required_for_non_service'
          AND pg_get_constraintdef(oid) LIKE '%listing_type = ''service''%'
          AND pg_get_constraintdef(oid) LIKE '%price IS NOT NULL%'
    ),
    'non-service product prices remain required'
);

SELECT * FROM finish();
ROLLBACK;
