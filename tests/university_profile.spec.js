const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('signup and profile editing offer a directory choice and an Other university option', async () => {
  const register = fs.readFileSync(path.join(__dirname, '../pages/register.php'), 'utf8');
  const editProfile = fs.readFileSync(path.join(__dirname, '../pages/edit_profile.php'), 'utf8');

  for (const source of [register, editProfile]) {
    expect(source).toContain('name="university_id"');
    expect(source).toContain('value="other"');
    expect(source).toContain('name="other_university_name"');
    expect(source).toContain('getAvailableUniversities($pdo)');
  }
  expect(editProfile).toContain("require_once __DIR__ . '/../includes/functions_member2.php';");
  expect(register).toContain('university_id, other_university_name');
  expect(register).toContain("':other_university_name' => $otherUniversityName");
  expect(editProfile).toContain('university_id = :university_id');
  expect(editProfile).toContain('other_university_name = :other_university_name');
});

test('profiles display explicitly provided university and admin stats prefer it to email inference', async () => {
  const profile = fs.readFileSync(path.join(__dirname, '../pages/profile.php'), 'utf8');
  const adminUsers = fs.readFileSync(path.join(__dirname, '../admin/users.php'), 'utf8');

  expect(profile).toContain("$user['other_university_name']");
  expect(profile).toContain("__('profile.university_attended')");
  expect(profile).toContain("require_once __DIR__ . '/../includes/functions_member2.php';");
  expect(adminUsers.indexOf("if ($universityId > 0 && isset($universitiesById[$universityId]))"))
    .toBeLessThan(adminUsers.indexOf("getUniversityInfoFromEmail($user['email'] ?? '')"));
  expect(adminUsers).toContain("'source' => 'self_reported'");
});

test('university profile migration adds the custom Other-university field', async () => {
  const migration = fs.readFileSync(
    path.join(__dirname, '../supabase/migrations/20261026183438_add_user_other_university.sql'),
    'utf8'
  );

  expect(migration).toMatch(/ALTER TABLE public\.users[\s\S]*ADD COLUMN IF NOT EXISTS other_university_name VARCHAR\(200\) NULL/);
});
