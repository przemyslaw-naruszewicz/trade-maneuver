import { mkdir, writeFile } from 'node:fs/promises';
import { dirname } from 'node:path';
import { expect, test } from '@playwright/test';

// The Playwright service mounts docs/gui-design here, read-only.
const mockupDir = '/mockup';
const viewport = { width: 1280, height: 800 };
const maxDiffPixelRatio = 0.01;

/**
 * Opens a page of the mockup in the given theme, `light` or `dark`.
 */
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

/**
 * Compares the page under test, already open in the given theme, with a mockup page in the same theme: the whole
 * page, or the element that the selector finds on each page. At most 1% of the pixels may differ.
 *
 * The mockup page's screenshot becomes the expected image of Playwright's toMatchSnapshot, which compares it with
 * the screenshot of the page under test and attaches the difference image to the report.
 */
export async function expectToMatchMockup(page, mockupPage, { theme, selector }) {
    const testInfo = test.info();
    const snapshotName = `${[mockupPage, theme, selector].filter(Boolean).join('-')}.png`;
    const mockup = await page.context().newPage();
    try {
        await openMockup(mockup, mockupPage, theme);
        const expectedPath = testInfo.snapshotPath(snapshotName);
        await mkdir(dirname(expectedPath), { recursive: true });
        await writeFile(expectedPath, await screenshot(mockup, selector));
    } finally {
        await mockup.close();
    }

    await page.setViewportSize(viewport);
    const compared = selector ? `element ${selector}` : 'whole page';
    expect(
        await screenshot(page, selector),
        `${page.url()} differs from the mockup page ${mockupPage} (${compared}, ${theme} theme)`,
    ).toMatchSnapshot(snapshotName, { maxDiffPixelRatio });
}

function screenshot(page, selector) {
    const options = { animations: 'disabled' };

    return selector ? page.locator(selector).screenshot(options) : page.screenshot({ ...options, fullPage: true });
}
