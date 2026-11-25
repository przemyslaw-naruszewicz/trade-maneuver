import { expect, test } from '@playwright/test';
import { pageTheme } from '../support/operator.js';

test('after logout, the login page applies the theme chosen in the panel', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('button', { name: 'Switch theme' }).click();
    await expect(pageTheme(page)).toHaveAttribute('data-theme', 'dark');

    await page.getByRole('button', { name: 'Log out' }).click();

    await expect(page).toHaveURL('/login');
    await expect(pageTheme(page)).toHaveAttribute('data-theme', 'dark');
    await page.goto('/');
    await expect(page).toHaveURL('/login');
});
