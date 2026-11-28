// Marks each element with data-change-key that an update of its component adds or gives a new
// data-change-version, for about 10 seconds.
const markedFor = 10 * 1000;

export function markRecentChanges(livewire) {
    const versionsBeforeUpdate = new WeakMap();
    livewire.hook('morph', ({ el }) => versionsBeforeUpdate.set(el, versionsIn(el)));
    livewire.hook('morphed', ({ el }) => {
        const before = versionsBeforeUpdate.get(el);
        versionsBeforeUpdate.delete(el);
        if (before === undefined) {
            return;
        }
        el.querySelectorAll('[data-change-key]').forEach((element) => {
            if (before.get(element.dataset.changeKey) === element.dataset.changeVersion) {
                return;
            }
            element.classList.add('recently-changed');
            setTimeout(() => element.classList.remove('recently-changed'), markedFor);
        });
    });
}

function versionsIn(root) {
    const versions = new Map();
    root.querySelectorAll('[data-change-key]').forEach((element) => {
        versions.set(element.dataset.changeKey, element.dataset.changeVersion);
    });

    return versions;
}
