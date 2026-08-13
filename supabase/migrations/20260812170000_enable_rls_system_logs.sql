-- Security Hardening: Enable RLS on system_logs table and restrict public PostgREST API access.
-- Server-side PHP PDO connections and service_role bypass RLS.

ALTER TABLE public.system_logs ENABLE ROW LEVEL SECURITY;

DROP POLICY IF EXISTS system_logs_no_client_access ON public.system_logs;

CREATE POLICY system_logs_no_client_access
ON public.system_logs
FOR ALL
TO authenticated, anon
USING (false)
WITH CHECK (false);
