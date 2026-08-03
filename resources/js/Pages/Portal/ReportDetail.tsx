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

interface WaterfallStep {
    label: string;
    delta_cents: number;
    cumulative_cents: number;
    kind: 'start' | 'decrease' | 'end';
}

interface HourCell {
    hour: number;
    avg_eur_per_mwh: number | null;
    tier: string;
}

interface ScheduleBar {
    asset_label: string;
    date_label: string;
    time_label: string;
    start_hour: number;
    end_hour: number;
    tier: string;
}

interface RecommendationRow {
    title: string;
    rationale: string;
    savings_label: string;
    complexity: string;
    business_impact: string;
}

interface ViewModel {
    cover: {
        organization_name: string;
        report_title: string;
        period_label: string;
        generated_label: string;
    };
    executive_summary: {
        situation: string;
        kpis: Kpi[];
        waterfall_thumbnail: WaterfallStep[];
        fee_configured: boolean;
        confidence_badge: string | null;
        confidence_note: string | null;
        assumption_summary: string;
        key_recommendations: string[];
        roi_label: string;
        status_note: string | null;
        next_steps: string[];
    };
    customer_overview: {
        company_name: string;
        process_type_label: string;
        period_label: string;
        metering_points: Array<{
            label: string;
            site_name: string;
            data_source: string;
            status_label: string;
        }>;
        data_coverage: {
            day_count: number | null;
            hour_count: number;
            expected_hour_count: number | null;
        };
        production_profile: string;
        production_data_status: string;
    };
    baseline: {
        current_annual_cost_label: string;
        avg_load_profile: Array<{ hour: number; avg_kwh: number }>;
        peak_cost_hours: number[];
        cost_driver_note: string;
    };
    market: {
        heatmap: HourCell[];
        min_price_label: string;
        max_price_label: string;
        volatility_note: string;
        trend: Array<{ label: string; avg_eur_per_mwh: number }>;
        trend_mode: 'table' | 'chart';
        negative_price_note: string | null;
        sensitivity_note: string | null;
    };
    savings: {
        waterfall: WaterfallStep[];
        fee_configured: boolean;
        table: Array<[string, string]>;
    };
    schedule: {
        current: ScheduleBar[];
        recommended: ScheduleBar[];
        callouts: string[];
    };
    recommendations: RecommendationRow[];
    calculation: {
        summary: string;
        estimate_note: string;
        coverage_note: string;
        price_source: string;
        assumptions: Array<[string, string]>;
        exclusions: string[];
    };
}

const TIER_BG: Record<string, string> = {
    cheap: 'bg-success',
    mid: 'bg-warning',
    expensive: 'bg-error',
    unknown: 'bg-border',
};

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

function Waterfall({ steps }: { steps: WaterfallStep[] }) {
    const maxAbs = Math.max(
        1,
        ...steps.map((s) => Math.abs(s.cumulative_cents)),
    );

    return (
        <div className="flex flex-col gap-2">
            {steps.map((step) => (
                <div key={step.label} className="flex items-center gap-3">
                    <div className="text-text-secondary w-40 shrink-0 text-xs">
                        {step.label}
                    </div>
                    <div className="bg-border/50 h-3 flex-1 rounded-full">
                        <div
                            className={`h-3 rounded-full ${step.kind === 'decrease' ? 'bg-error' : 'bg-accent'}`}
                            style={{
                                width: `${Math.max(2, (Math.abs(step.cumulative_cents) / maxAbs) * 100)}%`,
                            }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

function LoadProfileChart({
    profile,
}: {
    profile: Array<{ hour: number; avg_kwh: number }>;
}) {
    const max = Math.max(1, ...profile.map((p) => p.avg_kwh));

    return (
        <div className="border-border relative flex h-28 items-end gap-px border-b">
            {profile.map((p) => (
                <div
                    key={p.hour}
                    className="bg-accent/70 flex-1"
                    style={{
                        height: `${Math.max(2, (p.avg_kwh / max) * 100)}%`,
                    }}
                    title={`${p.hour}:00 — ${p.avg_kwh.toFixed(1)} kWh`}
                />
            ))}
        </div>
    );
}

function Heatmap({ cells }: { cells: HourCell[] }) {
    return (
        <div className="flex gap-px">
            {cells.map((c) => (
                <div
                    key={c.hour}
                    className={`flex-1 py-2 text-center text-[9px] text-white ${TIER_BG[c.tier] ?? TIER_BG.unknown}`}
                    title={
                        c.avg_eur_per_mwh !== null
                            ? `${c.hour}:00 — €${c.avg_eur_per_mwh.toFixed(0)}/MWh`
                            : `${c.hour}:00 — no data`
                    }
                >
                    {c.hour}
                </div>
            ))}
        </div>
    );
}

function ScheduleBars({ bars }: { bars: ScheduleBar[] }) {
    if (bars.length === 0) {
        return (
            <p className="text-text-secondary text-xs">Nothing on record.</p>
        );
    }

    return (
        <div className="flex flex-col gap-2">
            {bars.map((bar, i) => (
                <div key={i}>
                    <div className="bg-border/40 relative h-5 rounded">
                        <div
                            className={`absolute top-0 h-5 rounded ${TIER_BG[bar.tier] ?? TIER_BG.unknown}`}
                            style={{
                                left: `${(bar.start_hour / 24) * 100}%`,
                                width: `${((bar.end_hour - bar.start_hour) / 24) * 100}%`,
                            }}
                        />
                    </div>
                    <div className="text-text-secondary mt-0.5 text-xs">
                        {bar.asset_label}
                        <span className="ml-1">
                            · {bar.date_label} · {bar.time_label}
                        </span>
                    </div>
                </div>
            ))}
        </div>
    );
}

function DailyTrend({
    trend,
    mode,
}: {
    trend: ViewModel['market']['trend'];
    mode: ViewModel['market']['trend_mode'];
}) {
    if (trend.length === 0) return null;

    if (mode === 'table') {
        return (
            <table className="mt-3 w-full text-left text-xs">
                <thead className="border-border text-text-secondary border-b uppercase">
                    <tr>
                        <th className="py-1">Day</th>
                        <th className="py-1">Avg. €/MWh</th>
                    </tr>
                </thead>
                <tbody>
                    {trend.map((point) => (
                        <tr
                            key={point.label}
                            className="border-border border-b"
                        >
                            <td className="py-1">{point.label}</td>
                            <td className="py-1">
                                {point.avg_eur_per_mwh.toLocaleString('fi-FI', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                })}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        );
    }

    const max = Math.max(
        1,
        ...trend.map((point) => Math.abs(point.avg_eur_per_mwh)),
    );
    return (
        <div
            className="border-border mt-3 flex h-28 items-end gap-px border-b"
            aria-label="Daily average price trend"
        >
            {trend.map((point) => (
                <div
                    key={point.label}
                    className="bg-accent flex-1"
                    style={{
                        height: `${Math.max(2, (Math.abs(point.avg_eur_per_mwh) / max) * 100)}%`,
                    }}
                    title={`${point.label}: ${point.avg_eur_per_mwh.toLocaleString('fi-FI', { maximumFractionDigits: 1 })} €/MWh`}
                />
            ))}
        </div>
    );
}

/**
 * Mirrors the Next.js app's src/app/(portal)/dashboard/reports/
 * [assessmentId]/page.tsx — same customer report sections, same view-model
 * (App\Domain\Billing\Reports\SavingsReportViewModelBuilder produces both
 * this page's props and the PDF's data), simplified chart rendering (CSS
 * bars/heatmap rather than hand-tuned SVG) since the customer-visible
 * contract is the numbers, not the pixels.
 */
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
                meta={`${vm.cover.organization_name} · ${vm.cover.period_label}`}
            />

            <div className="mb-4 flex items-center justify-between">
                <div className="text-text-secondary text-xs">
                    {vm.cover.generated_label}
                </div>
                <a
                    href={`${base}/reports/${assessmentId}/pdf`}
                    className="text-accent text-xs font-semibold hover:underline"
                >
                    Download PDF ↓
                </a>
            </div>

            <SectionCard title="Executive Summary">
                <p className="text-text-secondary mb-4 text-sm">
                    {vm.executive_summary.situation}
                </p>
                {vm.executive_summary.confidence_badge && (
                    <div className="bg-warning/15 text-warning mb-3 inline-block rounded-full px-2 py-1 text-xs font-medium">
                        {vm.executive_summary.confidence_badge}
                    </div>
                )}
                <p className="text-text-secondary mb-3 text-xs">
                    {vm.executive_summary.assumption_summary}
                </p>
                <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {vm.executive_summary.kpis.map((kpi) => (
                        <div
                            key={kpi.label}
                            className="bg-accent-subtle rounded-control p-3"
                        >
                            <div className="text-text-secondary text-[10px] uppercase">
                                {kpi.label}
                            </div>
                            <div className="font-display text-primary text-lg font-bold">
                                {kpi.value}
                            </div>
                        </div>
                    ))}
                </div>
                {vm.executive_summary.confidence_note && (
                    <p className="rounded-control bg-warning/10 text-warning mb-3 p-3 text-xs">
                        {vm.executive_summary.confidence_note}
                    </p>
                )}
                {vm.executive_summary.fee_configured ? (
                    <Waterfall
                        steps={vm.executive_summary.waterfall_thumbnail}
                    />
                ) : (
                    <p className="text-text-secondary text-xs">
                        Hetkin’s fee has not been configured for this customer
                        yet — the savings breakdown will appear once it is.
                    </p>
                )}
                {vm.executive_summary.key_recommendations.length > 0 && (
                    <div className="mt-4">
                        <h3 className="text-primary mb-1 text-xs font-semibold">
                            Key recommendations
                        </h3>
                        <ul className="text-text-secondary list-disc pl-4 text-xs">
                            {vm.executive_summary.key_recommendations.map(
                                (r) => (
                                    <li key={r}>{r}</li>
                                ),
                            )}
                        </ul>
                    </div>
                )}
                <p className="text-text-secondary mt-3 text-xs">
                    {vm.executive_summary.roi_label}
                </p>
                {vm.executive_summary.status_note && (
                    <p className="text-warning mt-2 text-xs">
                        {vm.executive_summary.status_note}
                    </p>
                )}
            </SectionCard>

            <SectionCard title="Customer Overview">
                <table className="w-full text-left text-sm">
                    <tbody>
                        <tr className="border-border border-b">
                            <th className="text-text-secondary w-40 py-1.5 text-xs font-medium">
                                Company
                            </th>
                            <td className="py-1.5">
                                {vm.customer_overview.company_name}
                            </td>
                        </tr>
                        <tr className="border-border border-b">
                            <th className="text-text-secondary py-1.5 text-xs font-medium">
                                Production profile
                            </th>
                            <td className="py-1.5">
                                {vm.customer_overview.production_profile}
                            </td>
                        </tr>
                        <tr>
                            <th className="text-text-secondary py-1.5 text-xs font-medium">
                                Production data
                            </th>
                            <td className="py-1.5">
                                {vm.customer_overview.production_data_status}
                            </td>
                        </tr>
                        <tr className="border-border border-b">
                            <th className="text-text-secondary py-1.5 text-xs font-medium">
                                Process type
                            </th>
                            <td className="py-1.5">
                                {vm.customer_overview.process_type_label}
                            </td>
                        </tr>
                        <tr>
                            <th className="text-text-secondary py-1.5 text-xs font-medium">
                                Data coverage
                            </th>
                            <td className="py-1.5">
                                {vm.customer_overview.data_coverage.hour_count}{' '}
                                hourly readings
                                {vm.customer_overview.data_coverage
                                    .expected_hour_count
                                    ? ` of ${vm.customer_overview.data_coverage.expected_hour_count} expected`
                                    : ''}
                            </td>
                        </tr>
                    </tbody>
                </table>
                {vm.customer_overview.metering_points.length > 0 && (
                    <table className="mt-3 w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs uppercase">
                            <tr>
                                <th className="py-1.5">Label</th>
                                <th className="py-1.5">Site</th>
                                <th className="py-1.5">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vm.customer_overview.metering_points.map(
                                (mp, i) => (
                                    <tr
                                        key={i}
                                        className="border-border border-b last:border-0"
                                    >
                                        <td className="py-1.5">{mp.label}</td>
                                        <td className="py-1.5">
                                            {mp.site_name}
                                        </td>
                                        <td className="py-1.5">
                                            {mp.status_label}
                                        </td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                )}
            </SectionCard>

            <SectionCard title="Baseline Analysis">
                <p className="text-text-secondary mb-3 text-sm">
                    Current annual cost:{' '}
                    <strong className="text-primary">
                        {vm.baseline.current_annual_cost_label}
                    </strong>
                </p>
                <h3 className="text-primary mb-1 text-xs font-semibold">
                    Average load profile (kWh by hour of day, Finland time)
                </h3>
                <LoadProfileChart profile={vm.baseline.avg_load_profile} />
                <p className="text-text-secondary mt-3 text-xs">
                    {vm.baseline.cost_driver_note}
                </p>
            </SectionCard>

            <SectionCard title="Electricity Market Analysis">
                <p className="text-text-secondary mb-3 text-sm">
                    Price range: {vm.market.min_price_label} –{' '}
                    {vm.market.max_price_label}
                </p>
                <Heatmap cells={vm.market.heatmap} />
                <p className="text-text-secondary mt-3 text-xs">
                    {vm.market.volatility_note}
                </p>
                {vm.market.negative_price_note && (
                    <p className="text-text-secondary mt-2 text-xs">
                        {vm.market.negative_price_note}
                    </p>
                )}
                {vm.market.sensitivity_note && (
                    <p className="rounded-control bg-warning/10 text-warning mt-2 p-2 text-xs">
                        {vm.market.sensitivity_note}
                    </p>
                )}
                <h3 className="text-primary mt-4 mb-1 text-xs font-semibold">
                    Daily average price trend
                </h3>
                <DailyTrend
                    trend={vm.market.trend}
                    mode={vm.market.trend_mode}
                />
            </SectionCard>

            <SectionCard title="Savings Assessment">
                {vm.savings.fee_configured && (
                    <p className="text-text-secondary text-xs">
                        The executive summary contains the visual savings
                        waterfall; this table provides the detailed financial
                        breakdown.
                    </p>
                )}
                <table className="mt-4 w-full text-left text-sm">
                    <tbody>
                        {vm.savings.table.map(([label, value]) => (
                            <tr
                                key={label}
                                className="border-border border-b last:border-0"
                            >
                                <th className="text-text-secondary py-1.5 text-xs font-medium">
                                    {label}
                                </th>
                                <td className="py-1.5 font-semibold">
                                    {value}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </SectionCard>

            <SectionCard title="Production Schedule Optimization">
                <h3 className="text-primary mb-1 text-xs font-semibold">
                    Current schedule
                </h3>
                <ScheduleBars bars={vm.schedule.current} />
                <h3 className="text-primary mt-4 mb-1 text-xs font-semibold">
                    Recommended schedule
                </h3>
                <ScheduleBars bars={vm.schedule.recommended} />
                <ul className="text-text-secondary mt-4 list-disc pl-4 text-xs">
                    {vm.schedule.callouts.map((c, i) => (
                        <li key={i}>{c}</li>
                    ))}
                </ul>
            </SectionCard>

            {vm.recommendations.length > 0 && (
                <SectionCard title="Recommendation Summary">
                    <table className="w-full text-left text-sm">
                        <thead className="border-border text-text-secondary border-b text-xs uppercase">
                            <tr>
                                <th className="py-1.5">Change</th>
                                <th className="py-1.5">Savings</th>
                                <th className="py-1.5">Risk</th>
                            </tr>
                        </thead>
                        <tbody>
                            {vm.recommendations.map((r, i) => (
                                <tr
                                    key={i}
                                    className="border-border border-b last:border-0"
                                >
                                    <td className="py-1.5">
                                        <div>{r.title}</div>
                                        <div className="text-text-secondary text-xs">
                                            {r.rationale}
                                        </div>
                                    </td>
                                    <td className="py-1.5 tabular-nums">
                                        {r.savings_label}
                                    </td>
                                    <td className="py-1.5">{r.complexity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </SectionCard>
            )}

            <SectionCard title="How this recommendation was calculated">
                <p className="text-text-secondary mb-2 text-sm">
                    {vm.calculation.summary}
                </p>
                <p className="text-text-secondary mb-4 text-xs">
                    {vm.calculation.estimate_note}
                </p>
                <table className="w-full text-left text-sm">
                    <tbody>
                        <tr>
                            <th className="text-text-secondary py-1.5 text-xs font-medium">
                                Data coverage
                            </th>
                            <td className="py-1.5">
                                {vm.calculation.coverage_note}
                            </td>
                        </tr>
                    </tbody>
                </table>
                {vm.calculation.assumptions.length > 0 && (
                    <>
                        <h3 className="text-primary mt-4 mb-1 text-xs font-semibold">
                            Information used
                        </h3>
                        <table className="w-full text-left text-sm">
                            <tbody>
                                {vm.calculation.assumptions.map(
                                    ([label, value]) => (
                                        <tr
                                            key={label}
                                            className="border-border border-b last:border-0"
                                        >
                                            <th className="text-text-secondary w-40 py-1 text-xs font-medium">
                                                {label}
                                            </th>
                                            <td className="py-1">{value}</td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </>
                )}
                <h3 className="text-primary mt-4 mb-1 text-xs font-semibold">
                    Not included
                </h3>
                <ul className="text-text-secondary list-disc pl-4 text-xs">
                    {vm.calculation.exclusions.map((e, i) => (
                        <li key={i}>{e}</li>
                    ))}
                </ul>
            </SectionCard>
        </>
    );
}

ReportDetail.layout = (page: ReactElement) => (
    <PortalLayout>{page}</PortalLayout>
);
