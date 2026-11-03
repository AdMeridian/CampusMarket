const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('platform announcement renders properly on mobile viewport without squishing content', async ({ page }) => {
  // Mobile viewport matching user's phone (~384x824)
  await page.setViewportSize({ width: 384, height: 824 });

  const css = fs.readFileSync('public/css/style.css', 'utf8');

  await page.setContent(`
    <!doctype html>
    <html>
    <head>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>${css}</style>
      <style>
        body { margin: 0; background: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
      </style>
    </head>
    <body>
      <div class="platform-announcements-wrapper" id="platformAnnouncementsWrapper">
        <div class="container">
          <!-- Announcement 1: Services Live -->
          <div class="platform-announcement platform-announcement--success" id="announcement-item-1" role="alert">
            <div class="platform-announcement__icon">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            </div>
            <div class="platform-announcement__body">
              <span class="platform-announcement__title">Campus Services is Now Live!</span>
              <span class="platform-announcement__message">Hire fellow students or offer your skills for tutoring, tech support, moving, design, and campus gigs.</span>
            </div>
            <a href="/services" class="platform-announcement__cta">Explore Services ›</a>
            <button type="button" class="platform-announcement__dismiss" aria-label="Dismiss">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
          </div>

          <!-- Announcement 2: Campus Nudge -->
          <div class="platform-announcement platform-announcement--info" id="campus-nudge-item" role="alert">
            <div class="platform-announcement__icon">
              <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            </div>
            <div class="platform-announcement__body">
              <span class="platform-announcement__title">Set your campus</span>
              <span class="platform-announcement__message">Let other students know where you study — it helps you discover listings near your university.</span>
            </div>
            <a href="/pages/select_campus.php" class="platform-announcement__cta">Add campus ›</a>
            <button type="button" class="platform-announcement__dismiss" aria-label="Dismiss">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
          </div>
        </div>
      </div>
    </body>
    </html>
  `);

  const card = page.locator('#announcement-item-1');
  const body = card.locator('.platform-announcement__body');
  const cta = card.locator('.platform-announcement__cta');
  const dismiss = card.locator('.platform-announcement__dismiss');

  await expect(card).toBeVisible();
  await expect(body).toBeVisible();
  await expect(cta).toBeVisible();

  const cardBox = await card.boundingBox();
  const bodyBox = await body.boundingBox();
  const ctaBox = await cta.boundingBox();
  const dismissBox = await dismiss.boundingBox();

  // The card height on mobile should be compact (< 220px), NOT squished into 400-500px
  expect(cardBox.height).toBeLessThan(220);

  // The body should take almost the entire card width
  expect(bodyBox.width).toBeGreaterThan(250);

  // The CTA should be placed below the title/message on mobile, not squishing beside it
  expect(ctaBox.y).toBeGreaterThan(bodyBox.y + 20);

  // The dismiss button should be at the top right
  expect(dismissBox.x).toBeGreaterThan(bodyBox.x + bodyBox.width - 35);

  // Save screenshot to artifact directory
  const artifactDir = "C:\\Users\\Carl\\.gemini\\antigravity\\brain\\0ab160d5-7208-4ffa-a4a2-04e56e0bb32d";
  await page.screenshot({ path: path.join(artifactDir, 'announcement_mobile_preview.png'), fullPage: false });
});
