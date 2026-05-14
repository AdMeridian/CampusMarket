const { test, expect } = require('@playwright/test');
const fs = require('fs');

test('user dropdown click toggles and closes correctly', async ({ page }) => {
  const script = fs.readFileSync('public/js/mobile-menu.js', 'utf8');

  await page.setContent(`
    <!doctype html>
    <html><body>
      <button id="mobile-menu-btn"><svg></svg></button>
      <div id="nav-links"></div>
      <div class="user-dropdown">
        <button type="button" class="user-dropdown-btn" aria-expanded="false" aria-haspopup="true">
          <span>Account</span>
        </button>
        <div class="user-dropdown-content">
          <a href="#">My Orders</a>
        </div>
      </div>
      <div id="outside">Outside</div>
    </body></html>
  `);

  await page.addScriptTag({ content: script });
  await page.evaluate(() => {
    document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true }));
  });

  const dropdown = page.locator('.user-dropdown');
  const button = page.locator('.user-dropdown-btn');

  await expect(dropdown).not.toHaveClass(/active/);
  await expect(button).toHaveAttribute('aria-expanded', 'false');

  await button.click();
  await expect(dropdown).toHaveClass(/active/);
  await expect(button).toHaveAttribute('aria-expanded', 'true');

  await page.locator('#outside').click();
  await expect(dropdown).not.toHaveClass(/active/);
  await expect(button).toHaveAttribute('aria-expanded', 'false');

  await button.click();
  await page.keyboard.press('Escape');
  await expect(dropdown).not.toHaveClass(/active/);
  await expect(button).toHaveAttribute('aria-expanded', 'false');
});
