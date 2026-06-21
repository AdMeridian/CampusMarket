-- Rate limit helper for Supabase Edge Function chatbot (reuses rate_limit_buckets).

create or replace function public.chatbot_rate_limit_allow(
  p_bucket text,
  p_max_hits integer default 40,
  p_window_seconds integer default 3600
)
returns boolean
language plpgsql
security definer
set search_path = public
as $$
declare
  v_hit integer;
  v_start timestamptz;
begin
  if p_bucket is null or length(trim(p_bucket)) = 0 then
    return true;
  end if;

  select hit_count, window_start
  into v_hit, v_start
  from rate_limit_buckets
  where bucket_key = p_bucket
  for update;

  if not found then
    insert into rate_limit_buckets (bucket_key, hit_count, window_start)
    values (p_bucket, 1, now());
    return true;
  end if;

  if extract(epoch from (now() - v_start)) >= p_window_seconds then
    update rate_limit_buckets
    set hit_count = 1, window_start = now()
    where bucket_key = p_bucket;
    return true;
  end if;

  if v_hit >= p_max_hits then
    return false;
  end if;

  update rate_limit_buckets
  set hit_count = hit_count + 1
  where bucket_key = p_bucket;

  return true;
end;
$$;

revoke all on function public.chatbot_rate_limit_allow(text, integer, integer) from public;
grant execute on function public.chatbot_rate_limit_allow(text, integer, integer) to service_role;
