import { expect } from '@playwright/test';

// The Playwright service mounts docs/gui-design here, read-only.
const mockupDir = '/mockup';
const viewport = { width: 1280, height: 800 };

export async function openMockup(page, mockupPage, theme) {
    await page.setViewportSize(viewport);
    await page.goto(`file://${mockupDir}/${mockupPage}`);
    await page.locator('[data-theme]').first().evaluate((root, value) => {
        root.dataset.theme = value;
    }, theme);
    await page.evaluate(async () => {
        await document.fonts.ready;
    });
}

export async function expectToMatchMockup(page, mockupPage, { theme, selector } = {}) {
    const actual = selector
        ? await page.locator(selector).screenshot({ animations: 'disabled' })
        : await page.screenshot({ animations: 'disabled', fullPage: true });

    expect(actual).toMatchSnapshot(`${mockupPage}-${theme}.png`);
}
