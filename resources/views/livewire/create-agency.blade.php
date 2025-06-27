<div>
    <div class="flex items-center justify-center text-center text-xs uppercase w-full mb-8 xs:px-5 sm:px-5 lg:px-20">
        ¡Bienvenidos a TuDrEnCasa! 
        <br>Es un placer recibirlos por primera vez en nuestra plataforma.
        <br>Al registrarse, están dando el primer paso hacia una colaboración que busca transformar la atención médica y llevar servicios de salud innovadores y accesibles a más personas.
    </div>
    <form wire:submit="create">
        {{ $this->form }}
        <x-filament::button type="submit" wire:loading.attr="enabled" wire:target="create" class="w-full mt-5 mb-5 flex items-center justify-center p-5 bg-[#305b93] hover:bg-[#4a8982]">
            INICIAR REGISTRO
        </x-filament::button>
    </form>

    <x-filament-actions::modals />

</div>



