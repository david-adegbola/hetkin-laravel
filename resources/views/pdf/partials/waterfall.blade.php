@php
    $maxAbs = max(1, ...array_map(fn ($s) => abs($s['cumulative_cents']), $waterfall));
@endphp
<table>
    <tbody>
        @foreach ($waterfall as $step)
            <tr>
                <td style="width: 30%;">{{ $step['label'] }}</td>
                <td style="width: 50%;">
                    <div style="background: #e5e7eb; height: 14px; border-radius: 2px; position: relative;">
                        <div style="background: {{ $step['kind'] === 'decrease' ? '#ef4444' : '#4f46e5' }}; height: 14px; border-radius: 2px; width: {{ max(2, abs($step['cumulative_cents']) / $maxAbs * 100) }}%;"></div>
                    </div>
                </td>
                <td style="width: 20%; text-align: right;">{{ \App\Domain\Billing\Reports\Money::formatEurCents($step['cumulative_cents']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
