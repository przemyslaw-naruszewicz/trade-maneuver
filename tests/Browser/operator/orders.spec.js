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
