BEGIN;

SELECT plan(10);

SELECT ok(
    (SELECT relrowsecurity FROM pg_class WHERE oid = 'public.service_job_posts'::regclass),
    'RLS is enabled for service job posts'
);
SELECT ok(has_column_privilege('anon', 'public.service_job_posts', 'id', 'SELECT'), 'anonymous users can read public requests');
SELECT ok(NOT has_column_privilege('anon', 'public.service_job_posts', 'poster_id', 'INSERT'), 'anonymous users cannot create requests');
SELECT ok(NOT has_table_privilege('anon', 'public.service_job_posts', 'UPDATE'), 'anonymous users cannot update requests');
SELECT ok(NOT has_table_privilege('anon', 'public.service_job_posts', 'DELETE'), 'anonymous users cannot delete requests');
SELECT ok(has_column_privilege('authenticated', 'public.service_job_posts', 'id', 'SELECT'), 'signed-in users can read requests');
SELECT ok(has_column_privilege('authenticated', 'public.service_job_posts', 'poster_id', 'INSERT'), 'signed-in users can create requests');
SELECT ok(has_column_privilege('authenticated', 'public.service_job_posts', 'expires_at', 'INSERT'), 'signed-in users can set bounded request expiry');
SELECT ok(has_column_privilege('authenticated', 'public.service_job_posts', 'status', 'UPDATE'), 'signed-in users can close their requests');
SELECT ok(NOT has_table_privilege('authenticated', 'public.service_job_posts', 'DELETE'), 'signed-in users cannot delete requests');

SELECT * FROM finish();
ROLLBACK;
