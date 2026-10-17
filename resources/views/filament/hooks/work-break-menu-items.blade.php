{{--
    Reunión y almuerzo dentro del menú de usuario (debajo de «Perfil de usuario»).
    No es un componente Livewire: lee el estado del store `workBreak` que mantiene
    el componente WorkBreakToggle y le pide abrir sus acciones con el evento
    `wbt-action`, así los contadores siguen vivos y las modales viven en un solo lugar.
--}}
@if (\App\Livewire\WorkBreakToggle::shouldRender())
    <x-filament::dropdown.list x-data x-show="$store.workBreak?.ready" x-cloak>
        {{-- Reunión --}}
        <x-filament::dropdown.list.item
            icon="heroicon-o-check-circle"
            color="success"
            x-show="$store.workBreak?.meeting?.open"
            x-on:click="close(); $dispatch('wbt-action', 'endMeeting')"
        >
            Terminé la reunión
            <span class="block text-xs font-normal opacity-75" x-text="$store.workBreak?.meetingText"></span>
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            icon="heroicon-o-user-group"
            color="primary"
            x-show="$store.workBreak?.meeting?.available && ! $store.workBreak?.meeting?.open"
            x-on:click="close(); $dispatch('wbt-action', 'startMeeting')"
        >
            Entrar en reunión
            <span class="block text-xs font-normal opacity-75" x-show="($store.workBreak?.meeting?.today_count ?? 0) > 0" x-text="'Hoy: ' + $store.workBreak?.meeting?.today_count + ' · ' + $store.workBreak?.meetingTodayText"></span>
        </x-filament::dropdown.list.item>

        {{-- Almuerzo --}}
        <x-filament::dropdown.list.item
            icon="heroicon-o-clock"
            color="info"
            x-show="$store.workBreak?.status?.state === 'ready'"
            x-on:click="close(); $dispatch('wbt-action', 'startLunch')"
        >
            Salir a almorzar
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            icon="heroicon-o-arrow-uturn-left"
            color="success"
            x-show="$store.workBreak?.status?.state === 'on'"
            x-on:click="close(); $dispatch('wbt-action', 'endLunch')"
        >
            Volver del almuerzo
            <span class="block text-xs font-normal opacity-75" x-text="$store.workBreak?.lunchText"></span>
        </x-filament::dropdown.list.item>

        <x-filament::dropdown.list.item
            icon="heroicon-o-clock"
            disabled
            x-show="['early', 'done', 'missed'].includes($store.workBreak?.status?.state)"
        >
            <span x-text="$store.workBreak?.lunchText"></span>
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
@endif
