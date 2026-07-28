<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $vm['cover']['report_title'] }}</title>
<style>
    @page {
        margin: 90px 50px 60px 50px;
        font-family: DejaVu Sans, sans-serif;
    }

    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a2e; }
    .page-header { position: fixed; top: -70px; left: 0px; right: 0px; font-size: 9px; color: #999; border-bottom: 1px solid #eee; padding-bottom: 4px; }
    .page-footer { position: fixed; bottom: -50px; left: 0px; right: 0px; font-size: 9px; color: #999; border-top: 1px solid #eee; padding-top: 4px; text-align: center; }
    h1 { font-size: 22px; margin: 0 0 6px 0; color: #1a1a2e; }
    h2 { font-size: 16px; margin: 0 0 12px 0; color: #1a1a2e; border-bottom: 2px solid #4f46e5; padding-bottom: 4px; }
    h3 { font-size: 12px; margin: 14px 0 6px 0; color: #4f46e5; }
    p { line-height: 1.5; margin: 0 0 8px 0; }
    .section { page-break-after: always; }
    .section:last-child { page-break-after: avoid; }
    .muted { color: #666; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .badge-cheap { background: #dcfce7; color: #166534; }
    .badge-mid { background: #fef3c7; color: #92400e; }
    .badge-expensive { background: #fee2e2; color: #991b1b; }
    .badge-neutral { background: #e5e7eb; color: #374151; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    th, td { text-align: left; padding: 5px 6px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
    th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #555; }
    .kpi-table td { border: none; padding: 8px; }
    .kpi-box { background: #f5f5ff; border-radius: 6px; padding: 8px; }
    .kpi-label { font-size: 9px; color: #666; text-transform: uppercase; }
    .kpi-value { font-size: 16px; font-weight: bold; color: #1a1a2e; }
    .chart { position: relative; height: 110px; margin: 10px 0 20px 0; border-bottom: 1px solid #ccc; }
    .bar { position: absolute; bottom: 0; background: #4f46e5; }
    .bar-cheap { background: #22c55e; }
    .bar-mid { background: #f59e0b; }
    .bar-expensive { background: #ef4444; }
    .heat-row td { text-align: center; font-size: 8px; color: #fff; padding: 6px 0; }
    .heat-cheap { background: #22c55e; }
    .heat-mid { background: #f59e0b; }
    .heat-expensive { background: #ef4444; }
    .heat-unknown { background: #d1d5db; color: #555; }
    .cover { text-align: center; padding-top: 140px; }
    .cover h1 { font-size: 30px; }
    ul { margin: 0 0 10px 18px; padding: 0; }
    li { margin-bottom: 4px; line-height: 1.4; }
    .schedule-row { position: relative; height: 22px; margin-bottom: 6px; background: #f3f4f6; border-radius: 3px; }
    .schedule-bar-fill { position: absolute; top: 0; height: 22px; border-radius: 3px; }
</style>
</head>
<body>

<div class="page-header">Hetkin Oy</div>

<div class="page-footer">
    Hetkin Oy &middot; {{ $vm['cover']['organization_name'] }} &middot; Confidential &middot; Page {PAGE_NUM} of {PAGE_COUNT} &middot; v{{ $vm['cover']['version'] }}
</div>

{{-- Cover --}}
<div class="section cover">
    <h1>{{ $vm['cover']['report_title'] }}</h1>
    <p style="font-size: 16px;">{{ $vm['cover']['organization_name'] }}</p>
    <p class="muted">{{ $vm['cover']['period_label'] }}</p>
    <p class="muted">{{ $vm['cover']['generated_label'] }}</p>
    <p class="muted">Version {{ $vm['cover']['version'] }}</p>
    @if ($vm['cover']['is_provisional'])
        <p><span class="badge badge-mid">Provisional estimate</span></p>
    @endif
</div>

{{-- Executive summary --}}
<div class="section">
    <h2>Executive Summary</h2>
    <p>{{ $vm['executive_summary']['situation'] }}</p>

    <table class="kpi-table">
        <tr>
            @foreach ($vm['executive_summary']['kpis'] as $kpi)
                <td style="width: 25%;">
                    <div class="kpi-box">
                        <div class="kpi-label">{{ $kpi['label'] }}</div>
                        <div class="kpi-value">{{ $kpi['value'] }}</div>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    <h3>Savings waterfall</h3>
    @include('pdf.partials.waterfall', ['waterfall' => $vm['executive_summary']['waterfall_thumbnail']])

    @if (count($vm['executive_summary']['key_recommendations']) > 0)
        <h3>Key recommendations</h3>
        <ul>
            @foreach ($vm['executive_summary']['key_recommendations'] as $rec)
                <li>{{ $rec }}</li>
            @endforeach
        </ul>
    @endif

    <h3>Return on Hetkin's fee</h3>
    <p>{{ $vm['executive_summary']['roi_label'] }}</p>

    @if ($vm['executive_summary']['status_note'])
        <p class="muted">{{ $vm['executive_summary']['status_note'] }}</p>
    @endif

    <h3>Next steps</h3>
    <ul>
        @foreach ($vm['executive_summary']['next_steps'] as $step)
            <li>{{ $step }}</li>
        @endforeach
    </ul>
</div>

{{-- Customer overview --}}
<div class="section">
    <h2>Customer Overview</h2>
    <table>
        <tr><th>Company</th><td>{{ $vm['customer_overview']['company_name'] }}</td></tr>
        <tr><th>Process type</th><td>{{ $vm['customer_overview']['process_type_label'] }}</td></tr>
        <tr><th>Reporting period</th><td>{{ $vm['customer_overview']['period_label'] }}</td></tr>
        <tr><th>Data coverage</th><td>
            {{ $vm['customer_overview']['data_coverage']['hour_count'] }} hourly readings
            @if ($vm['customer_overview']['data_coverage']['expected_hour_count'])
                of {{ $vm['customer_overview']['data_coverage']['expected_hour_count'] }} expected
            @endif
        </td></tr>
        <tr><th>Production profile</th><td>{{ $vm['customer_overview']['production_profile'] }}</td></tr>
    </table>

    <h3>Metering points</h3>
    <table>
        <thead><tr><th>Label</th><th>Site</th><th>Data source</th><th>Status</th></tr></thead>
        <tbody>
            @forelse ($vm['customer_overview']['metering_points'] as $mp)
                <tr>
                    <td>{{ $mp['label'] }}</td>
                    <td>{{ $mp['site_name'] }}</td>
                    <td>{{ $mp['data_source'] }}</td>
                    <td>{{ $mp['healthy'] ? 'Healthy' : 'Needs attention' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No metering points on record.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Baseline analysis --}}
<div class="section">
    <h2>Baseline Analysis</h2>
    <p>Current annual cost: <strong>{{ $vm['baseline']['current_annual_cost_label'] }}</strong></p>
    <p>{{ $vm['baseline']['cost_driver_note'] }}</p>

    <h3>Average load profile (kWh by hour of day, UTC)</h3>
    @php $maxKwh = max(1, ...array_column($vm['baseline']['avg_load_profile'], 'avg_kwh')); @endphp
    <div class="chart">
        @foreach ($vm['baseline']['avg_load_profile'] as $point)
            <div class="bar" style="left: {{ $point['hour'] * (100 / 24) }}%; width: {{ 100 / 24 - 0.4 }}%; height: {{ max(1, $point['avg_kwh'] / $maxKwh * 100) }}%;"></div>
        @endforeach
    </div>
</div>

{{-- Market analysis --}}
<div class="section">
    <h2>Electricity Market Analysis</h2>
    <p>Price range this period: {{ $vm['market']['min_price_label'] }} &ndash; {{ $vm['market']['max_price_label'] }}</p>
    <p>{{ $vm['market']['volatility_note'] }}</p>

    <h3>Average price by hour of day, UTC</h3>
    <table>
        <tr class="heat-row">
            @foreach ($vm['market']['heatmap'] as $cell)
                <td class="heat-{{ $cell['tier'] }}">{{ $cell['hour'] }}</td>
            @endforeach
        </tr>
    </table>

    @if (count($vm['market']['trend']) > 0)
        <h3>Daily average price trend</h3>
        <table>
            <thead><tr><th>Day</th><th>Avg &euro;/MWh</th></tr></thead>
            <tbody>
                @foreach ($vm['market']['trend'] as $point)
                    <tr><td>{{ $point['label'] }}</td><td>{{ number_format($point['avg_eur_per_mwh'], 2) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- Savings assessment --}}
<div class="section">
    <h2>Savings Assessment</h2>
    @include('pdf.partials.waterfall', ['waterfall' => $vm['savings']['waterfall']])
    <table>
        <tbody>
            @foreach ($vm['savings']['table'] as [$label, $value])
                <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Schedule --}}
<div class="section">
    <h2>Production Schedule Optimization</h2>

    <h3>Current schedule</h3>
    @forelse ($vm['schedule']['current'] as $bar)
        <div class="schedule-row">
            <div class="schedule-bar-fill heat-{{ $bar['tier'] }}" style="left: {{ $bar['start_hour'] / 24 * 100 }}%; width: {{ ($bar['end_hour'] - $bar['start_hour']) / 24 * 100 }}%;"></div>
        </div>
        <p class="muted" style="margin-top: -4px;">{{ $bar['asset_label'] }}</p>
    @empty
        <p class="muted">No production schedule on record.</p>
    @endforelse

    <h3>Recommended schedule</h3>
    @forelse ($vm['schedule']['recommended'] as $bar)
        <div class="schedule-row">
            <div class="schedule-bar-fill heat-{{ $bar['tier'] }}" style="left: {{ $bar['start_hour'] / 24 * 100 }}%; width: {{ ($bar['end_hour'] - $bar['start_hour']) / 24 * 100 }}%;"></div>
        </div>
        <p class="muted" style="margin-top: -4px;">{{ $bar['asset_label'] }}</p>
    @empty
        <p class="muted">No recommendations on record.</p>
    @endforelse

    <h3>Notes</h3>
    <ul>
        @foreach ($vm['schedule']['callouts'] as $callout)
            <li>{{ $callout }}</li>
        @endforeach
    </ul>
</div>

{{-- Recommendations --}}
<div class="section">
    <h2>Recommendation Summary</h2>
    <table>
        <thead><tr><th>Change</th><th>Rationale</th><th>Savings</th><th>Delivery risk</th></tr></thead>
        <tbody>
            @forelse ($vm['recommendations'] as $rec)
                <tr>
                    <td>{{ $rec['title'] }}</td>
                    <td>{{ $rec['rationale'] }}</td>
                    <td>{{ $rec['savings_label'] }}</td>
                    <td>{{ $rec['complexity'] }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No recommendations on record.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Methodology --}}
<div class="section">
    <h2>Methodology</h2>
    <table>
        <tr><th>Strategy</th><td>{{ $vm['methodology']['strategy_label'] }}</td></tr>
        <tr><th>Status</th><td>{{ $vm['methodology']['status_label'] }}</td></tr>
        <tr><th>Sample days</th><td>{{ $vm['methodology']['sample_day_count'] ?? 'n/a' }}</td></tr>
        <tr><th>Coverage</th><td>{{ $vm['methodology']['coverage_note'] }}</td></tr>
        <tr><th>Price source</th><td>{{ $vm['methodology']['price_source'] }}</td></tr>
    </table>

    @if (count($vm['methodology']['assumptions']) > 0)
        <h3>Assumptions</h3>
        <table>
            <tbody>
                @foreach ($vm['methodology']['assumptions'] as [$label, $value])
                    <tr><th>{{ $label }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>Exclusions</h3>
    <ul>
        @foreach ($vm['methodology']['exclusions'] as $exclusion)
            <li>{{ $exclusion }}</li>
        @endforeach
    </ul>
</div>

{{-- Appendix --}}
<div class="section">
    <h2>Appendix</h2>
    <h3>Glossary</h3>
    <table>
        <tbody>
            @foreach ($vm['appendix']['glossary'] as [$term, $definition])
                <tr><th style="width: 25%;">{{ $term }}</th><td>{{ $definition }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <p class="muted">{{ $vm['appendix']['version_history'] }}</p>
    <p class="muted">Questions? Contact {{ $vm['appendix']['contact_email'] }}</p>
</div>

</body>
</html>
