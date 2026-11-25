import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { expect, test } from '@playwright/test';

const playwright = fileURLToPath(new URL('../../node_modules/.bin/playwright', import.meta.url));

// Each case runs one mockup comparison in a nested Playwright run with the option.
for (const option of ['--update-snapshots', '--update-snapshots=all']) {
    test(`the comparison helper fails when Playwright runs with ${option}`, async ({}, testInfo) => {
        test.setTimeout(60_000);

        const run = spawnSync(playwright, [
            'test',
            `--config=${testInfo.config.configFile}`,
            '--grep=a mockup page matches itself as a whole page in the light theme',
            '--reporter=line',
            `--output=${testInfo.outputPath('nested-run')}`,
            option,
        ], { encoding: 'utf8' });

        expect(run.stdout, run.stderr).toContain('1 failed');
        expect(run.stdout).toContain('cannot run with --update-snapshots');
    });
}
