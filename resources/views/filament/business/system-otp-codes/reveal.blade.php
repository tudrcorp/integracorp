@php
    /** @var \App\Models\SystemOtpCode $record */
    $isActive = $status === \App\Models\SystemOtpCode::STATUS_ACTIVE;
    $details = (array) ($record->details ?? []);
@endphp

<div class="flex flex-col gap-4" x-data="{ copied: false }">
    <div class="flex flex-col items-center gap-3 rounded-xl p-5 {{ $isActive ? 'bg-emerald-50 ring-1 ring-emerald-600/20 dark:bg-emerald-500/10 dark:ring-emerald-400/20' : 'bg-gray-50 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10' }}">
        <p class="font-mono text-4xl font-bold tracking-[0.35em] tabular-nums {{ $isActive ? 'text-emerald-700 dark:text-emerald-300' : 'text-gray-400 line-through dark:text-gray-500' }}">{{ $code }}</p>

        <x-filament::badge :color="\App\Models\SystemOtpCode::statusColor($status)">{{ $status }}</x-filament::badge>
        <p class="text-center text-sm text-gray-600 dark:text-gray-300">{{ $statusHint }}</p>

        @if ($isActive)
            <x-filament::button
                size="sm"
                color="gray"
                icon="heroicon-m-clipboard-document"
                x-on:click="navigator.clipboard?.writeText(@js($code)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
            >
                <span x-show="! copied">Copiar clave</span>
                <span x-show="copied" x-cloak>Copiada</span>
            </x-filament::button>
        @endif
    </div>

    <dl class="grid grid-cols-1 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
        <dt class="text-gray-500 dark:text-gray-400">Pedida por</dt>
        <dd class="text-gray-900 sm:col-span-2 dark:text-white">{{ $record->requested_by_name ?? '—' }}</dd>

        <dt class="text-gray-500 dark:text-gray-400">Sobre qué</dt>
        <dd class="text-gray-900 sm:col-span-2 dark:text-white">{{ $record->subject ?? '—' }}</dd>

        <dt class="text-gray-500 dark:text-gray-400">Dónde se escribe</dt>
        <dd class="text-gray-900 sm:col-span-2 dark:text-white">{{ $record->kind->whereLabel() }}</dd>

        <dt class="text-gray-500 dark:text-gray-400">Vence</dt>
        <dd class="text-gray-900 sm:col-span-2 dark:text-white">{{ $record->expires_at?->format('d/m/Y h:i:s A') }}</dd>

        @foreach ($details as $label => $value)
            <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
            <dd class="text-gray-900 sm:col-span-2 dark:text-white">{{ $value }}</dd>
        @endforeach
    </dl>
</div>
