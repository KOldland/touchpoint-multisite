const { test, expect } = require('@playwright/test');

const inviteToken = 'invite_token_browser_001';
const inviteEmail = 'invitee@example.com';

test('quote club invite link accepts and clears invite params', async ({ page }) => {
  let acceptCalls = 0;

  await page.route('**/wp-json/khm/v1/sponsor/invite/accept', async (route) => {
    acceptCalls += 1;
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, user_id: 707, sponsor_id: 22 }),
    });
  });

  await page.goto(`/tests/UI/quoteclub_harness.html?khm_sponsor_invite=${inviteToken}&khm_sponsor_invite_email=${encodeURIComponent(inviteEmail)}`);

  const quoteClubRoot = page.locator('.khm-quoteclub');
  await expect(quoteClubRoot).toBeVisible();

  const hasScriptConfig = await page.evaluate(() => !!window.khmQuoteClub && !!window.khmQuoteClub.sponsorRestUrl);
  expect(hasScriptConfig).toBeTruthy();

  const statusCard = page.locator('.khm-quoteclub-invite-status');
  await expect(statusCard).toContainText('Sponsor invite accepted.');
  await expect.poll(() => acceptCalls).toBeGreaterThan(0);

  await expect.poll(() => {
    const url = new URL(page.url());
    return url.searchParams.has('khm_sponsor_invite') || url.searchParams.has('khm_sponsor_invite_email');
  }).toBeFalsy();
});

test('quote club invite shows retry and succeeds on retry after transient failure', async ({ page }) => {
  let acceptCalls = 0;

  await page.route('**/wp-json/khm/v1/sponsor/invite/accept', async (route) => {
    acceptCalls += 1;

    if (acceptCalls === 1) {
      await route.fulfill({
        status: 500,
        contentType: 'application/json',
        body: JSON.stringify({ success: false, error: 'invite_in_progress' }),
      });
      return;
    }

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ success: true, user_id: 707, sponsor_id: 22 }),
    });
  });

  await page.goto(`/tests/UI/quoteclub_harness.html?khm_sponsor_invite=${inviteToken}&khm_sponsor_invite_email=${encodeURIComponent(inviteEmail)}`);

  const quoteClubRoot = page.locator('.khm-quoteclub');
  await expect(quoteClubRoot).toBeVisible();

  const hasScriptConfig = await page.evaluate(() => !!window.khmQuoteClub && !!window.khmQuoteClub.sponsorRestUrl);
  expect(hasScriptConfig).toBeTruthy();

  const statusCard = page.locator('.khm-quoteclub-invite-status');
  const retryButton = page.locator('.khm-invite-retry-btn');

  await expect(statusCard).toContainText('Invite acceptance failed.');
  await expect(retryButton).toBeVisible();

  await retryButton.click();

  await expect.poll(() => acceptCalls).toBeGreaterThan(1);
  await expect(statusCard).toContainText('Sponsor invite accepted.');
});
