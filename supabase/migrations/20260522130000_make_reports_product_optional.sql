-- Align reports schema with report page behavior:
-- report links are optional, so product_id must be nullable.

alter table reports
  alter column product_id drop not null;
