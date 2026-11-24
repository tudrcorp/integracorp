@php
    use App\Support\Operations\PatientWhatsApp\UltraMsgConnectionStatus;

    /** @var array{state: string, label: string, detail: string, checked_at: string|null} $instance */
    /** @var array{state: string, label: string, detail: string} $webhook */
    $instanceTone = match ($instance['state']) {
        UltraMsgConnectionStatus::STATE_CONNECTED => 'success',
        UltraMsgConnectionStatus::STATE_DISCONNECTED, UltraMsgConnectionStatus::STATE_UNREACHABLE => 'danger',
        default => 'gray',
    };
    $age = UltraMsgConnectionStatus::ageInSeconds($instance);
    $isStale = $instance['checked_at'] !== null && $age > UltraMsgConnectionStatus::STALE_SECONDS;
    $dots = [
        'success' => 'bg-emerald-500',
        'danger' => 'bg-rose-500',
        'warning' => 'bg-amber-500',
        'gray' => 'bg-gray-400',
    ];
    $badges = [
        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-300/70 dark:bg-emerald-500/20 dark:text-emerald-100',
        'danger' => 'bg-rose-100 text-rose-800 ring-rose-300/70 dark:bg-rose-500/20 dark:text-rose-100',
        'warning' => 'bg-amber-100 text-amber-800 ring-amber-300/70 dark:bg-amber-500/20 dark:text-amber-100',
        'gray' => 'bg-gray-100 text-gray-700 ring-gray-300/70 dark:bg-white/10 dark:text-gray-200',
    ];
@endphp

<div wire:poll.60s class="grid gap-3 text-sm md:grid-cols-2">
    <div class="rounded-2xl border border-gray-200/80 p-4 dark:border-white/10">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Instancia de WhatsApp (envío)</span>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $badges[$instanceTone] }}">
                <span @class(['size-2 rounded-full', $dots[$instanceTone], 'animate-pulse' => $instanceTone === 'success'])></span>
                {{ $instance['label'] }}
            </span>
        </div>
        <p class="mt-2 text-gray-700 dark:text-gray-200">{{ $instance['detail'] }}</p>
        @if ($instance['checked_at'] !== null && $instance['state'] !== UltraMsgConnectionStatus::STATE_NOT_CONFIGURED)
            <p @class(['mt-1 text-xs', 'text-amber-700 dark:text-amber-300' => $isStale, 'text-gray-500 dark:text-gray-400' => ! $isStale])>
                Verificado {{ \Illuminate\Support\Carbon::parse($instance['checked_at'])->diffForHumans() }}@if ($isStale) — dato desactualizado, pulse «Verificar ahora»@endif.
            </p>
        @endif
    </div>

    <div class="rounded-2xl border border-gray-200/80 p-4 dark:border-white/10">
        <div class="flex items-center justify-between gap-3">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Avisos de WhatsApp (recepción)</span>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $badges[$webhook['state']] ?? $badges['gray'] }}">
                <span class="size-2 rounded-full {{ $dots[$webhook['state']] ?? $dots['gray'] }}"></span>
                {{ $webhook['label'] }}
            </span>
        </div>
        <p class="mt-2 text-gray-700 dark:text-gray-200">{{ $webhook['detail'] }}</p>
    </div>
</div>
