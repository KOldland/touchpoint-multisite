const { test, expect } = require('@playwright/test');

test('landing success markup snapshot is stable', async ({ page }) => {
  await page.goto('/?session_id=cs_test_success_001');

  const successRoot = page.locator('.khm-success-page, .khm-success-modal');
  await expect(successRoot.first()).toBeVisible();

  await expect(successRoot.first()).toHaveScreenshot('landing-success.png');
});
