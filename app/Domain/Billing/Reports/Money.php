<?php

namespace App\Domain\Billing\Reports;

use NumberFormatter;

/** Mirrors the Next.js app's formatEurCents() (src/lib/format.ts) — the only sanctioned way to render money in this domain. */
class Money
{
    public static function formatEurCents(int $cents): string
    {
        static $formatter = null;
        $formatter ??= new NumberFormatter('fi-FI', NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($cents / 100, 'EUR');
    }
}
