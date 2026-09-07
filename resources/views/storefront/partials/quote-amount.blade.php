@php
    $hasAmount = ! empty($quote['has_amount']);
    $prefix = (string) ($quote['amount_prefix'] ?? '');
    $label = (string) ($quote['amount_label'] ?? 'Monto por confirmar');
    $period = (string) ($quote['amount_period'] ?? '');
@endphp
<p class="sf-quote-card__amount {{ $hasAmount ? '' : 'is-pending' }}">
    @if ($prefix !== '')
        <span class="sf-quote-card__amount-prefix">{{ $prefix }}</span>
    @endif
    <span class="sf-quote-card__amount-value">{{ $label }}</span>
    @if ($period !== '')
        <span class="sf-quote-card__amount-period">{{ $period }}</span>
    @endif
</p>
