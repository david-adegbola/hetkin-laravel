<?php

namespace App\Domain\Billing\Enums;

/** Mirrors the Next.js app's `InvoiceStatus` Prisma enum exactly. */
enum InvoiceStatus: string
{
    case Draft = 'DRAFT';
    case Issued = 'ISSUED';
    case Paid = 'PAID';
    case Disputed = 'DISPUTED';
    case Void = 'VOID';
}
