import { expect, test } from '@playwright/test';
import { expectToMatchMockup } from '../support/mockup.js';

// This test approves the seeded DEMO_B order, so it runs after the other panel tests, which expect it to await
// approval.
const order = (symbol) => `.order-band:has(.order-symbol:text-is("${symbol}"))`;
const approveForm = (symbol) => `form[aria-label="Approve the order for ${symbol}"]`;
const livewireUpdate = /\/livewire[^/]*\/update$/;

async function approveInItsForm(page, symbol) {
    const update = page.waitForResponse(livewireUpdate);
    await page.locator(approveForm(symbol)).getByRole('button', { name: 'Approve order' }).click();
    await update;
}

test('when DEMO_B is approved in two pages, the second says that it was already decided and lists it as approved', async ({ context }) => {
    const first = await context.newPage();
    const second = await context.newPage();
    for (const page of [first, second]) {
        await page.goto('/orders');
        await page.locator(order('DEMO_B')).getByRole('button', { name: 'Approve', exact: true }).click();
        await expect(page.locator(approveForm('DEMO_B'))).toBeVisible();
    }

    await approveInItsForm(first, 'DEMO_B');
    await approveInItsForm(second, 'DEMO_B');

    const notice = second.locator('.notice[role="alert"]');
    await expect(notice).toHaveText('This order was already decided in another tab, so nothing changed. The list below shows its current status.');
    await expect(second.locator(order('DEMO_B'))).toHaveCount(0);
    await expect(second.locator('tr:has(.status-approved):has-text("DEMO_B") .status')).toHaveText('Approved');
    await expectToMatchMockup(second, 'order-actions.html', { theme: 'light', selector: '.notice[role="alert"]' });

    await second.getByRole('button', { name: 'Switch theme' }).click();
    await expect(second.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expectToMatchMockup(second, 'order-actions.html', { theme: 'dark', selector: '.notice[role="alert"]' });
});
