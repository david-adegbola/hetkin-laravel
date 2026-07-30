/** Ported 1:1 from the Next.js app's src/lib/format.ts. */
export function formatEurCents(cents: number): string {
    return new Intl.NumberFormat('fi-FI', {
        style: 'currency',
        currency: 'EUR',
    }).format(cents / 100);
}

/**
 * Every pilot site is in Finland — matches config('scheduling.timezone')'s
 * own default (config/scheduling.php) — so a production-schedule block's
 * start/end time is always displayed in Europe/Helsinki, explicitly,
 * rather than `new Date(iso).toLocaleString()`'s implicit *viewer's
 * browser* timezone. Those two usually agree for a Finnish user but
 * silently diverge for anyone viewing from elsewhere, which would show a
 * physical Finnish site's schedule in the wrong wall-clock time.
 */
export function formatScheduleDateTime(iso: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone: 'Europe/Helsinki',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    }).format(new Date(iso));
}

/**
 * Converts a UTC ISO string into the "YYYY-MM-DDTHH:mm" shape a native
 * `<input type="datetime-local">` needs to pre-fill with the equivalent
 * Europe/Helsinki wall-clock value — e.g. for editing a recommendation's
 * proposed_start_at (RecommendationDecisionButtons.tsx). A naive
 * `iso.slice(0, 16)` string-slice would take the raw UTC digits and treat
 * them as if they were already local time, silently reintroducing the
 * exact class of bug formatScheduleDateTime() above exists to avoid —
 * `Intl.DateTimeFormat`'s `formatToParts` is used instead of any
 * locale-formatting shortcut so the field order/padding is explicit, not
 * an incidental property of whichever locale happens to sort that way.
 */
export function toHelsinkiDatetimeLocalValue(iso: string): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Helsinki',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(new Date(iso));

    const get = (type: string) =>
        parts.find((p) => p.type === type)?.value ?? '00';

    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}
