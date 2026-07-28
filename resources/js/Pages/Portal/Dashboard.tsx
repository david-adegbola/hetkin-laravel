import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import RecommendationDecisionButtons from '@/Components/portal/RecommendationDecisionButtons';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { formatEurCents } from '@/lib/format';
import { usePortalBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ReactElement } from 'react';

interface RecommendationDecision {
    decision: string;
    reason: string | null;
    adjusted_start_at: string | null;
}

interface RecommendationRow {
    id: string;
    rationale: string;
    delivery_risk: string;
    projected_savings_cents: number;
    proposed_start_at: string;
    proposed_end_at: string;
    decision: RecommendationDecision | null;
}

interface AlertRow {
    id: string;
    type: string;
    severity: string;
    title: string;
    body: string;
    created_at: string;
}

function severityDotClass(severity: string): string {
    if (severity === 'WARNING') return 'bg-error';
    if (severity === 'OPPORTUNITY') return 'bg-success';

    return 'bg-primary';
}

/**
 * Portal overview — see App\Http\Controllers\Portal\DashboardController's
 * docblock for why this is an honest interim page rather than an
 * approximation of v2's full stat-card dashboard (src/app/(portal)/
 * dashboard/page.tsx): the OTHER three stat cards (verified savings
 * MTD/YTD, adoption rate) come from a VerificationRecord aggregate view
 * this port hasn't built yet — a distinct gap from Alerts, not part of
 * Stage 10. Links to what Stage 5 actually built (Schedule, Data) instead
 * of showing zeros that would look identical whether nothing happened yet
 * or something is broken. "Recent recommendations" (Stage 6) and "Active
 * alerts"/"Alerts" (Stage 10) ARE real and ported here in full.
 */
export default function Dashboard({
    recentRecommendations,
    activeAlertCount,
    recentAlerts,
}: {
    recentRecommendations: RecommendationRow[];
    activeAlertCount: number;
    recentAlerts: AlertRow[];
}) {
    const { organization } = usePage<PageProps>().props;
    const base = usePortalBasePath();

    return (
        <>
            <PortalPageHeader
                title="Dashboard"
                meta={
                    organization
                        ? `${organization.name}${organization.status === 'PILOT' ? ' · Pilot' : ''}`
                        : undefined
                }
            />
            <Card className="mb-4">
                <h2 className="font-display text-primary mb-2 text-base font-semibold">
                    Get started
                </h2>
                <p className="text-text-secondary mb-4 text-sm">
                    Upload your electricity consumption data and add your
                    production schedule to start building toward a savings
                    assessment.
                </p>
                <div className="flex flex-wrap items-center gap-3">
                    <Link
                        href={`${base}/data`}
                        className="text-accent hover:text-accent-hover text-sm font-medium"
                    >
                        Upload data →
                    </Link>
                    <Link
                        href={`${base}/schedule`}
                        className="text-accent hover:text-accent-hover text-sm font-medium"
                    >
                        Add schedule →
                    </Link>
                    {activeAlertCount > 0 && (
                        <Badge variant="expensive" showGlyph={false}>
                            {activeAlertCount} active alert
                            {activeAlertCount === 1 ? '' : 's'}
                        </Badge>
                    )}
                </div>
            </Card>

            <Card className="mb-4">
                <h3 className="font-display text-primary mb-4 text-base font-semibold">
                    Alerts
                </h3>
                {recentAlerts.length === 0 ? (
                    <p className="text-text-secondary py-6 text-center text-sm">
                        No alerts.
                    </p>
                ) : (
                    <div>
                        {recentAlerts.map((alert) => (
                            <div
                                key={alert.id}
                                className="border-border flex items-start gap-3 border-b py-3 last:border-0"
                            >
                                <span
                                    className={`mt-1.5 h-2 w-2 flex-shrink-0 rounded-full ${severityDotClass(alert.severity)}`}
                                    aria-hidden="true"
                                />
                                <div className="flex-1">
                                    <div className="text-primary text-sm font-semibold">
                                        {alert.title}
                                    </div>
                                    <p className="text-text-secondary mt-0.5 text-sm">
                                        {alert.body}
                                    </p>
                                    <div className="text-text-secondary mt-1 text-xs">
                                        {new Date(
                                            alert.created_at,
                                        ).toLocaleDateString('en-GB')}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>

            <Card>
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="font-display text-primary text-base font-semibold">
                        Recent recommendations
                    </h3>
                </div>
                {recentRecommendations.length === 0 ? (
                    <p className="text-text-secondary py-6 text-center text-sm">
                        No recommendations yet — these appear once your savings
                        assessment and optimization engine are running against
                        real schedule and price data.
                    </p>
                ) : (
                    <div>
                        {recentRecommendations.map((rec) => (
                            <div
                                key={rec.id}
                                className="border-border flex flex-wrap items-center justify-between gap-3 border-b py-3.5 last:border-0"
                            >
                                <div className="min-w-[220px] flex-1">
                                    <div className="text-primary text-sm font-semibold">
                                        {rec.rationale}
                                    </div>
                                    <div className="text-text-secondary mt-1 flex flex-wrap items-center gap-1.5 text-xs">
                                        <Badge
                                            variant={
                                                rec.delivery_risk === 'LOW'
                                                    ? 'cheap'
                                                    : rec.delivery_risk ===
                                                        'MEDIUM'
                                                      ? 'mid'
                                                      : 'expensive'
                                            }
                                            showGlyph={false}
                                        >
                                            {rec.delivery_risk.toLowerCase()}{' '}
                                            risk
                                        </Badge>
                                        {rec.decision && (
                                            <span>
                                                {rec.decision.decision ===
                                                'PARTIAL'
                                                    ? `partially adopted — moved to ${rec.decision.adjusted_start_at ? new Date(rec.decision.adjusted_start_at).toLocaleString('en-GB') : '?'}`
                                                    : rec.decision.decision.toLowerCase()}
                                                {rec.decision.reason
                                                    ? ` — "${rec.decision.reason}"`
                                                    : ''}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <div className="font-display text-success text-sm font-bold tabular-nums">
                                        +
                                        {formatEurCents(
                                            rec.projected_savings_cents,
                                        )}
                                    </div>
                                    {!rec.decision && (
                                        <RecommendationDecisionButtons
                                            base={base}
                                            recommendationId={rec.id}
                                            proposedStartAt={
                                                rec.proposed_start_at
                                            }
                                            proposedEndAt={rec.proposed_end_at}
                                        />
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </Card>
        </>
    );
}

Dashboard.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
