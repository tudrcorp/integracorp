<div>
    <div class="flex items-center justify-center text-center text-md uppercase w-full xs:px-5 sm:px-5 lg:px-20" style="margin-bottom: 12px;">
        ¡Bienvenidos a TuDrEnCasa!
        <br>Estimado agente, es un gusto recibirte.!
        <br>Al registrarte, formas parte de una comunidad comprometida con llevar <br> soluciones de salud innovadoras y accesibles a más personas.
    </div>

    <form wire:submit="create" class="mt-10 mb-5">
        {{ $this->form }}
        <div style="margin-top: 20px;">
            <x-filament::button type="submit" wire:loading.attr="enabled" wire:target="create" class="w-full mt-10 mb-5 flex items-center justify-center p-5 bg-[#305b93] hover:bg-[#4a8982]">
                INICIAR REGISTRO
            </x-filament::button>

        </div>
    </form>

    <x-filament-actions::modals />


</div>
