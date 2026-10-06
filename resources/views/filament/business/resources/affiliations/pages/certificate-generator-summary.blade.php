@php
    /** @var string $planLabel */
    /** @var string $statusLabel */
    /** @var string $frequency */
    /** @var \App\Support\Affiliations\Certificates\CertificatePaymentPeriod|null $period */
    $paid = (bool) $period?->currentIsPaid;
@endphp
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Plan</div>
        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $planLabel }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $statusLabel }} · pago {{ \Illuminate\Support\Str::lower($frequency) }}</div>
    </div>
    <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Vigencia</div>
        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $period ? $period->start->format('d/m/Y').' – '.$period->end->format('d/m/Y') : 'Sin fecha de vigencia' }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $affiliatesCount }} {{ $affiliatesCount === 1 ? 'afiliado vigente' : 'afiliados vigentes' }}</div>
    </div>
    <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Período facturado</div>
        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $period?->currentPeriodLabel() ?? '—' }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $period?->paidPeriodLabel() ? 'Pagado: '.$period->paidPeriodLabel() : 'Sin pagos registrados en el año' }}</div>
    </div>
    <div @class([
        'rounded-xl px-4 py-3',
        'bg-success-50 dark:bg-success-500/10' => $paid,
        'bg-warning-50 dark:bg-warning-500/10' => ! $paid,
    ])>
        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Sello «PAGADO»</div>
        <div @class([
            'mt-1 text-sm font-semibold',
            'text-success-700 dark:text-success-400' => $paid,
            'text-warning-700 dark:text-warning-400' => ! $paid,
        ])>{{ $paid ? 'Sí, el período vigente está pagado' : 'No: el período vigente no está pagado' }}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $paid ? 'Se imprime en el certificado.' : 'El certificado saldrá sin el sello.' }}</div>
    </div>
</div>
