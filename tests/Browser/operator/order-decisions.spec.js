import { expect, test } from '@playwright/test';
import { expectToMatchMockup } from '../support/mockup.js';
import { storeTheme } from '../support/operator.js';

// BrowserTestOrderSeeder creates the orders of orders.html: DEMO_A, DEMO_B, and DEMO_C await approval, a further DEMO_A
// order is approved, and a further DEMO_C order is refused. These tests open forms and type in them, which changes no
// order; a zero-quantity approval is refused and changes nothing either.
const order = (symbol) => `.order-band:has(.order-symbol:text-is("${symbol}"))`;
const approveForm = (symbol) => `form[aria-label="Approve the order for ${symbol}"]`;
const refuseForm = (symbol) => `form[aria-label="Refuse the order for ${symbol}"]`;
const livewireUpdate = /\/livewire[^/]*\/update$/;

async function openApproval(page, symbol) {
    await page.locator(order(symbol)).getByRole('button', { name: 'Approve', exact: true }).click();
    await expect(page.locator(approveForm(symbol))).toBeVisible();
}

async function openRefusal(page, symbol) {
    await page.locator(order(symbol)).getByRole('button', { name: 'Refuse', exact: true }).click();
    await expect(page.locator(refuseForm(symbol))).toBeVisible();
}

for (const theme of ['light', 'dark']) {
    test(`the approve form of DEMO_A with 10.60 EUR typed matches order-actions.html in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.goto('/orders');
        await openApproval(page, 'DEMO_A');
        const limitPrice = page.locator(approveForm('DEMO_A')).getByLabel('New limit price, optional');
        await limitPrice.fill('10.60');
        await limitPrice.blur();

        // The mockup's preview of the recalculated quantity and cost is not built, so only the field and the actions
        // are compared; the field keeps the width it has beside the preview.
        await expectToMatchMockup(page, 'order-actions.html', { theme, selector: `${approveForm('DEMO_A')} > div:first-child > div:first-child` });
        await expectToMatchMockup(page, 'order-actions.html', { theme, selector: `${approveForm('DEMO_A')} > div:last-child` });
    });

    test(`the approve form of DEMO_B shows the zero-quantity message after 250.00 EUR, as order-actions.html does in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.goto('/orders');
        await openApproval(page, 'DEMO_B');
        await page.locator(approveForm('DEMO_B')).getByLabel('New limit price, optional').fill('250.00');

        await page.locator(approveForm('DEMO_B')).getByRole('button', { name: 'Approve order' }).click();

        const message = page.locator(`${approveForm('DEMO_B')} .field-error`);
        await expect(message).toHaveText('This limit price leaves a quantity of zero, so the order stays awaiting approval. Enter a lower limit price.');
        await expect(page.locator(order('DEMO_B')).locator('.status')).toHaveText('Awaiting approval');
        await expectToMatchMockup(page, 'order-actions.html', { theme, selector: `${approveForm('DEMO_B')} > div:first-child` });
    });

    test(`the refuse form of DEMO_C matches order-actions.html in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.goto('/orders');
        await openRefusal(page, 'DEMO_C');

        await expectToMatchMockup(page, 'order-actions.html', { theme, selector: `${refuseForm('DEMO_C')} > div:first-child` });
        await expectToMatchMockup(page, 'order-actions.html', { theme, selector: `${refuseForm('DEMO_C')} > div:last-child` });
    });

    test(`the Approve and Refuse buttons of DEMO_B match orders.html in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.goto('/orders');

        await expectToMatchMockup(page, 'orders.html', { theme, selector: `${order('DEMO_B')} > div:nth-child(3) > div:last-child` });
    });

    test(`the status badges and execution progress of the approved DEMO_A and the refused DEMO_C rows match orders.html in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.goto('/orders');

        // The table cells take their size from the mockup's other rows and columns, so the badges and the meters are
        // compared, each pinned to the same position on both pages, and the progress texts are checked.
        await expect(page.locator('tr:has(.status-approved) > td:nth-child(1)')).toHaveText('DEMO_ADEMO');
        await expect(page.locator('tr:has(.status-approved) > td:nth-child(3)')).toHaveText('None, 0 of 18');
        await expect(page.locator('tr:has(.status-refused) > td:nth-child(1)')).toHaveText('DEMO_CDEMO');
        await expect(page.locator('tr:has(.status-refused) > td:nth-child(3)')).toHaveText('None, 0 of 8');
        for (const selector of ['.status-approved', 'tr:has(.status-approved) .meter', '.status-refused', 'tr:has(.status-refused) .meter']) {
            await expectToMatchMockup(page, 'orders.html', { theme, selector, pinned: true });
        }
    });
}

test('after 30 seconds, an update keeps the open approve and refuse forms with the typed limit price and note', async ({ page }) => {
    await page.clock.install();
    await page.goto('/orders');
    await openApproval(page, 'DEMO_A');
    await page.locator(approveForm('DEMO_A')).getByLabel('New limit price, optional').fill('10.60');
    await openRefusal(page, 'DEMO_C');
    await page.locator(refuseForm('DEMO_C')).getByLabel('Note, optional').fill('The spread looks too wide today.');
    const lastUpdate = page.locator('.live-status');
    const lastUpdateBefore = await lastUpdate.textContent();
    // The server renders the last update time to the second, and Playwright's clock does not move the server's.
    await page.waitForTimeout(1100);

    const update = page.waitForResponse(livewireUpdate);
    await page.clock.runFor(30 * 1000);
    await update;

    await expect(lastUpdate).not.toHaveText(lastUpdateBefore);
    await expect(page.locator(approveForm('DEMO_A')).getByLabel('New limit price, optional')).toHaveValue('10.60');
    await expect(page.locator(refuseForm('DEMO_C')).getByLabel('Note, optional')).toHaveValue('The spread looks too wide today.');
});
