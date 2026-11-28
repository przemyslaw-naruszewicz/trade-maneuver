// Shows the time left until a signal validity ends in each element with data-time-left-until, counted from the
// browser's clock. An element with data-time-left-unit-class shows the unit in a span of that class.
const minute = 60 * 1000;

/**
 * The time left in whole minutes, rounded up, so that a validity that has not ended never shows 0 minutes.
 */
export function timeLeft(validUntil, now) {
    const minutes = Math.max(0, Math.ceil((validUntil - now) / minute));
    if (minutes < 60) {
        return { value: String(minutes), unit: 'min left' };
    }
    if (minutes < 24 * 60) {
        return { value: String(Math.floor(minutes / 60)), unit: `h ${minutes % 60} min left` };
    }
    const days = Math.floor(minutes / (24 * 60));

    return { value: String(days), unit: days === 1 ? 'day left' : 'days left' };
}

export function showTimesLeft(root = document) {
    const now = Date.now();
    root.querySelectorAll('[data-time-left-until]').forEach((element) => {
        const { value, unit } = timeLeft(Date.parse(element.dataset.timeLeftUntil), now);
        const unitClass = element.dataset.timeLeftUnitClass;
        if (unitClass === undefined) {
            element.textContent = `${value} ${unit}`;

            return;
        }
        const unitElement = document.createElement('span');
        unitElement.className = unitClass;
        unitElement.textContent = unit;
        element.replaceChildren(value, unitElement);
    });
}
