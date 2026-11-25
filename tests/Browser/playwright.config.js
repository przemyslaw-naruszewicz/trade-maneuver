import { defineConfig, devices } from '@playwright/test';

// bin/e2e runs this configuration in the Playwright service of the e2e Compose profile.
const qualityDir = '../../storage/quality/playwright';

export default defineConfig({
    testDir: '.',
    outputDir: `${qualityDir}/test-results`,
    // The comparison helper writes each mockup screenshot here as the expected image, so none is committed.
    snapshotPathTemplate: `${qualityDir}/snapshots/{testFilePath}/{arg}{ext}`,
    forbidOnly: !!process.env.CI,
    reporter: [
        ['list'],
        ['html', { outputFolder: `${qualityDir}/report`, open: 'never' }],
    ],
    use: {
        baseURL: process.env.E2E_BASE_URL,
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
            },
        },
    ],
});
