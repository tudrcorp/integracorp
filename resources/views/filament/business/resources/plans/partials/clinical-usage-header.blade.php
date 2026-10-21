@php
    $chip = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset';
@endphp

<div class="flex flex-col gap-2 py-1">
    <span class="text-xs font-semibold uppercase tracking-[0.14em] text-primary-600 dark:text-primary-400">Uso clínico del plan</span>

    <span class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $name }}</span>

    <span class="flex flex-wrap items-center gap-1.5">
        <span class="{{ $chip }} bg-gray-50 text-gray-700 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            {{ $isPackage ? 'Plan por paquete' : 'Plan por coberturas' }}
        </span>

        @if (filled($code))
            <span class="{{ $chip }} bg-gray-50 font-mono text-gray-700 ring-gray-500/20 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">{{ $code }}</span>
        @endif

        @if ($inactive)
            <span class="{{ $chip }} bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30">Plan inactivo</span>
        @endif

        @if ($total === 0)
            <span class="{{ $chip }} bg-gray-50 text-gray-600 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">Sin beneficios</span>
        @elseif ($complete)
            <span class="{{ $chip }} bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30">✓ Listo para telemedicina · {{ $total }} beneficios</span>
        @else
            <span class="{{ $chip }} bg-amber-50 text-amber-800 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/30">● Incompleto · faltan {{ $missing }} de {{ $total }}</span>
        @endif
    </span>

    <span class="text-sm text-gray-500 dark:text-gray-400">
        Qué servicio puede asignar el médico por cada beneficio y cuántas veces. Las tarifas y cotizaciones no cambian.
    </span>
</div>
