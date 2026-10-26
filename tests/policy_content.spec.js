const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('English and Turkish policies describe open signup and self-reported campus affiliation', async () => {
  const policies = ['en', 'tr'].map((language) => JSON.parse(
    fs.readFileSync(path.join(__dirname, `../public/lang/${language}.extra.json`), 'utf8')
  ));

  for (const policy of policies) {
    expect(policy['policy.terms.s2_body']).toBeTruthy();
    expect(policy['policy.terms.s3_body']).toBeTruthy();
    expect(policy['policy.privacy.s1_li_account']).toBeTruthy();
    expect(policy['policy.privacy.s7_body']).toBeTruthy();
    expect(policy['policy.safety.s4_body']).toBeTruthy();
    expect(policy['policy.last_updated_date']).toBeTruthy();
  }

  expect(policies[0]['policy.terms.s2_body']).toContain('personal or university email');
  expect(policies[0]['policy.terms.s3_body']).toContain('self-reported');
  expect(policies[0]['policy.privacy.s1_li_account']).toContain('we do not ask you to submit a student ID');
  expect(policies[0]['policy.privacy.s7_body']).toContain('not verified by CampusMarket');
  expect(policies[0]['policy.safety.s4_body']).toContain('does not prove someone');
  expect(policies[0]['policy.rules.s5_li_4']).toContain('between users');
  expect(policies[0]['policy.last_updated_date']).toBe('October 26, 2026');

  expect(policies[1]['policy.terms.s2_body']).toContain('kişisel veya üniversite e-posta');
  expect(policies[1]['policy.terms.s3_body']).toContain('kullanıcının beyanına dayanır');
  expect(policies[1]['policy.privacy.s1_li_account']).toContain('öğrenci numarası istemiyoruz');
  expect(policies[1]['policy.privacy.s7_body']).toContain('CampusMarket tarafından doğrulanmaz');
  expect(policies[1]['policy.safety.s4_body']).toContain('öğrencilik durumunu');
  expect(policies[1]['policy.rules.s5_li_4']).toContain('kullanıcılar arasındaki');
  expect(policies[1]['policy.last_updated_date']).toBe('26 Ekim 2026');
});
