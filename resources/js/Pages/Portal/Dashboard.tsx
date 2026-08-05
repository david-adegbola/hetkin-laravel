import EnergySchedulingChart, { type EnergyPricePoint } from '@/Components/portal/EnergySchedulingChart';
import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import RecommendationDecisionButtons from '@/Components/portal/RecommendationDecisionButtons';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { formatEurCents, formatScheduleDateTime } from '@/lib/format';
import { usePortalBasePath } from '@/lib/subdomain';
import type { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ReactElement } from 'react';

interface RecommendationDecision {
    decision: string;
    reason: string | null;
    adjusted_start_at: string | null;
}

interface RecommendationRow {
    id: string;
    schedule_block_id: string | null;
    rationale: string;
    delivery_risk: string;
    projected_savings_cents: number;
    original_start_at: string | null;
    original_end_at: string | null;
    proposed_start_at: string;
    proposed_end_at: string;
    price_curve: EnergyPricePoint[];
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

interface OnboardingStatus {
    hasMeterData: boolean;
    hasAssessment: boolean;
    hasScheduleBlock: boolean;
    hasDecision: boolean;
}

function severityDotClass(severity: string): string {
    if (severity === 'WARNING') return 'bg-error';
    if (severity === 'OPPORTUNITY') return 'bg-success';

    return 'bg-primary';
}

function StatCard({
    label,
    value,
    sub,
}: {
    label: string;
    value: string;
    sub?: string;
}) {
    return (
        <Card>
            <div className="text-text-secondary text-xs font-semibold tracking-wide uppercase">
                {label}
            </div>
            <div className="font-display text-primary mt-2 text-2xl font-bold tabular-nums">
                {value}
            </div>
            {sub && (
                <div className="text-text-secondary mt-1.5 text-xs">{sub}</div>
            )}
        </Card>
    );
}

interface ChecklistStep {
    label: string;
    done: boolean;
    href: string;
    cta: string;
}

/**
 * Replaces the wall of zeros a freshly-provisioned org used to see —
 * mirrors v2's OnboardingChecklist (src/app/(portal)/dashboard/page.tsx)
 * exactly: funnel order data → assessment → schedule → acted-on
 * recommendation, disappears permanently once every step is done since an
 * established customer has no use for onboarding chrome.
 */
function OnboardingChecklist({ steps }: { steps: ChecklistStep[] }) {
    const doneCount = steps.filter((s) => s.done).length;
    if (doneCount === steps.length) return null;

    return (
        <Card className="border-primary/10 bg-primary/[0.03] mb-6">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="font-display text-primary text-base font-semibold">
                    Get your first savings assessment
                </h3>
                <span className="text-text-secondary text-xs font-medium">
                    {doneCount} of {steps.length} done
                </span>
            </div>
            <ol className="flex flex-col gap-2.5">
                {steps.map((step, i) => (
                    <li
                        key={step.label}
                        className="bg-background flex flex-wrap items-center justify-between gap-2 rounded-lg px-3.5 py-3 shadow-xs"
                    >
                        <div className="flex items-center gap-3">
                            <span
                                aria-hidden
                                className={`flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                    step.done
                                        ? 'bg-success text-white'
                                        : 'bg-border text-text-secondary'
                                }`}
                            >
                                {step.done ? '✓' : i + 1}
                            </span>
                            <span
                                className={`text-sm ${step.done ? 'text-text-secondary line-through' : 'text-primary font-medium'}`}
                            >
                                {step.label}
                            </span>
                        </div>
                        {!step.done && (
                            <Link
                                href={step.href}
                                className="bg-accent hover:bg-accent-hover rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition-colors"
                            >
                                {step.cta}
                            </Link>
                        )}
                    </li>
                ))}
            </ol>
        </Card>
    );
}

/**
 * Portal overview — mirrors the Next.js app's src/app/(portal)/dashboard/
 * page.tsx in full as of MIGRATION_PLAN.md's post-Stage-12 ADR. See
 * App\Http\Controllers\Portal\DashboardController's docblock for why the
 * stat cards below were deferred until now and what closed the gap.
 */
export default function Dashboard({
    mtdSavingsCents,
    ytdSavingsCents,
    adoptionRate,
    followedCount,
    totalDecisions,
    recentRecommendations,
    activeAlertCount,
    recentAlerts,
    onboarding,
}: {
    mtdSavingsCents: number;
    ytdSavingsCents: number;
    adoptionRate: number | null;
    followedCount: number;
    totalDecisions: number;
    recentRecommendations: RecommendationRow[];
    activeAlertCount: number;
    recentAlerts: AlertRow[];
    onboarding: OnboardingStatus;
}) {
    const { organization } = usePage<PageProps>().props;
    const base = usePortalBasePath();
    const featuredRecommendation = recentRecommendations[0] ?? null;

    const checklistSteps: ChecklistStep[] = [
        {
            label: 'Upload your electricity consumption data',
            done: onboarding.hasMeterData,
            href: `${base}/data`,
            cta: 'Upload data',
        },
        {
            label: 'Generate your first savings assessment',
            done: onboarding.hasAssessment,
            href: `${base}/reports`,
            cta: 'Generate assessment',
        },
        {
            label: 'Add your production schedule',
            done: onboarding.hasScheduleBlock,
            href: `${base}/schedule`,
            cta: 'Add schedule',
        },
        {
            label: 'Act on a recommendation',
            done: onboarding.hasDecision,
            href: `${base}/schedule`,
            cta: 'Run optimization',
        },
    ];

    return (
        <>
            <Head title="Dashboard" />
            <PortalPageHeader
                title="Savings Dashboard"
                meta={
                    organization
                        ? `${organization.name}${organization.status === 'PILOT' ? ' · Pilot' : ''}`
                        : undefined
                }
            />

            <OnboardingChecklist steps={checklistSteps} />

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Verified savings (MTD)"
                    value={formatEurCents(mtdSavingsCents)}
                    sub={
                        mtdSavingsCents
                            ? undefined
                            : 'No verification cycles closed yet'
                    }
                />
                <StatCard
                    label="Verified savings (YTD)"
                    value={formatEurCents(ytdSavingsCents)}
                />
                <StatCard
                    label="Recommendations followed"
                    value={adoptionRate === null ? '—' : `${adoptionRate}%`}
                    sub={
                        totalDecisions > 0
                            ? `${followedCount} of ${totalDecisions}`
                            : 'No recommendations issued yet'
                    }
                />
                <StatCard
                    label="Active alerts"
                    value={String(activeAlertCount)}
                />
            </div>

            <EnergySchedulingChart prices={featuredRecommendation?.price_curve ?? []} />

            <div className="mb-4 grid gap-4 lg:grid-cols-[1.5fr_1fr]">
                <Card>
                    <div className="mb-4 flex items-center justify-between">
                        <h3 className="font-display text-primary text-base font-semibold">
                            Recent recommendations
                        </h3>
                    </div>
                    {recentRecommendations.length === 0 ? (
                        <p className="text-text-secondary py-6 text-center text-sm">
                            No recommendations yet — these appear once your
                            savings assessment and optimization engine are
                            running against real schedule and price data.
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
                                                        ? `partially adopted — moved to ${rec.decision.adjusted_start_at ? formatScheduleDateTime(rec.decision.adjusted_start_at) : '?'}`
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
                                                proposedEndAt={
                                                    rec.proposed_end_at
                                                }
                                            />
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </Card>

                <Card>
                    <div className="mb-4">
                        <h3 className="font-display text-primary text-base font-semibold">
                            Alerts
                        </h3>
                    </div>
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
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactElement) => <PortalLayout>{page}</PortalLayout>;
