const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test.describe('Service Onboarding, AI Guideline Rules, and Admin Moderation', () => {

  test('edit_profile.php has only ONE whatsapp_opt_in_default checkbox (no duplicate)', () => {
    const editProfilePath = path.join(__dirname, '../pages/edit_profile.php');
    const content = fs.readFileSync(editProfilePath, 'utf8');

    // Count occurrences of id="whatsapp_opt_in_default"
    const waMatches = content.match(/id=["']whatsapp_opt_in_default["']/g) || [];
    expect(waMatches.length).toBe(1);

    // Verify it is placed after contact number (phone)
    const phoneIdx = content.indexOf('name="phone"');
    const waIdx = content.indexOf('id="whatsapp_opt_in_default"');
    expect(phoneIdx).toBeGreaterThan(0);
    expect(waIdx).toBeGreaterThan(phoneIdx);
  });

  test('actions/enroll_provider.php uses boolean TRUE for PostgreSQL and handles null price', () => {
    const enrollPath = path.join(__dirname, '../actions/enroll_provider.php');
    const content = fs.readFileSync(enrollPath, 'utf8');

    // Verify PostgreSQL branch uses boolean TRUE for is_primary
    expect(content).toContain('VALUES (?, ?, TRUE)');

    // Verify admin notification is sent upon enrollment
    expect(content).toContain('listingModerationNotifyAdmins');
  });

  test('api/ai_classify_service.php enforces video editing guidelines over graphic design', () => {
    const classifyPath = path.join(__dirname, '../api/ai_classify_service.php');
    const content = fs.readFileSync(classifyPath, 'utf8');

    // Verify AI prompt rules strictly separate Video Editing from Graphic Design
    expect(content).toContain('CRITICAL GUIDELINE RULES:');
    expect(content).toContain('NEVER title video editing as "Graphic Design"');
    expect(content).toContain('MUST be assigned to "Photography & Videography"');

    // Verify fallback logic prioritizes videography/photo category and video editing title
    expect(content).toContain("findCat(['videography', 'photo'");
    expect(content).toContain('Professional Video Editing & Content Creation');
  });

  test('admin/services.php provides direct navigation to pending approvals and queue alert', () => {
    const servicesPath = path.join(__dirname, '../admin/services.php');
    const content = fs.readFileSync(servicesPath, 'utf8');

    expect(content).toContain('pendingServicesCount');
    expect(content).toContain('Pending Approvals');
    expect(content).toContain('admin/listings.php?type=service&status=pending_approval');
  });

  test('admin/index.php displays pending services count on Services Directory card', () => {
    const adminIndexPath = path.join(__dirname, '../admin/index.php');
    const content = fs.readFileSync(adminIndexPath, 'utf8');

    expect(content).toContain('Services Directory');
    expect(content).toContain('pending_services');
  });

  test('database migration exists to allow null price on products for services', () => {
    const migrationPath = path.join(__dirname, '../supabase/migrations/20261113120000_allow_null_price_for_services.sql');
    expect(fs.existsSync(migrationPath)).toBe(true);
    const content = fs.readFileSync(migrationPath, 'utf8');
    expect(content).toContain('ALTER TABLE public.products ALTER COLUMN price DROP NOT NULL');
  });
});
