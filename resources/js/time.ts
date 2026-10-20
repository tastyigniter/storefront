/** Show a 24-hour "HH:mm" time as "7:30 PM" on selectors; the value posted to the server stays 24-hour. */
export function formatTimeLabel(time: string): string {
    const match = /^(\d{1,2}):(\d{2})$/.exec(time.trim());
    if (!match) {
        return time;
    }

    const hours = Number(match[1]);
    if (hours > 23) {
        return time;
    }

    const period = hours >= 12 ? 'PM' : 'AM';
    const hour12 = hours % 12 || 12;

    return `${hour12}:${match[2]} ${period}`;
}

/** Show a "YYYY-MM-DD" date as "Wed, 21 Oct" (with the year when it isn't this year). */
export function formatDateLabel(date: string): string {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(date.trim());
    if (!match) {
        return date;
    }

    const value = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));

    return value.toLocaleDateString(undefined, {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        ...(value.getFullYear() === new Date().getFullYear() ? {} : { year: 'numeric' }),
    });
}
