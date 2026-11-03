-- Migration: 20261103140000_fix_get_university_and_backfill.sql
-- Fix get_university_by_email type casting & subdomain matching, and backfill existing users

CREATE OR REPLACE FUNCTION public.get_university_by_email(email_address text)
RETURNS TABLE(university_id bigint, university_name text, country_code character, currency character varying, currency_symbol character varying)
LANGUAGE plpgsql
STABLE
SET search_path TO ''
AS $function$
DECLARE
    v_domain TEXT;
BEGIN
    v_domain := lower(split_part(email_address, '@', 2));

    RETURN QUERY
    SELECT
        u.id                    AS university_id,
        u.name::text            AS university_name,
        u.country_code::character AS country_code,
        c.default_currency      AS currency,
        c.currency_symbol       AS currency_symbol
    FROM public.universities u
    JOIN public.countries c ON c.code = u.country_code
    WHERE u.is_active = TRUE
      AND c.is_active = TRUE
      AND (
          v_domain = lower(u.domain_pattern)
          OR v_domain LIKE '%.' || lower(u.domain_pattern)
      )
    ORDER BY
        CASE WHEN lower(u.domain_pattern) = v_domain THEN 0 ELSE 1 END,
        length(u.domain_pattern) DESC
    LIMIT 1;
END;
$function$;

-- Backfill existing users whose university_id is NULL but have student email domains
UPDATE public.users u
SET university_id = sub.university_id
FROM (
    SELECT 
        u2.id,
        (SELECT gu.university_id FROM public.get_university_by_email(u2.email) gu LIMIT 1) AS university_id
    FROM public.users u2
    WHERE u2.university_id IS NULL
) sub
WHERE u.id = sub.id
  AND sub.university_id IS NOT NULL;

