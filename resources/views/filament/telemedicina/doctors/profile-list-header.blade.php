@php
    $fullName ??= null;
    $specialty ??= null;
    $status ??= null;
    $checklist ??= [];

    $statusKey = mb_strtoupper(trim((string) $status));
    $statusClasses = match ($statusKey) {
        'ACTIVO', 'ACTIVA' => 'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30',
        'INACTIVO', 'INACTIVA' => 'bg-rose-500/10 text-rose-700 ring-rose-600/25 dark:text-rose-300 dark:ring-rose-400/30',
        default => 'bg-gray-500/10 text-gray-700 ring-gray-500/20 dark:text-gray-200 dark:ring-white/15',
    };
@endphp

<div class="flex min-w-0 items-center gap-4 py-1">
    <span
        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0a74d6] to-[#005ca9] text-white shadow-md shadow-[#005ca9]/25"
        aria-hidden="true"
    >
        <x-filament::icon icon="heroicon-o-identification" class="size-7" />
    </span>

    <div class="min-w-0 space-y-1.5">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-[0.08em] text-gray-500 dark:text-gray-400">Ficha profesional</span>

            @if (filled($status))
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $statusClasses }}">{{ $statusKey }}</span>
            @endif
        </div>

        <p class="text-xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-2xl">Mi perfil médico</p>

        @if (filled($fullName))
            <p class="truncate text-base font-semibold text-gray-800 dark:text-gray-100" title="{{ $fullName }}">
                Dr(a). {{ $fullName }}
                @if (filled($specialty))
                    <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $specialty }}</span>
                @endif
            </p>

            <p class="max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-300">
                Mantenga al día sus datos, credenciales y firma digital: se imprimen en recetas, informes y órdenes. Use «Ver perfil» o «Editar» en su ficha.
            </p>
        @else
            <p class="max-w-3xl text-sm font-normal text-gray-600 dark:text-gray-300">
                Su usuario aún no tiene un médico asociado. Contacte a Operaciones para completar el registro.
            </p>
        @endif

        @if ($checklist !== [])
            <ul class="flex flex-wrap items-center gap-2 pt-1" aria-label="Estado de su ficha">
                @foreach ($checklist as $item)
                    <li
                        @class([
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1',
                            'bg-emerald-500/10 text-emerald-700 ring-emerald-600/20 dark:text-emerald-300 dark:ring-emerald-400/30' => $item['done'],
                            'bg-amber-500/10 text-amber-700 ring-amber-600/25 dark:text-amber-300 dark:ring-amber-400/30' => ! $item['done'],
                        ])
                        title="{{ $item['done'] ? $item['label'].': registrado' : $item['label'].': pendiente. Complételo desde «Editar».' }}"
                    >
                        <x-filament::icon :icon="$item['done'] ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle'" class="size-3.5" />
                        <span>{{ $item['label'] }}</span>
                        @unless ($item['done'])
                            <span class="font-normal">· pendiente</span>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
