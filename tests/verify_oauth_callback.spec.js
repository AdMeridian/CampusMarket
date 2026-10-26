const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('Supabase OAuth callback submits its tokens after the document body exists', async ({ page }) => {
  const php = fs.readFileSync(path.join(__dirname, '../pages/verify_email.php'), 'utf8');
  const bodyStart = php.indexOf('<body>');
  const scriptStart = php.indexOf('<script>', bodyStart);
  const scriptEnd = php.indexOf('</script>', scriptStart);

  expect(bodyStart).toBeGreaterThanOrEqual(0);
  expect(scriptStart).toBeGreaterThan(bodyStart);
  expect(scriptEnd).toBeGreaterThan(scriptStart);

  const script = php
    .slice(scriptStart + '<script>'.length, scriptEnd)
    .replaceAll('<?php echo BASE_URL; ?>', 'https://campusmarket.test/');
  const callbackHtml = `<!doctype html>
    <html><head><meta charset="utf-8"><title>Verifying</title></head>
    <body><p>Verifying your link...</p><script>${script}</script></body></html>`;

  let submittedForm;
  await page.route('https://campusmarket.test/pages/verify_email.php*', async (route) => {
    if (route.request().method() === 'POST') {
      submittedForm = route.request().postData();
      await route.fulfill({ status: 200, body: 'OAuth callback received' });
      return;
    }
    await route.fulfill({ status: 200, contentType: 'text/html', body: callbackHtml });
  });

  await page.goto('https://campusmarket.test/pages/verify_email.php#access_token=access123&refresh_token=refresh456&type=bearer');
  await expect.poll(() => submittedForm).toContain('access_token=access123');
  expect(submittedForm).toContain('refresh_token=refresh456');
  expect(submittedForm).toContain('type=bearer');
});

test('first-time OAuth user insert uses a PostgreSQL string literal for the role', async () => {
  const php = fs.readFileSync(path.join(__dirname, '../pages/verify_email.php'), 'utf8');

  expect(php).toMatch(/INSERT INTO users \(username, email, password_hash, role, is_verified\)\s+VALUES \(:u, :e, :h, \\'user\\', TRUE\)/);
});

test('new OAuth accounts get a profile setup notification linking to edit profile', async () => {
  const callback = fs.readFileSync(path.join(__dirname, '../pages/verify_email.php'), 'utf8');
  const helpers = fs.readFileSync(path.join(__dirname, '../includes/functions.php'), 'utf8');

  expect(callback).toContain('if ($isNewLocalUser)');
  expect(callback).toContain("'Complete your profile'");
  expect(callback).toContain('createNotification(');
  expect(helpers).toContain("if ($title === 'Complete your profile')");
  expect(helpers).toContain("$base . 'pages/edit_profile.php'");
  expect(helpers).toContain("return 'Profile Setup';");
});

test('profile email verification status is only rendered for the owner or an admin', async () => {
  const profile = fs.readFileSync(path.join(__dirname, '../pages/profile.php'), 'utf8');

  expect(profile).toContain('preferred_language, is_verified, university_id');
  expect(profile).toMatch(/<\?php if \(\$isSelf \|\| isAdmin\(\)\): \?>\s*<div class="info-item">\s*<span class="info-label">Status<\/span>/);
  expect(profile).toContain("'Email verified' : 'Email not verified'");
  expect(profile).not.toContain('Verified Student');
});
