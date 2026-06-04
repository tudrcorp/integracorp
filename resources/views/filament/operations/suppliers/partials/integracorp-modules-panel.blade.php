@php
    $modules = [
        [
            'icon' => 'heroicon-o-heart',
            'label' => 'Telemedicina',
            'hint' => 'Casos, pacientes y consultas',
        ],
        [
            'icon' => 'heroicon-o-clipboard-document-list',
            'label' => 'Servicios médicos',
            'hint' => 'Coordinación y gestión clínica',
        ],
        [
            'icon' => 'heroicon-o-clipboard-document-check',
            'label' => 'Órdenes de servicio',
            'hint' => 'Órdenes operativas finalizadas',
        ],
    ];
    $iconClass = 'size-5';
    $isEnabled = (bool) ($enabled ?? false);
@endphp

<div class="fi-supplier-integracorp-modules space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-[0.08em] text-slate-500 dark:text-slate-400">
            Módulos con acceso al habilitar
        </p>
        <span @class([
            'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
            'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-500/30' => $isEnabled,
            'bg-slate-100 text-slate-600 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-300 dark:ring-white/10' => ! $isEnabled,
        ])>
            {{ $isEnabled ? 'Funciones aceptadas' : 'Sin aceptación' }}
        </span>
    </div>
    <ul class="grid gap-2.5 sm:grid-cols-3">
        @foreach ($modules as $module)
            <li
                @class([
                    'flex gap-3 rounded-2xl border p-3.5 shadow-sm transition-colors',
                    'border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 to-white ring-1 ring-emerald-200/60 dark:border-emerald-500/25 dark:from-emerald-500/10 dark:to-slate-950/90 dark:ring-emerald-500/20' => $isEnabled,
                    'border-slate-200/90 bg-gradient-to-br from-white to-slate-50/90 opacity-75 dark:border-white/10 dark:from-slate-900/80 dark:to-slate-950/90' => ! $isEnabled,
                ])
            >
                <span
                    @class([
                        'flex size-9 shrink-0 items-center justify-center rounded-xl ring-1',
                        'bg-emerald-500/10 text-emerald-600 ring-emerald-500/20 dark:bg-emerald-400/15 dark:text-emerald-300 dark:ring-emerald-400/25' => $isEnabled,
                        'bg-sky-500/10 text-sky-600 ring-sky-500/20 dark:bg-sky-400/15 dark:text-sky-300 dark:ring-sky-400/25' => ! $isEnabled,
                    ])
                >
                    @switch($module['icon'])
                        @case('heroicon-o-heart')
                            <x-heroicon-o-heart @class([$iconClass]) />
                            @break
                        @case('heroicon-o-clipboard-document-list')
                            <x-heroicon-o-clipboard-document-list @class([$iconClass]) />
                            @break
                        @default
                            <x-heroicon-o-clipboard-document-check @class([$iconClass]) />
                    @endswitch
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex items-start justify-between gap-2">
                        <span class="block text-sm font-semibold leading-tight text-slate-900 dark:text-white">
                            {{ $module['label'] }}
                        </span>
                        @if ($isEnabled)
                            <x-heroicon-s-check-circle class="size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                        @else
                            <x-heroicon-s-x-circle class="size-4 shrink-0 text-slate-400 dark:text-slate-500" />
                        @endif
                    </span>
                    <span class="mt-0.5 block text-xs leading-snug text-slate-500 dark:text-slate-400">
                        {{ $module['hint'] }}
                    </span>
                </span>
            </li>
        @endforeach
    </ul>
</div>
