-- Service listings remain visible until their owner or a moderator changes their status.
UPDATE public.products
SET service_expires_at = NULL
WHERE listing_type = 'service'
  AND service_expires_at IS NOT NULL;

DROP INDEX IF EXISTS public.idx_products_service_expiry;

DROP POLICY IF EXISTS provider_profiles_read_published ON public.provider_profiles;
CREATE POLICY provider_profiles_read_published
ON public.provider_profiles
FOR SELECT
TO anon, authenticated
USING (
    enrollment_status = 'pilot'
    AND is_verified
    AND EXISTS (
        SELECT 1
        FROM public.products AS service_listing
        WHERE service_listing.user_id = provider_profiles.user_id
          AND service_listing.listing_type = 'service'
          AND service_listing.status = 'active'
    )
);