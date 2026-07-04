-- Agent role for managed listing accounts (enum value must be in its own migration).

ALTER TYPE user_role ADD VALUE IF NOT EXISTS 'agent';
