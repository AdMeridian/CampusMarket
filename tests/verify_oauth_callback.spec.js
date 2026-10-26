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
