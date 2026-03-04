const { test, expect } = require('@playwright/test');

test('landing success modal/page has no critical axe violations', async ({ page }) => {
  await page.goto('/?session_id=cs_test_success_001');
  await page.addScriptTag({ path: require.resolve('axe-core') });

  const results = await page.evaluate(async () => {
    return await axe.run(document, {
      runOnly: {
        type: 'tag',
        values: ['wcag2a', 'wcag2aa']
      }
    });
  });

  const seriousOrCritical = results.violations.filter(v =>
    v.impact === 'serious' || v.impact === 'critical'
  );

  expect(seriousOrCritical.length).toBe(0);
});
