@php
    /** @var array<int, array<string, mixed>> $columns */
    $type = (string) ($type ?? 'benefits');

    // El editor de la modal deja renombrar y quitar columnas desde la propia
    // cabecera. Se edita solo en la tabla de beneficios: repetir el input en la
    // tabla de tarifas duplicaría el mismo dato y el viaje a Livewire.
    $manageColumns = (bool) ($manageColumns ?? false) && $type === 'benefits';
    $matrixStatePath = (string) ($matrixStatePath ?? 'data');
@endphp
@foreach ($columns as $column)
    @php
        $columnKey = (string) ($column['column_key'] ?? $loop->index);
        $headerLabel = (string) ($column['header_label'] ?? '');
    @endphp
    <th
        wire:key="pg-plan-th-{{ $type }}-{{ $columnKey }}"
        class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase min-w-0"
    >
        @if ($manageColumns)
            <div class="flex items-center gap-1">
                <input
                    type="text"
                    wire:model.live.debounce.400ms="{{ $matrixStatePath }}.columns.{{ $loop->index }}.header_label"
                    placeholder="Nombre de la columna"
                    aria-label="Nombre de la columna del plan"
                    class="block w-full min-w-0 rounded-lg border border-white/30 bg-white/15 px-2 py-1 text-center text-[11px] font-bold uppercase text-white placeholder:text-white/60 focus:border-white focus:bg-white/25 focus:ring-0"
                />
                <button
                    type="button"
                    wire:click="removeMatrixColumn('{{ $columnKey }}', '{{ $matrixStatePath }}')"
                    wire:confirm="¿Quitar la columna «{{ $headerLabel !== '' ? $headerLabel : 'sin nombre' }}»? Se perderán sus coberturas y tarifas en esta cotización."
                    class="inline-flex shrink-0 items-center justify-center rounded-lg p-1 text-white/80 transition hover:bg-white/20 hover:text-white"
                    title="Quitar columna"
                    aria-label="Quitar columna"
                >
                    <x-filament::icon icon="heroicon-m-x-mark" class="size-4" />
                </button>
            </div>
        @else
            {{ $headerLabel !== '' ? $headerLabel : '—' }}
        @endif
    </th>
@endforeach
