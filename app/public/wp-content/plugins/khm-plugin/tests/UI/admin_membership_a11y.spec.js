const { test, expect } = require('@playwright/test');

async function runAxe(page) {
  await page.addScriptTag({ path: require.resolve('axe-core') });
  const results = await page.evaluate(async () => {
    return await axe.run(document, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa'] }
    });
  });
  return results.violations.filter(v => v.impact === 'serious' || v.impact === 'critical');
}

test('membership reports/admin page accessibility smoke', async ({ page }) => {
  await page.goto('/wp-admin/admin.php?page=khm-membership-reports');

  const loggedOutMarker = page.locator('#loginform, form[name="loginform"]');
  test.skip((await loggedOutMarker.count()) > 0, 'Admin auth required; run in authenticated staging session.');

  const severe = await runAxe(page);
  expect(severe.length).toBe(0);

  const exportButton = page.locator('button:has-text("Export"), input[type="submit"][value*="Export"], a:has-text("Export")');
  test.skip((await exportButton.count()) === 0, 'Export control not present in this admin view variant.');
  await expect(exportButton.first()).toBeVisible();
});
