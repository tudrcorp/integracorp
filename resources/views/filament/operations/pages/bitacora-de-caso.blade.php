<x-filament-panels::page>
    <div class="space-y-4">
        @unless (is_array($this->dossier))
            <div class="rounded-2xl border border-slate-200/90 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <label class="mb-2 block text-sm font-semibold text-slate-800 dark:text-white">
                    Buscar caso
                </label>
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Código, nombre del paciente o cédula"
                        autocomplete="off"
                    />
                </x-filament::input.wrapper>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    Escriba al menos 2 caracteres. Puede buscar por código de caso, nombre o cédula.
                </p>
                <p wire:loading wire:target="search" class="mt-2 text-xs font-medium text-sky-700 dark:text-sky-300">
                    Buscando casos…
                </p>

                @if ($this->search !== '' && mb_strlen($this->search) < 2)
                    <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">Siga escribiendo para ver resultados.</p>
                @endif

                @if (count($this->searchResults) > 0)
                    <ul class="mt-3 divide-y divide-slate-200 overflow-hidden rounded-xl border border-slate-200 dark:divide-white/10 dark:border-white/10">
                        @foreach ($this->searchResults as $result)
                            <li wire:key="bitacora-result-{{ $result['id'] }}">
                                <button
                                    type="button"
                                    wire:click="selectCase({{ $result['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="selectCase"
                                    class="flex w-full items-start justify-between gap-3 px-4 py-2.5 text-left transition hover:bg-sky-50 dark:hover:bg-sky-950/40"
                                >
                                    <span>
                                        <span class="block font-semibold text-slate-900 dark:text-white">{{ $result['label'] }}</span>
                                        <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Estatus: {{ $result['status'] }}</span>
                                    </span>
                                    <span class="text-xs font-medium text-sky-700 dark:text-sky-300">Ver bitácora</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif (mb_strlen($this->search) >= 2)
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">No se encontraron casos con ese criterio.</p>
                @endif
            </div>
        @endunless

        <div
            wire:loading.flex
            wire:target="selectCase"
            class="hidden items-center justify-center gap-2 rounded-2xl border border-sky-200 bg-sky-50 px-4 py-5 text-sm font-medium text-sky-800 dark:border-sky-500/30 dark:bg-sky-950/40 dark:text-sky-200"
        >
            <x-filament::loading-indicator class="h-5 w-5" />
            Cargando bitácora…
        </div>

        @if (is_array($this->dossier))
            @php($dossier = $this->dossier)
            <div
                wire:key="bitacora-dossier-{{ $dossier['case_id'] ?? $this->selectedCaseId }}"
                class="space-y-3"
            >
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/80 bg-white px-4 py-3 dark:border-white/10 dark:bg-gray-900">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-slate-900 dark:text-white">
                            {{ $dossier['code'] }}
                            <span class="font-normal text-slate-500">· {{ $dossier['patient']['Nombre'] ?? 'Paciente' }}</span>
                        </h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ $dossier['status'] }}
                            @if (! empty($dossier['is_discharge']))
                                · incluye cierre por alta médica
                            @endif
                        </p>
                    </div>
                    <x-filament::button
                        color="gray"
                        wire:click="clearCase"
                        icon="heroicon-m-x-mark"
                        size="sm"
                    >
                        Buscar otro caso
                    </x-filament::button>
                </div>

                <div class="grid gap-3 xl:grid-cols-2">
                    @include('filament.operations.partials.bitacora-caso-map', [
                        'title' => 'Identificación del caso',
                        'map' => $dossier['header'] ?? [],
                        'open' => true,
                    ])
                    @include('filament.operations.partials.bitacora-caso-map', [
                        'title' => 'Paciente',
                        'map' => $dossier['patient'] ?? [],
                        'open' => true,
                    ])
                </div>

                @if (! empty($dossier['discharge']))
                    @include('filament.operations.partials.bitacora-caso-map', [
                        'title' => 'Alta médica',
                        'map' => $dossier['discharge'],
                        'open' => true,
                    ])
                @endif

                @include('filament.operations.partials.bitacora-caso-documents', [
                    'entries' => $dossier['documents'] ?? [],
                    'open' => true,
                ])

                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Consultas y notas médicas', 'entries' => $dossier['consultations'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Seguimientos', 'entries' => $dossier['follow_ups'] ?? []])
                @include('filament.operations.partials.bitacora-caso-amd', ['entries' => $dossier['amd_reports'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Observaciones del caso', 'entries' => $dossier['observations'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Bitácora operativa', 'entries' => $dossier['operation_logs'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Laboratorios', 'entries' => $dossier['labs'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Medicamentos', 'entries' => $dossier['medications'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Estudios / imagenología', 'entries' => $dossier['studies'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Especialistas', 'entries' => $dossier['specialties'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Coordinaciones', 'entries' => $dossier['coordinations'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Órdenes de servicio', 'entries' => $dossier['service_orders'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Citas médicas', 'entries' => $dossier['appointments'] ?? []])
                @include('filament.operations.partials.bitacora-caso-list', ['title' => 'Mensajería de seguimiento', 'entries' => $dossier['messages'] ?? []])
            </div>
        @endif
    </div>
</x-filament-panels::page>
