import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Badge } from '@/Components/ui/Badge';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { usePortalBasePath } from '@/lib/subdomain';
import { Head } from '@inertiajs/react';
import { ReactElement, ReactNode } from 'react';

interface Recommendation {
    priority: string;
    asset: string;
    what_to_change: string;
    current_timing: string;
    proposed_timing: string;
    why: string;
    saving_per_occurrence: string;
    annual_contribution: string | null;
    operational_impact: string;
    confirmation: string;
}

interface ViewModel {
    cover: {
        report_title: string;
        period_label: string;
        generated_label: string;
    };
    opportunity: {
        status: string;
        headline: string;
        kpis: { label: string; value: string }[];
        meaning: string;
        scope_note: string;
        priority_action: Recommendation | null;
    };
    recommendations: {
        items: Recommendation[];
        additional_count: number;
        empty_message: string;
    };
    evidence: {
        profile: {
            title: string;
            unit: string;
            points: { hour: string; kwh: number }[];
        };
        cost_comparison: { label: string; value: string }[];
        callout: string;
    };
    confidence: {
        label: string;
        reason: string;
        information: { label: string; value: string }[];
        included: string;
        confirm_before_action: string;
        ending_note: string;
    };
}

function SectionCard({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <Card className="mb-5">
            <h2 className="font-display text-primary mb-4 text-lg font-semibold">
                {title}
            </h2>
            {children}
        </Card>
    );
}

function RecommendationCard({
    recommendation,
}: {
    recommendation: Recommendation;
}) {
    return (
        <div className="border-border rounded-control border p-4">
            <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-accent text-xs font-semibold tracking-wide uppercase">
                        {recommendation.priority}
                    </p>
                    <h3 className="text-primary mt-1 font-semibold">
                        {recommendation.asset}
                    </h3>
                </div>
                <p className="text-success text-right text-sm font-semibold tabular-nums">
                    {recommendation.saving_per_occurrence}
                    {recommendation.annual_contribution && (
                        <span className="text-text-secondary mt-1 block text-xs font-normal">
                            {recommendation.annual_contribution}
                        </span>
                    )}
                </p>
            </div>
            <p className="text-text-secondary text-sm">
                {recommendation.what_to_change}
            </p>
            <div className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div className="bg-surface rounded-control p-2.5">
                    <span className="text-text-secondary block text-xs">
                        Current timing
                    </span>
                    <span className="text-primary font-medium">
                        {recommendation.current_timing}
                    </span>
                </div>
                <div className="bg-accent/5 rounded-control p-2.5">
                    <span className="text-text-secondary block text-xs">
                        Proposed timing
                    </span>
                    <span className="text-primary font-medium">
                        {recommendation.proposed_timing}
                    </span>
                </div>
            </div>
            <div className="mt-3 space-y-1.5 text-xs">
                <p className="text-text-secondary">
                    <span className="text-primary font-semibold">Why: </span>
                    {recommendation.why}
                </p>
                <p className="text-text-secondary">
                    <span className="text-primary font-semibold">
                        Operational impact:{' '}
                    </span>
                    {recommendation.operational_impact}
                </p>
                <p className="text-text-secondary">
                    <span className="text-primary font-semibold">
                        Confirm:{' '}
                    </span>
                    {recommendation.confirmation}
                </p>
            </div>
        </div>
    );
}

function HourlyProfile({
    profile,
}: {
    profile: ViewModel['evidence']['profile'];
}) {
    const max = Math.max(...profile.points.map((point) => point.kwh), 1);
    if (!profile.points.length)
        return (
            <p className="text-text-secondary text-sm">
                A chart is not available for this historical report.
            </p>
        );
    return (
        <div>
            <div
                className="flex h-36 items-end gap-px"
                aria-label={`${profile.title}: ${profile.unit}`}
            >
                {profile.points.map((point) => (
                    <div
                        key={point.hour}
                        title={`${point.hour}: ${point.kwh} kWh`}
                        className="bg-accent/70 min-w-0 flex-1 rounded-t"
                        style={{
                            height: `${Math.max(2, (point.kwh / max) * 100)}%`,
                        }}
                    />
                ))}
            </div>
            <div className="text-text-secondary mt-2 flex justify-between text-[10px]">
                <span>00:00</span>
                <span>06:00</span>
                <span>12:00</span>
                <span>18:00</span>
                <span>23:00</span>
            </div>
        </div>
    );
}

export default function ReportDetail({
    assessment_id: assessmentId,
    vm,
}: {
    assessment_id: string;
    vm: ViewModel;
}) {
    const base = usePortalBasePath();
    const priority = vm.opportunity.priority_action;
    return (
        <>
            <Head title={vm.cover.report_title} />
            <PortalPageHeader
                title={vm.cover.report_title}
                meta={vm.cover.period_label}
            />
            <div className="mb-5 flex items-center justify-between gap-3">
                <span className="text-text-secondary text-xs">
                    Generated {vm.cover.generated_label}
                </span>
                <a
                    href={`${base}/reports/${assessmentId}/pdf`}
                    className="text-accent shrink-0 text-xs font-semibold hover:underline"
                >
                    Download PDF
                </a>
            </div>
            <SectionCard title="Savings opportunity">
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <p className="text-text-secondary text-sm">
                        {vm.opportunity.meaning}
                    </p>
                    <Badge
                        variant={
                            vm.opportunity.status === 'Ready to review'
                                ? 'cheap'
                                : 'mid'
                        }
                        showGlyph={false}
                    >
                        {vm.opportunity.status}
                    </Badge>
                </div>
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {vm.opportunity.kpis.map((kpi) => (
                        <div
                            key={kpi.label}
                            className="border-border rounded-control border p-3"
                        >
                            <p className="text-text-secondary text-xs font-medium">
                                {kpi.label}
                            </p>
                            <p className="text-primary mt-1 text-xl font-semibold tabular-nums">
                                {kpi.value}
                            </p>
                        </div>
                    ))}
                </div>
                <p className="text-text-secondary mt-4 text-xs">
                    {vm.opportunity.scope_note}
                </p>
            </SectionCard>
            {priority && (
                <SectionCard title="Highest-priority action">
                    <RecommendationCard recommendation={priority} />
                </SectionCard>
            )}
            <SectionCard title="Recommended changes">
                {vm.recommendations.items.length ? (
                    <div className="space-y-3">
                        {vm.recommendations.items.map((item, index) => (
                            <RecommendationCard
                                key={`${item.asset}-${index}`}
                                recommendation={item}
                            />
                        ))}
                    </div>
                ) : (
                    <p className="text-text-secondary text-sm">
                        {vm.recommendations.empty_message}
                    </p>
                )}
                {vm.recommendations.additional_count > 0 && (
                    <p className="text-text-secondary mt-3 text-xs">
                        {vm.recommendations.additional_count} additional
                        recommendation
                        {vm.recommendations.additional_count === 1
                            ? ''
                            : 's'}{' '}
                        are available after these priority actions.
                    </p>
                )}
            </SectionCard>
            <SectionCard title="Evidence for this estimate">
                <h3 className="text-primary mb-1 text-sm font-semibold">
                    {vm.evidence.profile.title}
                </h3>
                <p className="text-text-secondary mb-3 text-xs">
                    {vm.evidence.profile.unit} · Finnish local time
                </p>
                <HourlyProfile profile={vm.evidence.profile} />
                <div className="mt-5 grid gap-3 sm:grid-cols-2">
                    {vm.evidence.cost_comparison.map((item) => (
                        <div
                            key={item.label}
                            className="bg-surface rounded-control p-3"
                        >
                            <p className="text-text-secondary text-xs">
                                {item.label}
                            </p>
                            <p className="text-primary mt-1 font-semibold tabular-nums">
                                {item.value}
                            </p>
                        </div>
                    ))}
                </div>
                <p className="text-text-secondary mt-4 text-xs">
                    {vm.evidence.callout}
                </p>
            </SectionCard>
            <SectionCard title="Confidence and scope">
                <div className="mb-4 flex flex-wrap items-center gap-2">
                    <Badge
                        variant={
                            vm.confidence.label === 'High' ? 'cheap' : 'mid'
                        }
                        showGlyph={false}
                    >
                        {vm.confidence.label} confidence
                    </Badge>
                    <p className="text-text-secondary text-sm">
                        {vm.confidence.reason}
                    </p>
                </div>
                <dl className="border-border divide-border grid divide-y border-y text-sm">
                    {vm.confidence.information.map((item) => (
                        <div
                            key={item.label}
                            className="grid gap-1 py-2.5 sm:grid-cols-3"
                        >
                            <dt className="text-text-secondary">
                                {item.label}
                            </dt>
                            <dd className="text-primary sm:col-span-2">
                                {item.value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <div className="mt-4 space-y-3 text-sm">
                    <p className="text-text-secondary">
                        <span className="text-primary font-semibold">
                            Included:{' '}
                        </span>
                        {vm.confidence.included}
                    </p>
                    <p className="text-text-secondary">
                        <span className="text-primary font-semibold">
                            Confirm before action:{' '}
                        </span>
                        {vm.confidence.confirm_before_action}
                    </p>
                    <p className="text-text-secondary text-xs">
                        {vm.confidence.ending_note}
                    </p>
                </div>
            </SectionCard>
        </>
    );
}

ReportDetail.layout = (page: ReactElement) => (
    <PortalLayout>{page}</PortalLayout>
);
