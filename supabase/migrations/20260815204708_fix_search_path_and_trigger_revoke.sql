-- Fix: mutable search_path on get_university_by_email
-- Fix: set_product_country_and_university still callable via REST (revoke from PUBLIC)

-- Drop and recreate get_university_by_email with fixed search_path
DROP FUNCTION IF EXISTS public.get_university_by_email(TEXT);

CREATE FUNCTION public.get_university_by_email(email_address TEXT)
RETURNS TABLE (
    university_id   BIGINT,
    university_name TEXT,
    country_code    CHAR(2),
    currency        VARCHAR(3),
    currency_symbol VARCHAR(5)
)
LANGUAGE plpgsql
SECURITY INVOKER
STABLE
SET search_path = ''
AS $$
DECLARE
    v_domain TEXT;
BEGIN
    v_domain := lower(split_part(email_address, '@', 2));

    RETURN QUERY
    SELECT
        u.id                    AS university_id,
        u.name                  AS university_name,
        u.country_code          AS country_code,
        c.default_currency      AS currency,
        c.currency_symbol       AS currency_symbol
    FROM public.universities u
    JOIN public.countries c ON c.code = u.country_code
    WHERE u.is_active = TRUE
      AND c.is_active = TRUE
      AND (
          lower(u.domain_pattern) = v_domain
          OR (
              u.domain_pattern LIKE '*%'
              AND v_domain LIKE '%' || lower(substring(u.domain_pattern FROM 2))
          )
      )
    ORDER BY
        CASE WHEN lower(u.domain_pattern) = v_domain THEN 0 ELSE 1 END
    LIMIT 1;
END;
$$;

GRANT EXECUTE ON FUNCTION public.get_university_by_email(TEXT) TO anon, authenticated;

-- Revoke EXECUTE from PUBLIC (which covers anon + authenticated) on the trigger function
-- Trigger functions should never be directly callable via REST API
REVOKE EXECUTE ON FUNCTION public.set_product_country_and_university() FROM PUBLIC;
