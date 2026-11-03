const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('platform announcement renders properly as a carousel when multiple announcements exist', async ({ page }) => {
  // Mobile viewport (~384x824)
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
          <div class="platform-announcements-carousel" id="platformAnnouncementsCarousel" role="region" aria-label="Platform announcements">
            <div class="platform-announcements-slides" id="platformAnnouncementsSlides">
              <!-- Announcement 1 -->
              <div class="platform-announcement platform-announcement--success is-active" id="announcement-item-1" role="alert">
                <div class="platform-announcement__icon">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                </div>
                <div class="platform-announcement__body">
                  <span class="platform-announcement__title">Campus Services is Now Live!</span>
                  <span class="platform-announcement__message">Hire fellow students or offer your skills for tutoring, tech support, moving, design, and campus gigs.</span>
                </div>
                <a href="/services" class="platform-announcement__cta">Explore Services ›</a>
                <button type="button" class="platform-announcement__dismiss" onclick="dismissPlatformAnnouncement(1)" aria-label="Dismiss announcement">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
              </div>

              <!-- Announcement 2 -->
              <div class="platform-announcement platform-announcement--info" id="campus-nudge-item" role="alert">
                <div class="platform-announcement__icon">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                </div>
                <div class="platform-announcement__body">
                  <span class="platform-announcement__title">Set your campus</span>
                  <span class="platform-announcement__message">Let other students know where you study — it helps you discover listings near your university.</span>
                </div>
                <a href="/pages/select_campus.php" class="platform-announcement__cta">Add campus ›</a>
                <button type="button" class="platform-announcement__dismiss" onclick="dismissCampusNudge()" aria-label="Dismiss">
                  <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
              </div>
            </div>

            <!-- Pager bar -->
            <div class="platform-announcements-pager" id="platformAnnouncementsPager">
              <button type="button" class="announcement-pager-btn" onclick="prevPlatformAnnouncement()" aria-label="Previous announcement">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"></polyline></svg>
              </button>
              <div class="announcement-pager-dots" id="announcementPagerDots">
                <button type="button" class="announcement-pager-dot is-active"></button>
                <button type="button" class="announcement-pager-dot"></button>
              </div>
              <span class="announcement-pager-count" id="announcementPagerCount">1 / 2</span>
              <button type="button" class="announcement-pager-btn" onclick="nextPlatformAnnouncement()" aria-label="Next announcement">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </body>
    </html>
  `);

  // Verify only ONE announcement is visible at a time
  const card1 = page.locator('#announcement-item-1');
  const card2 = page.locator('#campus-nudge-item');
  const pager = page.locator('#platformAnnouncementsPager');
  const wrapper = page.locator('#platformAnnouncementsWrapper');

  await expect(card1).toBeVisible();
  await expect(card2).toBeHidden();
  await expect(pager).toBeVisible();

  // Total height of the wrapper must be compact (< 220px) even with 2 announcements present
  const wrapperBox = await wrapper.boundingBox();
  expect(wrapperBox.height).toBeLessThan(220);

  // Take screenshot for visual verification
  const artifactDir = "C:\\Users\\Carl\\.gemini\\antigravity\\brain\\0ab160d5-7208-4ffa-a4a2-04e56e0bb32d";
  await page.screenshot({ path: path.join(artifactDir, 'announcement_carousel_preview.png'), fullPage: false });
});
