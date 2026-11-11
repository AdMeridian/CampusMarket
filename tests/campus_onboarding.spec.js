const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('Campus selection and OAuth onboarding are properly integrated', async () => {
  const en = JSON.parse(fs.readFileSync(path.join(__dirname, '../public/lang/en.extra.json'), 'utf8'));
  const tr = JSON.parse(fs.readFileSync(path.join(__dirname, '../public/lang/tr.extra.json'), 'utf8'));

  expect(en['profile.university_required']).toBeTruthy();
  expect(en['profile.select_campus_title']).toBeTruthy();
  expect(en['profile.select_campus_btn']).toBeTruthy();
  expect(en['profile.skip_for_now']).toBeTruthy();

  expect(tr['profile.university_required']).toBeTruthy();
  expect(tr['profile.select_campus_title']).toBeTruthy();
  expect(tr['profile.select_campus_btn']).toBeTruthy();
  expect(tr['profile.skip_for_now']).toBeTruthy();

  const register = fs.readFileSync(path.join(__dirname, '../pages/register.php'), 'utf8');
  expect(register).toContain('lookupUniversityByEmail');
  expect(register).toContain('profile.university_required');
  expect(register).toContain('data-domain=');

  const verifyEmail = fs.readFileSync(path.join(__dirname, '../pages/verify_email.php'), 'utf8');
  expect(verifyEmail).toContain('lookupUniversityByEmail');
  expect(verifyEmail).toContain('select_campus.php');

  const selectCampus = fs.readFileSync(path.join(__dirname, '../pages/select_campus.php'), 'utf8');
  expect(selectCampus).toContain('requireLogin()');
  expect(selectCampus).toContain('getNorthernCyprusUniversities($pdo)');
  expect(selectCampus).toContain('other_university_name');
  expect(selectCampus).toContain('skip=1');
  expect(selectCampus).toContain("\$_SESSION['university_id'] = \$uniId;");

  const functionsPhp = fs.readFileSync(path.join(__dirname, '../includes/functions.php'), 'utf8');
  expect(functionsPhp).toContain('function userHasCampusAffiliation');

  const announcementsPhp = fs.readFileSync(path.join(__dirname, '../includes/announcements.php'), 'utf8');
  expect(announcementsPhp).toContain('userHasCampusAffiliation');

  const adminAnnouncements = fs.readFileSync(path.join(__dirname, '../admin/announcements.php'), 'utf8');
  expect(adminAnnouncements).toContain("targetRole === 'no_campus'");
  expect(adminAnnouncements).toContain('university_id IS NULL');

  const migration = fs.readFileSync(
    path.join(__dirname, '../supabase/migrations/20261103140000_fix_get_university_and_backfill.sql'),
    'utf8'
  );
  expect(migration).toContain('CREATE OR REPLACE FUNCTION public.get_university_by_email');
  expect(migration).toContain('UPDATE public.users');
});

