const { test, expect } = require('@playwright/test');

async function runAxe(page) {
  await page.addScriptTag({ path: require.resolve('axe-core') });
  const results = await page.evaluate(async () => {
    return await axe.run(document, {
      runOnly: {
        type: 'tag',
        values: ['wcag2a', 'wcag2aa']
      }
    });
  });

  return results.violations.filter(v => v.impact === 'serious' || v.impact === 'critical');
}

test('landing form exposes consent checkbox accessibly', async ({ page }) => {
  await page.goto('/');

  const consentControl = page.locator('input[type="checkbox"][name*="consent" i], input[type="checkbox"][id*="consent" i]');
  test.skip((await consentControl.count()) === 0, 'Consent checkbox not present on current page variant.');

  await expect(consentControl.first()).toBeVisible();
  const seriousOrCritical = await runAxe(page);
  expect(seriousOrCritical.length).toBe(0);
});

test('landing success modal/page has no critical axe violations', async ({ page }) => {
  await page.goto('/?session_id=cs_test_success_001');
  const seriousOrCritical = await runAxe(page);

  expect(seriousOrCritical.length).toBe(0);
});

test('success surface contains aria-live and focusable confirmation region', async ({ page }) => {
  await page.goto('/?session_id=cs_test_success_001');

  const successRoot = page.locator('.khm-success-page, .khm-success-modal, [data-khm-success]');
  test.skip((await successRoot.count()) === 0, 'Success UI surface not available in this environment.');

  await expect(successRoot.first()).toBeVisible();

  const liveRegion = page.locator('[aria-live]');
  await expect(liveRegion.first()).toBeVisible();

  const focusables = successRoot.first().locator('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
  expect(await focusables.count()).toBeGreaterThan(0);
});
