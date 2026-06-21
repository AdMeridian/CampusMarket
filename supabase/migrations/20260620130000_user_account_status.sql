-- Account suspension for moderation (warn = notification only; suspend blocks login).

do $$ begin
  create type account_status as enum ('active', 'suspended');
exception
  when duplicate_object then null;
end $$;

alter table users
  add column if not exists account_status account_status not null default 'active';

create index if not exists idx_users_account_status on users (account_status);
