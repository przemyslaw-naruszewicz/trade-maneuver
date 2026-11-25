import { expect, test } from '@playwright/test';
import { expectToMatchMockup } from '../support/mockup.js';
import { pageTheme, storeTheme } from '../support/operator.js';

// start.html also shows elements of later tasks, so only the elements this task builds are compared.
const sharedElements = {
    'brand': '.brand',
    'Start navigation entry': '.nav-link[aria-current="page"]',
    'theme switch': '[data-theme-toggle]',
    'logout action': '.btn-quiet:has-text("Log out")',
    'page title': '.page-title',
};

for (const theme of ['light', 'dark']) {
    for (const [element, selector] of Object.entries(sharedElements)) {
        test(`the start page's ${element} matches start.html in the ${theme} theme`, async ({ page }) => {
            await storeTheme(page, theme);
            await page.goto('/');

            await expectToMatchMockup(page, 'start.html', { theme, selector });
        });
    }
}

test('the theme switch changes the theme, and the choice survives a reload', async ({ page }) => {
    await page.goto('/');
    await expect(pageTheme(page)).toHaveAttribute('data-theme', 'light');

    await page.getByRole('button', { name: 'Switch theme' }).click();
    await expect(pageTheme(page)).toHaveAttribute('data-theme', 'dark');

    await page.reload();
    await expect(pageTheme(page)).toHaveAttribute('data-theme', 'dark');
});
