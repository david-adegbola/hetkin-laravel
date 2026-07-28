import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card } from '@/Components/ui/Card';
import { FieldError } from '@/Components/ui/Input';
import PortalLayout from '@/Layouts/PortalLayout';
import { formatEurCents } from '@/lib/format';
import { usePortalBasePath } from '@/lib/subdomain';
import { Link, router } from '@inertiajs/react';
import { ReactElement, useState } from 'react';

interface AssessmentRow {
    id: string;
    generated_at: string;
    status: string;
    provisional: boolean;
    current_annual_cost_cents: number;
    optimized_annual_cost_cents: number;
    net_customer_savings_cents: number;
}

interface VerificationRow {
    id: string;
    period_start: string;
    period_end: string;
    verified_savings_cents: number;
    review_status: string;
}

function GenerateAssessmentButton({ base }: { base: string }) {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function submit() {
        setProcessing(true);
        setError(null);
        router.post(
            `${base}/reports/generate`,
            {},
            {
                preserveScroll: true,
                onError: (errors) => setError(errors.assessment ?? null),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div>
            <Button
                type="button"
                size="sm"
                disabled={processing}
                onClick={submit}
            >
                {processing ? 'Generating…' : 'Generate savings assessment'}
            </Button>
            <FieldError>{error}</FieldError>
        </div>
    );
}

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/reports/page.tsx.
 */
export default function Reports({
    assessments,
    verifications,
}: {
    assessments: AssessmentRow[];
    verifications: VerificationRow[];
}) {
    const base = usePortalBasePath();

    return (
        <>
            <PortalPageHeader title="Reports" />

            <div className="mb-8">
                <div className="mb-3 flex items-center justify-between">
                    <h3 className="font-display text-primary text-sm font-semibold">
                        Savings assessments
                    </h3>
                    <GenerateAssessmentButton base={base} />
                </div>

                {assessments.length === 0 ? (
                    <Card className="text-text-secondary py-8 text-center text-sm">
                        Generate your first savings assessment once you've
                        uploaded meter data and price data covers the same
                        period. Every figure will read "Estimated" until the
                        baseline methodology has been through legal review and
                        is locked by an admin.
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        {assessments.map((a) => (
                            <Card key={a.id}>
                                <div className="mb-2 flex items-center justify-between">
                                    <div className="text-text-secondary text-xs">
                                        {new Date(
                                            a.generated_at,
                                        ).toLocaleDateString('en-GB')}{' '}
                                        · {a.status.toLowerCase()}
                                    </div>
                                    <Badge
                                        variant={
                                            a.provisional ? 'mid' : 'cheap'
                                        }
                                        showGlyph={false}
                                    >
                                        {a.provisional
                                            ? 'Estimated'
                                            : 'Verified baseline'}
                                    </Badge>
                                </div>
                                <div className="font-display text-success text-xl font-bold tabular-nums">
                                    {formatEurCents(
                                        a.net_customer_savings_cents,
                                    )}
                                </div>
                                <div className="text-text-secondary text-xs">
                                    {a.provisional
                                        ? 'Estimated net customer savings'
                                        : 'Net customer savings'}
                                </div>
                                <div className="text-text-secondary mt-2 text-xs">
                                    Baseline:{' '}
                                    {formatEurCents(
                                        a.current_annual_cost_cents,
                                    )}
                                    /yr →{' '}
                                    {formatEurCents(
                                        a.optimized_annual_cost_cents,
                                    )}
                                    /yr
                                </div>
                                <div className="mt-3 flex items-center gap-4">
                                    <Link
                                        href={`${base}/reports/${a.id}`}
                                        className="text-accent text-xs font-semibold hover:underline"
                                    >
                                        View report →
                                    </Link>
                                    <a
                                        href={`${base}/reports/${a.id}/pdf`}
                                        className="text-accent text-xs font-semibold hover:underline"
                                    >
                                        Download PDF ↓
                                    </a>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            <div>
                <h3 className="font-display text-primary mb-3 text-sm font-semibold">
                    Monthly verification
                </h3>
                {verifications.length === 0 ? (
                    <Card className="text-text-secondary py-8 text-center text-sm">
                        Monthly verification reports appear here once your first
                        billing cycle closes. New cycles run in shadow mode
                        before affecting invoicing.
                    </Card>
                ) : (
                    <Card>
                        <table className="w-full text-left text-sm">
                            <thead className="border-border text-text-secondary border-b text-xs font-semibold uppercase">
                                <tr>
                                    <th className="py-2 pr-4">Period</th>
                                    <th className="py-2 pr-4">
                                        Verified savings
                                    </th>
                                    <th className="py-2 pr-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {verifications.map((v) => (
                                    <tr
                                        key={v.id}
                                        className="border-border border-b last:border-0"
                                    >
                                        <td className="py-2 pr-4">
                                            {new Date(
                                                v.period_start,
                                            ).toLocaleDateString('en-GB')}{' '}
                                            –{' '}
                                            {new Date(
                                                v.period_end,
                                            ).toLocaleDateString('en-GB')}
                                        </td>
                                        <td className="py-2 pr-4 tabular-nums">
                                            {formatEurCents(
                                                v.verified_savings_cents,
                                            )}
                                        </td>
                                        <td className="py-2 pr-4">
                                            {v.review_status
                                                .replace(/_/g, ' ')
                                                .toLowerCase()}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </Card>
                )}
            </div>
        </>
    );
}

Reports.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
