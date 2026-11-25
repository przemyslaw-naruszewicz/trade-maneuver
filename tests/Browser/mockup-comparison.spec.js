import { test } from '@playwright/test';
import { expectToMatchMockup, openMockup } from './support/mockup.js';

const mockupPage = 'sign-in.html';

for (const theme of ['light', 'dark']) {
    test(`a mockup page matches itself as a whole page in the ${theme} theme`, async ({ page }) => {
        await openMockup(page, mockupPage, theme);

        await expectToMatchMockup(page, mockupPage, { theme });
    });

    test(`a mockup page matches itself as one element in the ${theme} theme`, async ({ page }) => {
        await openMockup(page, mockupPage, theme);

        await expectToMatchMockup(page, mockupPage, { theme, selector: 'form' });
    });
}
