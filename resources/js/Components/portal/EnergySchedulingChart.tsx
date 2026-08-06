import { Card } from '@/Components/ui/Card';
import type { EChartsOption } from 'echarts';
import ReactECharts from 'echarts-for-react';
import { memo } from 'react';

export interface EnergyPricePoint {
    hour_start: string;
    eur_per_mwh: number;
}

function timeFormatter(timeZone: string): Intl.DateTimeFormat {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

function dateTimeFormatter(timeZone: string): Intl.DateTimeFormat {
    return new Intl.DateTimeFormat('en-GB', {
        timeZone,
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    });
}

function formatEur(value: number): string {
    return new Intl.NumberFormat('en-FI', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

export default memo(function EnergySchedulingChart({
    prices,
    dateLabel,
    timeZone,
}: {
    prices: EnergyPricePoint[];
    dateLabel: string;
    timeZone: string;
}) {
    if (prices.length === 0) {
        return (
            <Card className="mb-6">
                <h3 className="font-display text-primary text-base font-semibold">
                    Day-ahead electricity price — {dateLabel}
                </h3>
                <p className="text-text-secondary py-6 text-center text-sm">
                    Day-ahead prices appear here once price data is available.
                </p>
            </Card>
        );
    }

    const orderedPrices = [...prices].sort(
        (a, b) => Date.parse(a.hour_start) - Date.parse(b.hour_start),
    );
    const firstHour = Date.parse(orderedPrices[0].hour_start);
    const lastHour = Date.parse(
        orderedPrices[orderedPrices.length - 1].hour_start,
    );
    const time = timeFormatter(timeZone);
    const dateTime = dateTimeFormatter(timeZone);

    const option: EChartsOption = {
        animationDuration: 250,
        aria: {
            enabled: true,
            description:
                'Day-ahead electricity prices in euros per megawatt-hour.',
        },
        grid: { left: 66, right: 28, top: 24, bottom: 40 },
        tooltip: {
            trigger: 'axis',
            backgroundColor: '#0f172a',
            borderWidth: 0,
            textStyle: { color: '#ffffff' },
            formatter: (params: any) => {
                const point = params[0]?.data as [number, number] | undefined;
                if (!point) return '';

                return `${dateTime.format(point[0])}–${time.format(point[0] + 60 * 60 * 1000)}<br/><b>${formatEur(point[1])}/MWh</b>`;
            },
        },
        xAxis: {
            type: 'time',
            min: firstHour,
            max: lastHour + 60 * 60 * 1000,
            axisLabel: {
                formatter: (value: number) => time.format(value),
                color: '#64748b',
            },
            splitLine: {
                show: true,
                lineStyle: { color: '#e2e8f0', type: 'dashed' },
            },
            axisLine: { lineStyle: { color: '#e2e8f0' } },
            axisTick: { show: false },
        },
        yAxis: {
            type: 'value',
            name: '€/MWh',
            nameTextStyle: { color: '#64748b', padding: [0, 0, 0, 8] },
            axisLabel: { color: '#64748b' },
            splitLine: { show: true, lineStyle: { color: '#f1f5f9' } },
            axisLine: { show: false },
            axisTick: { show: false },
        },
        series: [
            {
                name: 'Day-ahead price',
                type: 'line',
                step: 'end',
                showSymbol: false,
                lineStyle: { color: '#4f46e5', width: 2 },
                data: orderedPrices.map((price) => [
                    Date.parse(price.hour_start),
                    price.eur_per_mwh,
                ]),
            },
        ],
    };

    return (
        <Card className="mb-6 overflow-hidden">
            <div className="mb-4">
                <h3 className="font-display text-primary text-base font-semibold">
                    Day-ahead electricity price — {dateLabel}
                </h3>
                <p className="text-text-secondary mt-1 text-sm">
                    Hourly electricity price in the configured schedule
                    timezone.
                </p>
            </div>
            <div className="overflow-x-auto">
                <div className="min-w-[620px]">
                    <ReactECharts
                        option={option}
                        style={{ height: 320, width: '100%' }}
                        opts={{ renderer: 'svg' }}
                        notMerge
                        lazyUpdate
                    />
                </div>
            </div>
        </Card>
    );
});
