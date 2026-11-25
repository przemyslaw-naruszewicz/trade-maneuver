import { expect, test } from '@playwright/test';

test('the health check of the e2e application answers with status 200', async ({ page }) => {
    const response = await page.goto('/up');

    expect(response?.status()).toBe(200);
});
