// When an update of a component marked with data-live-updates fails, the component shows its connection lost
// elements instead of Livewire's error dialog, and its poll keeps trying. The next successful update renders the
// component from the server again, which hides them.
export function showLostConnections(livewire) {
    livewire.interceptMessage(({ message, onFailure, onError }) => {
        const root = message.component.el;
        if (!root.hasAttribute('data-live-updates')) {
            return;
        }
        onFailure(() => showConnectionLost(root));
        onError(({ response, preventDefault }) => {
            // Livewire offers to reload a page whose session has expired.
            if (response.status === 419) {
                return;
            }
            preventDefault();
            showConnectionLost(root);
        });
    });
}

function showConnectionLost(root) {
    root.querySelectorAll('[data-connection-lost]').forEach((element) => {
        element.hidden = false;
    });
    root.querySelectorAll('[data-live-status]').forEach((status) => {
        status.textContent = `Connection lost, data from ${status.dataset.updatedAt}`;
        status.classList.add('live-status-lost');
    });
}
