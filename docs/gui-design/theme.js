const root = document.querySelector('[data-theme]');
const storageKey = 'trade-maneuver-theme';

function readStoredTheme() {
    try {
        return localStorage.getItem(storageKey);
    } catch {
        return null;
    }
}

function storeTheme(theme) {
    try {
        localStorage.setItem(storageKey, theme);
    } catch {
        // The toggle still works for this page when storage is blocked.
    }
}

const storedTheme = readStoredTheme();

if (root && storedTheme) {
    root.dataset.theme = storedTheme;
}

document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';

        root.dataset.theme = theme;
        storeTheme(theme);
    });
});
