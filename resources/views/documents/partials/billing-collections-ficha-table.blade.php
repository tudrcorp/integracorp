@props([
    'billingCollections',
])

@php
    $formatDate = static function (mixed $v): string {
        if (blank($v)) {
            return '—';
        }
        try {
            return \Carbon\Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $v;
        }
    };
    $formatMoney = static function (mixed $v): string {
        if ($v === null || $v === '') {
            return '—';
        }

        return 'US$ '.number_format((float) $v, 2, ',', '.');
    };

    $sortedBillingCollections = $billingCollections->sortBy(function ($row): string {
        $raw = $row->next_payment_date ?? $row->expiration_date;
        if (blank($raw)) {
            return '9999-12-31';
        }
        try {
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return '9999-12-30';
        }
    }, SORT_STRING)->values();
@endphp

<div class="section-block" style="page-break-inside: auto;">
    <div class="section-title">Próximos pagos y estatus de cobranza</div>
    @if ($sortedBillingCollections->isEmpty())
        <p class="small-text" style="margin:0;">No hay cobranzas registradas para este código de afiliación.</p>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Vencimiento</th>
                    <th>Estado cobranza</th>
                    <th>Frecuencia</th>
                    <th>Próx. pago (reg.)</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sortedBillingCollections as $row)
                    <tr>
                        <td>{{ $formatDate($row->expiration_date) }}</td>
                        <td>{{ $row->status ?? '—' }}</td>
                        <td>{{ $row->payment_frequency ?? '—' }}</td>
                        <td>{{ $formatDate($row->next_payment_date) }}</td>
                        <td>{{ $formatMoney($row->total_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
