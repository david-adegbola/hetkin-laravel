/** Ported 1:1 from the Next.js app's src/lib/format.ts. */
export function formatEurCents(cents: number): string {
    return new Intl.NumberFormat('fi-FI', {
        style: 'currency',
        currency: 'EUR',
    }).format(cents / 100);
}
