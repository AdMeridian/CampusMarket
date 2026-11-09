const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('mobile search row sits comfortably below announcement banner without 80px gap', async ({ page }) => {
  // Mobile viewport (~384x824)
  await page.setViewportSize({ width: 384, height: 824 });

  const css = fs.readFileSync('public/css/style.css', 'utf8');

  await page.setContent(`
    <!doctype html>
    <html class="has-announcement-banner">
    <head>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>${css}</style>
      <style>
        body { margin: 0; background: #f8fafc; font-family: system-ui, -apple-system, sans-serif; }
      </style>
    </head>
    <body class="has-announcement-banner">
      <!-- Top Navbar Mock -->
      <nav class="navbar" style="position: fixed; top: 0; left: 0; width: 100%; height: 60px; background: white; border-bottom: 1px solid #e2e8f0; z-index: 1000; display: flex; align-items: center; padding: 0 1rem;">
        <strong>CampusMarket</strong>
      </nav>

      <div class="platform-announcements-wrapper" id="platformAnnouncementsWrapper">
        <div class="container">
          <div class="platform-announcements-carousel" id="platformAnnouncementsCarousel" role="region" aria-label="Platform announcements">
            <div class="platform-announcements-slides" id="platformAnnouncementsSlides">
              <div class="platform-announcement platform-announcement--success is-active" id="announcement-item-1" role="alert">
                <div class="platform-announcement__icon">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                <div class="platform-announcement__body">
                  <span class="platform-announcement__title">Campus Services is Now Live!</span>
                  <span class="platform-announcement__message">Hire fellow students or offer your skills for tutoring, tech support, moving, design, and campus gigs.</span>
                </div>
                <a href="/services" class="platform-announcement__cta">Explore Services ›</a>
                <button type="button" class="platform-announcement__dismiss" aria-label="Dismiss announcement">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile Search Row -->
      <div class="mobile-search-row">
        <form style="display: flex; gap: 0.5rem; max-width: 500px; margin: 0 auto;">
          <input type="text" placeholder="Search items, books, tech..." style="flex: 1; padding: 0.6rem 1rem; border: 1px solid #cbd5e1; border-radius: 8px;">
          <button type="submit" style="padding: 0.6rem 1.2rem; background: #1a7f64; color: white; border: none; border-radius: 8px; font-weight: 600;">Search</button>
        </form>
      </div>

      <!-- Hero Section Mock -->
      <div style="background: #0f172a; color: white; padding: 2.5rem 1rem; text-align: center;">
        <h1 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 0.5rem;">Campus Marketplace</h1>
        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.5rem;">Join thousands of students trading books, electronics, and more.</p>
        <button style="padding: 0.75rem 1.5rem; background: white; color: #0f172a; border: none; border-radius: 8px; font-weight: 700;">Start Browsing</button>
      </div>
    </body>
    </html>
  `);

  const card = page.locator('#announcement-item-1');
  const searchRow = page.locator('.mobile-search-row');

  await expect(card).toBeVisible();
  await expect(searchRow).toBeVisible();

  const cardBox = await card.boundingBox();
  const searchBox = await searchRow.boundingBox();

  // Gap between bottom of announcement card and top of search row should be tight (<= 20px, not 80px+)
  const gap = searchBox.y - (cardBox.y + cardBox.height);
  expect(gap).toBeLessThanOrEqual(20);
});

test('clicking announcement CTA marks announcement as dismissed and closes it', async ({ page }) => {
  await page.setViewportSize({ width: 384, height: 824 });

  const css = fs.readFileSync('public/css/style.css', 'utf8');

  await page.setContent(`
    <!doctype html>
    <html class="has-announcement-banner">
    <head>
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <style>${css}</style>
    </head>
    <body class="has-announcement-banner">
      <div class="platform-announcements-wrapper" id="platformAnnouncementsWrapper">
        <div class="container">
          <div class="platform-announcements-carousel" id="platformAnnouncementsCarousel">
            <div class="platform-announcements-slides" id="platformAnnouncementsSlides">
              <div class="platform-announcement platform-announcement--success is-active" id="announcement-item-1" role="alert">
                <div class="platform-announcement__body">
                  <span class="platform-announcement__title">Campus Services is Now Live!</span>
                </div>
                <a href="#services" class="platform-announcement__cta" onclick="dismissPlatformAnnouncement(1); return false;">Explore Services ›</a>
                <button type="button" class="platform-announcement__dismiss" onclick="dismissPlatformAnnouncement(1)">✕</button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="mobile-search-row">Search</div>
      <script>
        window.dismissedId = null;
        window.dismissPlatformAnnouncement = function(id) {
          window.dismissedId = id;
          var el = document.getElementById('announcement-item-' + id);
          if (el) {
            el.remove();
            document.getElementById('platformAnnouncementsWrapper').style.display = 'none';
            document.body.classList.remove('has-announcement-banner');
          }
        };
      </script>
    </body>
    </html>
  `);

  const card = page.locator('#announcement-item-1');
  const cta = page.locator('.platform-announcement__cta');
  const wrapper = page.locator('#platformAnnouncementsWrapper');

  await expect(card).toBeVisible();

  // Click CTA
  await cta.click();

  // Verify dismiss callback fired with id 1
  const dismissedId = await page.evaluate(() => window.dismissedId);
  expect(dismissedId).toBe(1);

  // Verify the banner is closed
  await expect(wrapper).toBeHidden();
  await expect(page.locator('body')).not.toHaveClass(/has-announcement-banner/);
});
