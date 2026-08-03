<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $vm['cover']['report_title'] }}</title>
<style>
    @page { margin: 68px 52px 58px; font-family: DejaVu Sans, sans-serif; }
    body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.5; }
    .header { position: fixed; top: -48px; left: 0; right: 0; border-bottom: 1px solid #e5e7eb; color: #64748b; font-size: 9px; padding-bottom: 7px; }
    h1 { color: #172033; font-size: 28px; line-height: 1.15; margin: 0 0 10px; }
    h2 { border-bottom: 2px solid #4f46e5; color: #172033; font-size: 17px; margin: 28px 0 12px; padding-bottom: 5px; }
    h3 { color: #172033; font-size: 11px; margin: 0 0 3px; }
    p { margin: 0 0 9px; }
    .muted { color: #64748b; }
    .cover { min-height: 145px; padding-top: 48px; text-align: center; }
    .cover .company { color: #4f46e5; font-size: 16px; font-weight: bold; }
    .kpis { border-collapse: separate; border-spacing: 8px; margin: 6px -8px 12px; width: calc(100% + 16px); }
    .kpis td { border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; vertical-align: top; width: 25%; }
    .kpi-label { color: #64748b; font-size: 8px; font-weight: bold; text-transform: uppercase; }
    .kpi-value { color: #172033; font-size: 16px; font-weight: bold; margin-top: 5px; }
    .scope, .confirmation { background: #f5f5ff; border-left: 3px solid #4f46e5; color: #334155; padding: 10px 12px; }
    .recommendation { border-bottom: 1px solid #e5e7eb; padding: 10px 0; }
    .recommendation:last-child { border-bottom: 0; }
    .recommendation-table { width: 100%; border-collapse: collapse; }
    .recommendation-saving { text-align: right; vertical-align: top; width: 150px; }
    .saving { color: #172033; font-weight: bold; }
    ul { margin: 8px 0 0 18px; padding: 0; }
    li { margin-bottom: 5px; }
</style>
</head>
<body>
<div class="header">{{ $vm['cover']['organization_name'] }}</div>

<div class="cover">
    <p class="company">{{ $vm['cover']['organization_name'] }}</p>
    <h1>Savings Assessment</h1>
    <p class="muted">{{ $vm['cover']['period_label'] }}</p>
    <p class="muted">Generated {{ $vm['cover']['generated_label'] }}</p>
</div>

<h2>Estimated energy-price saving</h2>
<table class="kpis">
    <tr>
        @foreach ($vm['executive_summary']['kpis'] as $kpi)
            <td>
                <div class="kpi-label">{{ $kpi['label'] }}</div>
                <div class="kpi-value">{{ $kpi['value'] }}</div>
            </td>
        @endforeach
    </tr>
</table>
<p class="scope">{{ $vm['executive_summary']['energy_price_scope_note'] }}</p>

<h2>Recommended changes</h2>
@forelse ($vm['recommendations']['items'] as $recommendation)
    <div class="recommendation">
        <table class="recommendation-table">
            <tr>
                <td>
                    <h3>{{ $recommendation['title'] }}</h3>
                    <p class="muted">{{ $recommendation['rationale'] }}</p>
                </td>
                <td class="recommendation-saving">
                    <p class="saving">{{ $recommendation['savings_label'] }}</p>
                    <p class="muted">Operational risk: {{ strtolower($recommendation['complexity']) }}</p>
                </td>
            </tr>
        </table>
    </div>
@empty
    <p class="muted">No recommended changes are ready yet. Add or confirm a production schedule to generate actionable timing changes.</p>
@endforelse
@if ($vm['recommendations']['additional_count'] > 0)
    <p class="muted">{{ $vm['recommendations']['additional_count'] }} additional recommendation{{ $vm['recommendations']['additional_count'] === 1 ? '' : 's' }} are available after these highest-value changes.</p>
@endif

<h2>How this estimate was calculated</h2>
<p>{{ $vm['calculation']['summary'] }}</p>
<ul class="muted">
    <li>{{ $vm['calculation']['data_used'] }}</li>
    <li>{{ $vm['calculation']['operational_assumption'] }}</li>
</ul>
<p class="muted" style="margin-top: 10px;">{{ $vm['calculation']['estimate_note'] }}</p>

<h2>What needs to be confirmed</h2>
<p class="confirmation">{{ $vm['calculation']['confirmation_note'] }}</p>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
    $pdf->page_text(180, 806, '{{ addslashes($vm['cover']['organization_name']) }} · Confidential · Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.39, 0.45, 0.54]);
}
</script>
</body>
</html>
