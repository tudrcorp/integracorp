@php
    $row = 'flex items-start justify-between gap-4 border-b border-slate-200/70 py-2 text-sm last:border-0 dark:border-white/10';
    $label = 'text-slate-500 dark:text-slate-400';
    $value = 'text-right font-medium text-slate-800 dark:text-slate-100';
@endphp

<div class="flex flex-col gap-3">
    @if (! $health['enabled'])
        <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Telegram está apagado en este servidor (TELEGRAM_ENABLED=false). Los avisos salen por WhatsApp y correo.</div>
    @elseif (! $health['configured'])
        <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Falta TELEGRAM_BOT_TOKEN en el .env de este servidor. Los avisos salen por WhatsApp y correo.</div>
    @elseif ($health['ok'])
        <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">✅ El bot responde correctamente.</div>
    @else
        <div class="rounded-xl bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-300">El bot no responde: {{ $health['error'] }}</div>
    @endif

    <div>
        <div class="{{ $row }}"><span class="{{ $label }}">Bot</span><span class="{{ $value }}">{{ $health['bot_username'] ? '@'.$health['bot_username'] : '—' }}</span></div>
        <div class="{{ $row }}"><span class="{{ $label }}">Usuario configurado</span><span class="{{ $value }}">{{ config('telegram.bot_username') ? '@'.config('telegram.bot_username') : 'Falta TELEGRAM_BOT_USERNAME' }}</span></div>
        <div class="{{ $row }}"><span class="{{ $label }}">Webhook</span><span class="{{ $value }} break-all">{{ $health['webhook_url'] ?? 'Sin registrar (php artisan telegram:set-webhook)' }}</span></div>
        <div class="{{ $row }}"><span class="{{ $label }}">Mensajes de Telegram sin procesar</span><span class="{{ $value }}">{{ $health['pending_update_count'] }}</span></div>
        <div class="{{ $row }}"><span class="{{ $label }}">Último error del webhook</span><span class="{{ $value }}">{{ $health['webhook_last_error'] ? $health['webhook_last_error'].' ('.\Illuminate\Support\Carbon::createFromTimestamp($health['webhook_last_error_at'] ?? time())->timezone(config('app.timezone'))->format('d/m/Y h:i A').')' : 'Ninguno' }}</span></div>
        <div class="{{ $row }}"><span class="{{ $label }}">Cola de envío</span><span class="{{ $value }}">{{ $queue }}</span></div>
    </div>

    <p class="text-xs text-slate-500 dark:text-slate-400">El worker debe escuchar la cola «{{ $queue }}». Si nadie la atiende, el monitor de colas lo avisa por WhatsApp y correo.</p>
</div>
