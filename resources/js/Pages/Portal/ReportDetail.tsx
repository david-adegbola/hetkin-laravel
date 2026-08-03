import PortalPageHeader from '@/Components/portal/PortalPageHeader';
import { Card } from '@/Components/ui/Card';
import PortalLayout from '@/Layouts/PortalLayout';
import { usePortalBasePath } from '@/lib/subdomain';
import { Head } from '@inertiajs/react';
import { ReactElement, ReactNode } from 'react';

interface Kpi {
    label: string;
    value: string;
    tone: 'cheap' | 'mid' | 'expensive' | 'neutral';
}

interface Recommendation {
    title: string;
    rationale: string;
    savings_label: string;
    complexity: string;
}

interface ViewModel {
    cover: {
        organization_name: string;
        report_title: string;
        period_label: string;
        generated_label: string;
    };
    executive_summary: {
        kpis: Kpi[];
        energy_price_scope_note: string;
    };
    recommendations: {
        items: Recommendation[];
        additional_count: number;
    };
    calculation: {
        summary: string;
        estimate_note: string;
        data_used: string;
        operational_assumption: string;
        confirmation_note: string;
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
        <Card className="mb-4">
            <h2 className="font-display text-primary mb-3 text-base font-semibold">
                {title}
            </h2>
            {children}
        </Card>
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

    return (
        <>
            <Head title={vm.cover.report_title} />
            <PortalPageHeader
                title={vm.cover.report_title}
                meta={vm.cover.period_label}
            />

            <div className="mb-4 flex items-center justify-between gap-3">
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

            <SectionCard title="Estimated energy-price saving">
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    {vm.executive_summary.kpis.map((kpi) => (
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
                <p className="text-text-secondary mt-4 text-sm">
                    {vm.executive_summary.energy_price_scope_note}
                </p>
            </SectionCard>

            <SectionCard title="Recommended changes">
                {vm.recommendations.items.length > 0 ? (
                    <div className="divide-border divide-y">
                        {vm.recommendations.items.map((recommendation) => (
                            <div
                                key={recommendation.title}
                                className="grid gap-2 py-3 sm:grid-cols-[1fr_auto]"
                            >
                                <div>
                                    <p className="text-primary text-sm font-semibold">
                                        {recommendation.title}
                                    </p>
                                    <p className="text-text-secondary mt-1 text-xs">
                                        {recommendation.rationale}
                                    </p>
                                </div>
                                <div className="text-left sm:text-right">
                                    <p className="text-primary text-sm font-semibold tabular-nums">
                                        {recommendation.savings_label}
                                    </p>
                                    <p className="text-text-secondary mt-1 text-xs">
                                        Operational risk:{' '}
                                        {recommendation.complexity.toLowerCase()}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-text-secondary text-sm">
                        No recommended changes are ready yet. Add or confirm a
                        production schedule to generate actionable timing
                        changes.
                    </p>
                )}
                {vm.recommendations.additional_count > 0 && (
                    <p className="text-text-secondary mt-3 text-xs">
                        {vm.recommendations.additional_count} additional
                        recommendation
                        {vm.recommendations.additional_count === 1
                            ? ''
                            : 's'}{' '}
                        are available after these highest-value changes.
                    </p>
                )}
            </SectionCard>

            <SectionCard title="How this estimate was calculated">
                <p className="text-text-secondary text-sm">
                    {vm.calculation.summary}
                </p>
                <ul className="text-text-secondary mt-3 list-disc space-y-1 pl-4 text-sm">
                    <li>{vm.calculation.data_used}</li>
                    <li>{vm.calculation.operational_assumption}</li>
                </ul>
                <p className="text-text-secondary mt-3 text-xs">
                    {vm.calculation.estimate_note}
                </p>
            </SectionCard>

            <SectionCard title="What needs to be confirmed">
                <p className="text-text-secondary text-sm">
                    {vm.calculation.confirmation_note}
                </p>
            </SectionCard>
        </>
    );
}

ReportDetail.layout = (page: ReactElement) => (
    <PortalLayout>{page}</PortalLayout>
);
