import { expect, test } from '@playwright/test';
import { expectToMatchMockup } from '../support/mockup.js';
import { pageTheme, storeTheme, submitPassword, wrongTotpCode } from '../support/operator.js';

test('the login page renders the light theme by default', async ({ page }) => {
    await page.goto('/login');

    await expect(pageTheme(page)).toHaveAttribute('data-theme', 'light');
});

test('with the dark theme stored, the login page is dark when the browser parses <body>', async ({ page }) => {
    await storeTheme(page, 'dark');
    await page.addInitScript(() => {
        new MutationObserver((mutations, observer) => {
            if (document.body) {
                window.themeWhenBodyWasParsed = document.documentElement.dataset.theme;
                observer.disconnect();
            }
        }).observe(document, { childList: true, subtree: true });
    });

    await page.goto('/login');

    expect(await page.evaluate(() => window.themeWhenBodyWasParsed)).toBe('dark');
});

for (const theme of ['light', 'dark']) {
    test(`the login page matches sign-in.html in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await page.goto('/login');

        await expectToMatchMockup(page, 'sign-in.html', { theme });
    });

    test(`the TOTP page in its error state matches totp-challenge.html in the ${theme} theme`, async ({ page }) => {
        await storeTheme(page, theme);
        await submitPassword(page);
        await page.getByLabel('Authentication code').fill(wrongTotpCode());
        await page.getByRole('button', { name: 'Sign in' }).click();
        await expect(page.getByText('This code is not valid, or it has already been used.')).toBeVisible();
        // The mockup shows this value in the field.
        await page.getByLabel('Authentication code').fill('480216');

        await expectToMatchMockup(page, 'totp-challenge.html', { theme });
    });
}
