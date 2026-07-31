@php
    $maxAbs = max(1, ...array_map(fn ($s) => abs($s['cumulative_cents']), $waterfall));
@endphp
<table>
    <tbody>
        @foreach ($waterfall as $step)
            @php
                // A 'decrease'/'increase' row's own magnitude is its
                // delta (e.g. the fee itself) — its cumulative_cents is
                // the RUNNING TOTAL after that row is applied, not the
                // row's own value. Displaying cumulative_cents for every
                // row (including this one) was the exact bug that made
                // "Hetkin's service fee" show the same figure as "Net
                // customer savings": both are that row's cumulative
                // total, but only the net row's cumulative total is
                // meant to be shown. 'start'/'end' rows correctly show
                // their cumulative_cents (the running total IS the value
                // being presented for those two).
                $isDelta = in_array($step['kind'], ['decrease', 'increase'], true);
                $displayCents = $isDelta ? abs($step['delta_cents']) : $step['cumulative_cents'];
                $sign = $step['kind'] === 'decrease' ? '−' : '';
            @endphp
            <tr>
                <td style="width: 30%;">{{ $step['label'] }}</td>
                <td style="width: 50%;">
                    <div style="background: #e5e7eb; height: 14px; border-radius: 2px; position: relative;">
                        <div style="background: {{ $step['kind'] === 'decrease' ? '#ef4444' : '#4f46e5' }}; height: 14px; border-radius: 2px; width: {{ max(2, abs($step['cumulative_cents']) / $maxAbs * 100) }}%;"></div>
                    </div>
                </td>
                <td style="width: 20%; text-align: right;">{{ $sign }}{{ \App\Domain\Billing\Reports\Money::formatEurCents($displayCents) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
