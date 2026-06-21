-- Optional target user for harassment / abuse reports (listing reports use product_id).

alter table reports
  add column if not exists reported_user_id bigint references users(id) on delete set null;

create index if not exists idx_reports_reported_user_id on reports (reported_user_id);
create index if not exists idx_reports_reporter_product_pending on reports (reporter_id, product_id) where status = 'pending';
