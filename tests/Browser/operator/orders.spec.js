import { expect, test } from '@playwright/test';
import { expectToMatchMockup } from '../support/mockup.js';
import { storeTheme } from '../support/operator.js';

// BrowserTestOrderSeeder creates the orders of orders.html. The mockup also shows elements of later tasks and times
// of its own, so only the elements this task builds whose content does not depend on the time of the run are compared.
const order = (symbol) => `.order-band:has-text("${symbol}")`;
const tradeSignalField = (symbol, field) => `${order(symbol)} > div:nth-child(2) .facts > div:nth-child(${field})`;
const orderColumn = (symbol) => `${order(symbol)} > div:nth-child(3)`;

const comparedElements = {
    'page title': '.page-title',
    'Awaiting approval section heading with its count': '#awaiting-approval',
    'DEMO_A symbol heading': `${order('DEMO_A')} .order-symbol`,
    'DEMO_A line with the MIC and the direction': `${order('DEMO_A')} .order-symbol + .note`,
    'DEMO_A trade signal column head': `${order('DEMO_A')} > div:nth-child(2) .column-head`,
    'DEMO_A confidence': tradeSignalField('DEMO_A', 1),
    'DEMO_A horizon': tradeSignalField('DEMO_A', 2),
    'DEMO_A expected move': tradeSignalField('DEMO_A', 3),
    'DEMO_A reference price caption': `${tradeSignalField('DEMO_A', 4)} > dt`,
    'DEMO_A reference price': `${tradeSignalField('DEMO_A', 4)} > .fact-value`,
    'DEMO_A analytics application': tradeSignalField('DEMO_A', 5),
    'DEMO_A order column head': `${orderColumn('DEMO_A')} .column-head`,
    'DEMO_A order details': `${orderColumn('DEMO_A')} .facts`,
    'DEMO_C expected move "Not provided"': tradeSignalField('DEMO_C', 3),
};

const minute = 60 * 1000;
const livewireUpdate = /\/livewire[^/]*\/update$/;

test('the orders screen lists the seeded orders awaiting approval with the soonest signal validity first', async ({ page }) => {
    await page.goto('/orders');

    await expect(page.locator('.order-symbol')).toHaveText(['DEMO_A', 'DEMO_B', 'DEMO_C']);
});

for (const theme of ['light', 'dark']) {
    for (const [element, selector] of Object.entries(comparedElements)) {
        test(`the orders screen's ${element} matches orders.html in the ${theme} theme`, async ({ page }) => {
            await storeTheme(page, theme);
            await page.goto('/orders');

            await expectToMatchMockup(page, 'orders.html', { theme, selector });
        });
    }

    test(`the status and signal validity of DEMO_B, 2 h 28 min before its validity ends, match orders.html in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.goto('/orders');
        const validUntil = await page.locator(`${order('DEMO_B')} [data-time-left-until]`).getAttribute('data-time-left-until');
        await page.clock.setFixedTime(Date.parse(validUntil) - (2 * 60 + 28) * minute);
        await page.reload();

        await expect(page.locator(`${order('DEMO_B')} .time-left`)).toHaveText('2 h 28 min left');
        await expectToMatchMockup(page, 'orders.html', { theme, selector: `${order('DEMO_B')} .decision-pair` });
    });
}

test('the time left of DEMO_A counts down in the browser between updates', async ({ page }) => {
    await page.goto('/orders');
    const validUntil = Date.parse(await page.locator(`${order('DEMO_A')} [data-time-left-until]`).getAttribute('data-time-left-until'));
    const loadedAt = validUntil - 43 * minute - 10 * 1000;
    await page.clock.install({ time: loadedAt });
    await page.reload();
    await page.clock.pauseAt(loadedAt + 5 * 1000);
    const timeLeft = page.locator(`${order('DEMO_A')} .time-left`);
    await expect(timeLeft).toHaveText('44 min left');

    // 42 min 55 s left, before the first update after 30 seconds.
    await page.clock.runFor(10 * 1000);

    await expect(timeLeft).toHaveText('43 min left');
});

test('after 30 seconds, an update runs without a page reload and keeps the scroll position and the focus', async ({ page }) => {
    await page.clock.install();
    await page.goto('/orders');
    await page.evaluate(() => {
        window.loadedOnce = true;
    });
    const lastUpdate = page.locator('.live-status');
    const lastUpdateBefore = await lastUpdate.textContent();
    const demoC = page.locator(order('DEMO_C'));
    await demoC.focus();
    await page.evaluate(() => window.scrollTo(0, 300));
    // The server renders the last update time to the second, and Playwright's clock does not move the server's.
    await page.waitForTimeout(1100);

    const update = page.waitForResponse(livewireUpdate);
    await page.clock.runFor(30 * 1000);
    await update;

    await expect(lastUpdate).not.toHaveText(lastUpdateBefore);
    expect(await page.evaluate(() => window.loadedOnce)).toBe(true);
    expect(await page.evaluate(() => window.scrollY)).toBe(300);
    await expect(demoC).toBeFocused();
});

for (const theme of ['light', 'dark']) {
    test(`while updates fail, the orders screen says that the connection is lost, as foundations.html does in the ${theme} theme, and the retry clears it`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.clock.install();
        await page.goto('/orders');
        let failedUpdates = 0;
        await page.route(livewireUpdate, (route) => {
            failedUpdates++;

            return route.abort();
        });

        await page.clock.runFor(30 * 1000);

        const connectionLost = page.locator('.notice:has(button)');
        await expect(connectionLost).toBeVisible();
        await expect(connectionLost.locator('p')).toHaveText('Connection lost. Statuses and the time left may be out of date. Trying again.');
        await expect(page.locator('.live-status')).toHaveText(/^Connection lost, data from \d\d:\d\d:\d\d UTC$/);
        // The sample spans the width of foundations.html, so its text and its button are compared.
        await expectToMatchMockup(page, 'foundations.html', { theme, selector: '.notice:has(button) > p' });
        await expectToMatchMockup(page, 'foundations.html', { theme, selector: '.notice:has(button) > button' });

        await page.clock.runFor(30 * 1000);
        await expect.poll(() => failedUpdates).toBeGreaterThanOrEqual(2);

        await page.unroute(livewireUpdate);
        const update = page.waitForResponse(livewireUpdate);
        await connectionLost.getByRole('button', { name: 'Retry now' }).click();
        expect((await update).ok()).toBe(true);
        await expect(connectionLost).toBeHidden();
        await expect(page.locator('.live-status')).toHaveText(/^Live, updated \d\d:\d\d:\d\d UTC$/);
    });
}
