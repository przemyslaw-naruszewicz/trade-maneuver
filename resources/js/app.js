import { showTimesLeft } from './time-left.js';

// The theme switch changes the theme and remembers the choice in this browser only. The document component
// applies a stored choice before the first paint.
const themeStorageKey = 'trade-maneuver-theme';

function storeTheme(theme) {
    try {
        localStorage.setItem(themeStorageKey, theme);
    } catch {
        // The switch still works for this page when the browser blocks storage.
    }
}

document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const root = document.documentElement;
        const theme = root.dataset.theme === 'dark' ? 'light' : 'dark';

        root.dataset.theme = theme;
        storeTheme(theme);
    });
});

showTimesLeft();
setInterval(showTimesLeft, 1000);
document.addEventListener('livewire:init', () => {
    // An update replaces the server-rendered time-left elements, which the browser then fills again.
    window.Livewire.hook('morphed', ({ el }) => showTimesLeft(el));
});
