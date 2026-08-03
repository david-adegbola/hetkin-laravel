<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $vm['cover']['report_title'] }}</title>
<style>
    @page { margin: 65px 48px 56px; font-family: DejaVu Sans, sans-serif; }
    body { color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; }
    .header { position: fixed; top: -45px; left: 0; right: 0; border-bottom: 1px solid #dbe3ef; color: #64748b; font-size: 8px; padding-bottom: 7px; }
    .page { page-break-after: auto; } .page-break { page-break-after: always; }
    h1 { font-size: 30px; line-height: 1.12; margin: 0 0 9px; } h2 { color: #172033; font-size: 18px; margin: 0 0 13px; } h3 { font-size: 11px; margin: 0 0 5px; }
    p { margin: 0 0 8px; } .muted { color: #60708a; } .eyebrow { color: #4f46e5; font-size: 9px; font-weight: bold; letter-spacing: .08em; text-transform: uppercase; }
    .cover { padding-top: 105px; text-align: center; } .company { color: #4f46e5; font-size: 17px; font-weight: bold; margin-bottom: 18px; }
    .status { background: #eefbf5; border: 1px solid #b9e9cf; color: #17623b; display: inline-block; font-size: 9px; font-weight: bold; margin: 11px 0 19px; padding: 5px 9px; }
    .kpis { border-collapse: separate; border-spacing: 7px; margin: 0 -7px 14px; width: calc(100% + 14px); } .kpis td { border: 1px solid #dbe3ef; padding: 11px; vertical-align: top; width: 25%; } .kpi-label { color: #60708a; font-size: 8px; font-weight: bold; text-transform: uppercase; } .kpi-value { font-size: 16px; font-weight: bold; margin-top: 5px; }
    .note { background: #f5f7ff; border-left: 3px solid #4f46e5; color: #3f4e66; padding: 10px 12px; }
    .action { border: 1px solid #dbe3ef; margin-bottom: 10px; padding: 11px; } .action table, .timings { border-collapse: collapse; width: 100%; } .saving { color: #17623b; font-weight: bold; text-align: right; vertical-align: top; width: 145px; } .timings td { background: #f8fafc; padding: 7px; vertical-align: top; width: 50%; } .timings td + td { border-left: 6px solid white; background: #f5f7ff; }
    .small-label { color: #60708a; display: block; font-size: 8px; margin-bottom: 2px; } .details { color: #60708a; font-size: 9px; margin-top: 7px; }
    .profile { border-bottom: 1px solid #dbe3ef; height: 155px; padding: 0 2px; white-space: nowrap; } .bar-wrap { display: inline-block; height: 145px; position: relative; vertical-align: bottom; width: 4.16%; } .bar { background: #6366f1; bottom: 0; left: 1px; position: absolute; right: 1px; }
    .comparison { border-collapse: separate; border-spacing: 8px; margin: 17px -8px 12px; width: calc(100% + 16px); } .comparison td { background: #f8fafc; padding: 12px; width: 50%; }
    .info { border-collapse: collapse; width: 100%; } .info td { border-bottom: 1px solid #dbe3ef; padding: 8px 0; vertical-align: top; } .info td:first-child { color: #60708a; width: 36%; }
</style>
</head>
<body>
<div class="header">{{ $vm['cover']['organization_name'] }} · Savings Assessment</div>

<section class="page page-break cover">
    <p class="company">{{ $vm['cover']['organization_name'] }}</p>
    <p class="eyebrow">Operational Savings Diagnostic</p>
    <h1>Savings Assessment</h1>
    <p class="muted">{{ $vm['cover']['period_label'] }}</p>
    <p class="muted">Generated {{ $vm['cover']['generated_label'] }}</p>
    <span class="status">{{ $vm['opportunity']['status'] }}</span>
    <h2>{{ $vm['opportunity']['headline'] }}</h2>
    <table class="kpis"><tr>@foreach ($vm['opportunity']['kpis'] as $kpi)<td><div class="kpi-label">{{ $kpi['label'] }}</div><div class="kpi-value">{{ $kpi['value'] }}</div></td>@endforeach</tr></table>
    <p>{{ $vm['opportunity']['meaning'] }}</p><p class="note">{{ $vm['opportunity']['scope_note'] }}</p>
</section>

<section class="page page-break">
    <p class="eyebrow">What to change</p><h2>Recommended changes</h2>
    @forelse ($vm['recommendations']['items'] as $recommendation)
        <div class="action"><table><tr><td><p class="eyebrow">{{ $recommendation['priority'] }}</p><h3>{{ $recommendation['asset'] }}</h3><p class="muted">{{ $recommendation['what_to_change'] }}</p></td><td class="saving">{{ $recommendation['saving_per_occurrence'] }}@if ($recommendation['annual_contribution'])<br><span class="muted" style="font-weight: normal;">{{ $recommendation['annual_contribution'] }}</span>@endif</td></tr></table><table class="timings"><tr><td><span class="small-label">Current timing</span>{{ $recommendation['current_timing'] }}</td><td><span class="small-label">Proposed timing</span>{{ $recommendation['proposed_timing'] }}</td></tr></table><div class="details"><strong>Why:</strong> {{ $recommendation['why'] }}<br><strong>Operational impact:</strong> {{ $recommendation['operational_impact'] }}<br><strong>Confirm:</strong> {{ $recommendation['confirmation'] }}</div></div>
    @empty
        <p class="muted">{{ $vm['recommendations']['empty_message'] }}</p>
    @endforelse
    @if ($vm['recommendations']['additional_count'] > 0)<p class="muted">{{ $vm['recommendations']['additional_count'] }} additional recommendation{{ $vm['recommendations']['additional_count'] === 1 ? '' : 's' }} are available after these priority actions.</p>@endif
</section>

<section class="page page-break">
    <p class="eyebrow">Evidence</p><h2>Evidence for this estimate</h2>
    <h3>{{ $vm['evidence']['profile']['title'] }}</h3><p class="muted">{{ $vm['evidence']['profile']['unit'] }} · Finnish local time</p>
    @php($maxKwh = max(1, ...array_map(fn ($point) => (float) $point['kwh'], $vm['evidence']['profile']['points'] ?: [['kwh' => 0]])))
    <div class="profile">@foreach ($vm['evidence']['profile']['points'] as $point)<div class="bar-wrap"><div class="bar" style="height: {{ max(2, ((float) $point['kwh'] / $maxKwh) * 100) }}%;"></div></div>@endforeach</div>
    <p class="muted" style="font-size: 8px; margin-top: 4px;">00:00 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 06:00 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 12:00 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 18:00 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 23:00</p>
    <table class="comparison"><tr>@foreach ($vm['evidence']['cost_comparison'] as $item)<td><span class="small-label">{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></td>@endforeach</tr></table>
    <p class="note">{{ $vm['evidence']['callout'] }}</p>
</section>

<section class="page">
    <p class="eyebrow">Before acting</p><h2>Confidence and scope</h2>
    <p><strong>{{ $vm['confidence']['label'] }} confidence.</strong> {{ $vm['confidence']['reason'] }}</p>
    <table class="info">@foreach ($vm['confidence']['information'] as $item)<tr><td>{{ $item['label'] }}</td><td>{{ $item['value'] }}</td></tr>@endforeach</table>
    <h3 style="margin-top: 18px;">Included in this estimate</h3><p class="muted">{{ $vm['confidence']['included'] }}</p>
    <h3>Confirm before action</h3><p class="muted">{{ $vm['confidence']['confirm_before_action'] }}</p>
    <p class="note">{{ $vm['confidence']['ending_note'] }}</p>
</section>

<script type="text/php">
if (isset($pdf)) {
    $font = $fontMetrics->get_font('DejaVu Sans', 'normal');
    $pdf->page_text(175, 806, '{{ addslashes($vm['cover']['organization_name']) }} · Confidential · Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.39, 0.45, 0.54]);
}
</script>
</body></html>
