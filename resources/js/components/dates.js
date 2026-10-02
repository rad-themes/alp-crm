const relative = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });

const units = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
];

/** "3 days ago", "in 2 hours", or "just now". */
export function fromNow(iso) {
    if (!iso) return '';

    const seconds = (new Date(iso).getTime() - Date.now()) / 1000;

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relative.format(Math.round(seconds / size), unit);
        }
    }

    return __('just now');
}

/** Localised date, e.g. "2 Oct 2026". */
export function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}

/** Localised date and time, e.g. "2 Oct 2026, 14:05". */
export function formatDateTime(iso) {
    return iso ? new Date(iso).toLocaleString(undefined, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
}
