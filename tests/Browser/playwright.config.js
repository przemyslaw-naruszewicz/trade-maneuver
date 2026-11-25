import { defineConfig, devices } from '@playwright/test';
import { operatorSessionFile } from './support/operator.js';

// bin/e2e runs this configuration in the Playwright service of the e2e Compose profile.
const qualityDir = '../../storage/quality/playwright';
const browser = {
    ...devices['Desktop Chrome'],
    viewport: { width: 1280, height: 800 },
};

export default defineConfig({
    testDir: '.',
    outputDir: `${qualityDir}/test-results`,
    // The comparison helper writes each mockup screenshot here as the expected image, so none is committed.
    snapshotPathTemplate: `${qualityDir}/snapshots/{testFilePath}/{testName}/{arg}{ext}`,
    forbidOnly: !!process.env.CI,
    reporter: [
        ['list'],
        ['html', { outputFolder: `${qualityDir}/report`, open: 'never' }],
    ],
    use: {
        baseURL: process.env.E2E_BASE_URL,
        trace: 'retain-on-failure',
    },
    // A TOTP code is accepted once, so the operator logs in once per run and the panel tests share that session.
    // Logging out ends the session, so the logout test runs after every other panel test.
    projects: [
        {
            name: 'chromium',
            testIgnore: ['operator/login.setup.js', 'operator/panel.spec.js', 'operator/logout.spec.js'],
            use: browser,
        },
        {
            name: 'operator login',
            testMatch: 'operator/login.setup.js',
            use: browser,
        },
        {
            name: 'panel',
            testMatch: 'operator/panel.spec.js',
            dependencies: ['operator login'],
            use: { ...browser, storageState: operatorSessionFile },
        },
        {
            name: 'logout',
            testMatch: 'operator/logout.spec.js',
            dependencies: ['panel'],
            use: { ...browser, storageState: operatorSessionFile },
        },
    ],
});
