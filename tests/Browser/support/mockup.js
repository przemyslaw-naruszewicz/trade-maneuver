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
 * With `pinned`, the element is moved to the top left corner of the viewport on both pages for the screenshot. An
 * element in a table cell sits at a fractional position that depends on the table's other rows and columns, and text
 * at another fraction of a pixel is anti-aliased differently.
 *
 * The mockup page's screenshot becomes the expected image of Playwright's toMatchSnapshot, which compares it with
 * the screenshot of the page under test and attaches the difference image to the report.
 */
export async function expectToMatchMockup(page, mockupPage, { theme, selector, pinned = false }) {
    const testInfo = test.info();
    // These modes would replace the mockup's screenshot with the page under test's, so the comparison would pass.
    if (['all', 'changed'].includes(testInfo.config.updateSnapshots)) {
        throw new Error('The mockup comparison takes its expected image from the mockup, so it cannot run with --update-snapshots.');
    }

    const snapshotName = `${[mockupPage, theme, selector].filter(Boolean).join('-')}.png`;
    const mockup = await page.context().newPage();
    try {
        await openMockup(mockup, mockupPage, theme);
        const expectedPath = testInfo.snapshotPath(snapshotName);
        await mkdir(dirname(expectedPath), { recursive: true });
        await writeFile(expectedPath, await screenshot(mockup, selector, pinned));
    } finally {
        await mockup.close();
    }

    await page.setViewportSize(viewport);
    const compared = selector ? `element ${selector}` : 'whole page';
    expect(
        await screenshot(page, selector, pinned),
        `${page.url()} differs from the mockup page ${mockupPage} (${compared}, ${theme} theme)`,
    ).toMatchSnapshot(snapshotName, { maxDiffPixelRatio });
}

function screenshot(page, selector, pinned) {
    const options = { animations: 'disabled' };
    if (pinned) {
        options.style = `${selector} { position: fixed !important; inset: 0 auto auto 0 !important; margin: 0 !important; z-index: 2147483647 !important; background: var(--paper) !important; }`;
    }

    return selector ? page.locator(selector).screenshot(options) : page.screenshot({ ...options, fullPage: true });
}
