<x-filament-panels::page>
    <x-filament::tabs label="Biblioteca">
        @foreach (\App\Filament\Business\Pages\BibliotecaAtencion::TABS as $key => $label)
            <x-filament::tabs.item :active="$tab === $key" wire:click="selectTab('{{ $key }}')" wire:key="tab-{{ $key }}">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        @if ($tab === 'documentos')
            El analista los envía desde el chat con un clic (botón «Documentos» o tecla D). Solo PDF aprobados.
        @else
            El analista las inserta escribiendo «/» en el chat. No se envían solas: siempre puede revisarlas antes.
        @endif
    </p>

    {{ $this->table }}
</x-filament-panels::page>
