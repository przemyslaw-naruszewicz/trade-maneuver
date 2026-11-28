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

// The seeded orders give a count of 3. The link's note names actions of later tasks, so the link is compared without it.
const ordersElements = {
    'Orders navigation entry with its count': '.nav-link:has-text("Orders")',
    'number of orders awaiting approval': '.tile:has-text("Awaiting approval")',
    'label of the link to the orders screen': '.tile-link .tile-label',
    'action of the link to the orders screen': '.tile-link .tile-action',
};

for (const theme of ['light', 'dark']) {
    for (const [element, selector] of Object.entries({ ...sharedElements, ...ordersElements })) {
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

test('after 30 seconds, the start page and the navigation count each run an update without a page reload', async ({ page }) => {
    const updatedComponents = new Set();
    page.on('response', (response) => {
        if (!/\/livewire[^/]*\/update$/.test(response.url()) || !response.ok()) {
            return;
        }
        for (const component of response.request().postDataJSON().components) {
            updatedComponents.add(JSON.parse(component.snapshot).memo.name);
        }
    });
    await page.clock.install();
    await page.goto('/');
    await page.evaluate(() => {
        window.loadedOnce = true;
    });

    await page.clock.runFor(30 * 1000);

    await expect.poll(() => [...updatedComponents].sort()).toEqual(['trading.orders-awaiting-approval-count', 'trading.start']);
    expect(await page.evaluate(() => window.loadedOnce)).toBe(true);
});
