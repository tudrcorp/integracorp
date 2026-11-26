{{-- Doble check de una respuesta del analista: ✓ enviado, ✓✓ gris entregado, ✓✓ azul leído. La hora del globo es la de envío. --}}
@php
    $delivery = $message['delivery'] ?? '';
    $readAt = $message['read_time'] ?? null;
    $deliveredAt = $message['delivered_time'] ?? null;
    $label = match ($delivery) {
        'read' => 'Leído'.($readAt ? ' a las '.$readAt : ''),
        'delivered' => 'Entregado'.($deliveredAt ? ' a las '.$deliveredAt : '').' · aún no lo lee',
        default => 'Enviado · aún no llega al teléfono del cliente',
    };
@endphp
@if ($delivery !== '')
    @if ($showLabel ?? false)
        <span class="crm-ticks-label is-{{ $delivery }}">{{ match ($delivery) { 'read' => 'Leído'.($readAt ? ' a las '.$readAt : ''), 'delivered' => 'Entregado', default => 'Enviado' } }}</span>
    @endif
    <span class="crm-ticks is-{{ $delivery }}" title="{{ $label }}" role="img" aria-label="{{ $label }}">
        @if ($delivery === 'sent')
            <svg viewBox="0 0 16 11" width="14" height="10" aria-hidden="true"><path d="M1.5 5.8 5 9.2 14.5 1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @else
            <svg viewBox="0 0 20 11" width="17" height="10" aria-hidden="true"><path d="M1 5.8 4.5 9.2 13 1M7.5 7.8 9 9.2 18.5 1" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        @endif
    </span>
@endif
