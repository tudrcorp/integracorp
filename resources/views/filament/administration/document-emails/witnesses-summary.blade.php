@props([
    'cc' => [],
    'bcc' => [],
    'phones' => [],
    'settingLabel' => '',
])

{{-- Testigos del envío, tal como están en el Centro de notificaciones. --}}
<div class="space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
    @if ($cc !== [])
        <p>Copia visible (CC): {{ implode(', ', $cc) }}</p>
    @endif
    @if ($bcc !== [])
        <p>Copia oculta (CCO): {{ implode(', ', $bcc) }}</p>
    @endif
    @if ($phones !== [])
        <p>WhatsApp testigo con el PDF: {{ implode(', ', $phones) }}</p>
    @endif
    @if ($cc === [] && $bcc === [] && $phones === [])
        <p>Sin testigos activos.</p>
    @endif
    <p>Los testigos se configuran en Negocios → Centro de notificaciones → «{{ $settingLabel }}».</p>
</div>
