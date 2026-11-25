import { fileURLToPath } from 'node:url';
import { expect } from '@playwright/test';
import { TOTP } from 'otpauth';

// The account that BrowserTestOperatorSeeder creates in the e2e database.
const email = 'operator@example.test';
const password = 'a-browser-test-operator-password';
const totp = new TOTP({ secret: 'DEMODEMODEMODEMODEMODEMODEMODEMO', digits: 6, period: 30 });
const themeStorageKey = 'trade-maneuver-theme';

export const operatorSessionFile = fileURLToPath(
    new URL('../../../storage/quality/playwright/.auth/operator.json', import.meta.url),
);

export function currentTotpCode() {
    return totp.generate();
}

/**
 * A 6-digit code that differs from the codes of the previous, current, and next time step, which the application
 * accepts.
 */
export function wrongTotpCode() {
    const now = Date.now();
    const acceptedCodes = [-1, 0, 1].map((step) => totp.generate({ timestamp: now + step * totp.period * 1000 }));

    for (let candidate = 0; ; candidate++) {
        const code = String(candidate).padStart(6, '0');
        if (!acceptedCodes.includes(code)) {
            return code;
        }
    }
}

/**
 * Passes the password step, which leads to the TOTP challenge.
 */
export async function submitPassword(page) {
    await page.goto('/login');
    await page.getByLabel('E-mail address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Continue' }).click();
    await expect(page).toHaveURL('/login/totp');
}

/**
 * Stores the theme as the theme switch does, before any page of the test loads.
 */
export async function storeTheme(page, theme) {
    await page.addInitScript(([key, value]) => localStorage.setItem(key, value), [themeStorageKey, theme]);
}

export function pageTheme(page) {
    return page.locator('html');
}
