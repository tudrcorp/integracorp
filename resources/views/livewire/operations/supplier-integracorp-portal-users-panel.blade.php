<div class="space-y-3">
    <form wire:submit="savePortalUsers">
        {{ $this->form }}

        @if (\App\Support\Filament\Operations\OperationsSuperAdmin::check())
            <div class="flex justify-end pt-2">
                <x-filament::button type="submit" color="success" icon="heroicon-o-check">
                    Guardar usuarios de acceso
                </x-filament::button>
            </div>
        @endif
    </form>
</div>
