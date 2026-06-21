-- Structured report fields, moderation audit columns, and backfill from legacy reason text.

alter table reports
  add column if not exists issue_type varchar(32),
  add column if not exists description text,
  add column if not exists reference_link text,
  add column if not exists resolution varchar(32),
  add column if not exists resolved_at timestamptz,
  add column if not exists resolved_by_admin_id bigint references users(id) on delete set null,
  add column if not exists admin_notes text;

create index if not exists idx_reports_issue_type on reports (issue_type) where issue_type is not null;
create index if not exists idx_reports_pending_product on reports (product_id) where status = 'pending' and product_id is not null;
create index if not exists idx_reports_pending_reported_user on reports (reported_user_id) where status = 'pending' and reported_user_id is not null;

update reports
set
  issue_type = lower((regexp_match(reason, '^\[([A-Z_]+)\]'))[1]),
  description = trim(
    case
      when reason ~ E'\n\n'
        then split_part(regexp_replace(reason, '^\[[A-Z_]+\]\s*', ''), E'\n\n', 1)
      else regexp_replace(reason, '^\[[A-Z_]+\]\s*', '')
    end
  ),
  reference_link = nullif(
    trim(
      case
        when reason ~ 'Reference Link:'
          then regexp_replace(reason, '.*Reference Link:\s*', '', 'n')
        else ''
      end
    ),
    ''
  )
where issue_type is null
  and reason ~ '^\[';

update reports
set issue_type = coalesce(issue_type, 'other'),
    description = coalesce(nullif(trim(description), ''), trim(reason))
where description is null or issue_type is null;
