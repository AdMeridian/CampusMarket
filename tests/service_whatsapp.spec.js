const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('WhatsApp contact feature is properly integrated across services', async () => {
  // 1. Translations check
  const en = JSON.parse(fs.readFileSync(path.join(__dirname, '../public/lang/en.json'), 'utf8'));
  const tr = JSON.parse(fs.readFileSync(path.join(__dirname, '../public/lang/tr.json'), 'utf8'));

  expect(en['product.chat_whatsapp']).toBe('Chat on WhatsApp');
  expect(en['product.whatsapp_prefill']).toContain('{seller}');
  expect(en['product.whatsapp_prefill']).toContain('{title}');
  expect(en['product.whatsapp_prefill']).toContain('{url}');
  expect(en['product.whatsapp_enable_label']).toBeTruthy();

  expect(tr['product.chat_whatsapp']).toBe('WhatsApp ile Sohbet Et');
  expect(tr['product.whatsapp_prefill']).toContain('{seller}');
  expect(tr['product.whatsapp_prefill']).toContain('{title}');
  expect(tr['product.whatsapp_prefill']).toContain('{url}');
  expect(tr['product.whatsapp_enable_label']).toBeTruthy();

  // 2. Helper functions check
  const functionsPhp = fs.readFileSync(path.join(__dirname, '../includes/functions.php'), 'utf8');
  expect(functionsPhp).toContain('function buildWhatsAppChatUrl');
  expect(functionsPhp).toContain('wa.me');

  // 3. Product page check (desktop & mobile fluidity)
  const productPhp = fs.readFileSync(path.join(__dirname, '../pages/product.php'), 'utf8');
  expect(productPhp).toContain('serviceWhatsAppUrl');
  expect(productPhp).toContain('buildWhatsAppChatUrl');
  expect(productPhp).toContain('btn--whatsapp');
  expect(productPhp).toContain('js-whatsapp-seller');
  // Fluid responsiveness: stack vertically on mobile, side-by-side on sm screens
  expect(productPhp).toContain('flex flex-col sm:flex-row gap-2 w-full');
  expect(productPhp).toContain("contact_method: 'whatsapp'");

  // 4. Provider onboarding and enrollment check
  const onboardPhp = fs.readFileSync(path.join(__dirname, '../pages/provider_onboard.php'), 'utf8');
  expect(onboardPhp).toContain('name="enable_whatsapp"');
  expect(onboardPhp).toContain('name="whatsapp_number"');

  const enrollAction = fs.readFileSync(path.join(__dirname, '../actions/enroll_provider.php'), 'utf8');
  expect(enrollAction).toContain('whatsapp_number');
  expect(enrollAction).toContain('enable_whatsapp');

  // 5. Manage listing check
  const managePhp = fs.readFileSync(path.join(__dirname, '../pages/manage_listing.php'), 'utf8');
  expect(managePhp).toContain('manage_enable_whatsapp');
  expect(managePhp).toContain('name="whatsapp_number"');
  expect(managePhp).toContain('whatsapp_number = :wa_num');

  // 6. CSS styling check
  const styleCss = fs.readFileSync(path.join(__dirname, '../public/css/style.css'), 'utf8');
  expect(styleCss).toContain('.btn--whatsapp');
  expect(styleCss).toContain('#25D366');
  expect(styleCss).toContain('#20bd5a');

  // 7. Migrations check
  const mysqlMigration = fs.readFileSync(
    path.join(__dirname, '../database/migrations/20261109110000_add_product_whatsapp_number.mysql.sql'),
    'utf8'
  );
  expect(mysqlMigration).toContain('ADD COLUMN whatsapp_number');

  const pgMigration = fs.readFileSync(
    path.join(__dirname, '../supabase/migrations/20261109110000_add_product_whatsapp_number.sql'),
    'utf8'
  );
  expect(pgMigration).toContain('ADD COLUMN IF NOT EXISTS whatsapp_number');
});
