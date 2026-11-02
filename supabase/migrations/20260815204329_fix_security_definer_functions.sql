-- Fix SECURITY DEFINER warnings on functions exposed via PostgREST
-- 1. Switch get_university_by_email to SECURITY INVOKER (reads public data, no elevation needed)
-- 2. Revoke direct EXECUTE on trigger function from anon/authenticated roles

-- Must drop first since return type metadata differs between SECURITY DEFINER and INVOKER
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
AS $$
DECLARE
    v_domain TEXT;
BEGIN
    v_domain := lower(split_part(email_address, '@', 2));

    RETURN QUERY
    SELECT
        u.id                AS university_id,
        u.name              AS university_name,
        u.country_code      AS country_code,
        c.default_currency  AS currency,
        c.currency_symbol   AS currency_symbol
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

-- Ensure anon and authenticated can read universities and countries (needed for SECURITY INVOKER)
GRANT SELECT ON public.universities TO anon, authenticated;
GRANT SELECT ON public.countries TO anon, authenticated;
GRANT EXECUTE ON FUNCTION public.get_university_by_email(TEXT) TO anon, authenticated;

-- Revoke direct REST API execution of the trigger function (only triggers should call it)
REVOKE EXECUTE ON FUNCTION public.set_product_country_and_university() FROM anon, authenticated;
