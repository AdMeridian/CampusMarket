const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('Account lockout helpers and translations are properly configured', async () => {
  const en = JSON.parse(fs.readFileSync(path.join(__dirname, '../public/lang/en.extra.json'), 'utf8'));
  const tr = JSON.parse(fs.readFileSync(path.join(__dirname, '../public/lang/tr.extra.json'), 'utf8'));

  expect(en['auth.error_account_locked']).toContain('{minutes}');
  expect(en['auth.error_invalid_with_attempts']).toContain('{remaining}');
  expect(en['auth.reset_password_prompt']).toBeTruthy();

  expect(tr['auth.error_account_locked']).toContain('{minutes}');
  expect(tr['auth.error_invalid_with_attempts']).toContain('{remaining}');
  expect(tr['auth.reset_password_prompt']).toBeTruthy();

  const functionsMember2 = fs.readFileSync(path.join(__dirname, '../includes/functions_member2.php'), 'utf8');
  expect(functionsMember2).toContain('checkAccountLoginLockout');
  expect(functionsMember2).toContain('recordFailedLoginAttempt');
  expect(functionsMember2).toContain('clearLoginAttempts');

  const loginPage = fs.readFileSync(path.join(__dirname, '../pages/login.php'), 'utf8');
  expect(loginPage).toContain('checkAccountLoginLockout');
  expect(loginPage).toContain('recordFailedLoginAttempt');
  expect(loginPage).toContain('clearLoginAttempts');
  expect(loginPage).toContain('auth.error_account_locked');
  expect(loginPage).toContain('pages/forgot_password.php?email=');

  const dbConfig = fs.readFileSync(path.join(__dirname, '../config/db.php'), 'utf8');
  expect(dbConfig).toContain('ensureLoginAttemptsTable');

  const migration = fs.readFileSync(
    path.join(__dirname, '../supabase/migrations/20261103130000_create_login_attempts.sql'),
    'utf8'
  );
  expect(migration).toContain('CREATE TABLE IF NOT EXISTS public.login_attempts');
  expect(migration).toContain('ENABLE ROW LEVEL SECURITY');
});
