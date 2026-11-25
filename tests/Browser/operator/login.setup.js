import { expect, test as setup } from '@playwright/test';
import { currentTotpCode, operatorSessionFile, submitPassword } from '../support/operator.js';

setup('the seeded operator logs in with the password and a TOTP code and reaches the start page', async ({ page }) => {
    await submitPassword(page);
    await page.getByLabel('Authentication code').fill(currentTotpCode());
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL('/');
    await expect(page.getByRole('heading', { name: 'Start' })).toBeVisible();

    await page.context().storageState({ path: operatorSessionFile });
});
