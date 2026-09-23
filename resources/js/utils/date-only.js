export const APPLICATION_TIME_ZONE = 'America/Lima';

export function normalizeDateOnly(value) {
    if (!value) return '';

    const date = String(value).slice(0, 10);
    return /^\d{4}-\d{2}-\d{2}$/.test(date) ? date : '';
}

export function formatDateOnlyForDisplay(value, fallback = '-') {
    const date = normalizeDateOnly(value);
    if (!date) return value ? String(value).slice(0, 10) : fallback;

    const [year, month, day] = date.split('-');
    return `${day}/${month}/${year}`;
}

export function currentDateOnlyInTimeZone(now = new Date(), timeZone = APPLICATION_TIME_ZONE) {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(now);
    const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));

    return `${values.year}-${values.month}-${values.day}`;
}
