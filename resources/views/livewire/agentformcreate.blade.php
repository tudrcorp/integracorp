<?php

use App\Models\User;
use App\Models\Agent;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use PhpParser\Node\Stmt\TryCatch;

new #[Layout('components.layouts.auth.split')] class extends Component {


    public string $owner_code;
    public string $name;
    public string $type_doc;
    public string $ci_rif;
    public string $email;
    public string $phone;
    public string $password;
    public string $password_confirmation;

    public function mount($code = null)
    {
        $code_decrypted = isset($this->code) ? Crypt::decryptString($this->code) : 'TDG-100';
        $this->owner_code = $code_decrypted;
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        try {

            $validated = $this->validate([
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ]);

            $validated['password'] = Hash::make($validated['password']);

            /**
             * Creamos el agente
             * y cargamos la informacion previa en la tabla de agent
             */
            $create_agent = new Agent();
            $create_agent->owner_code       = $this->owner_code;
            $create_agent->agent_type_id    = 2;
            $create_agent->name             = $this->name;
            $create_agent->email            = $this->email;
            $create_agent->save();

            /**
             * Creamos el registro en la tabla de usuarios
             */
            $create_user = new User();
            $create_user->code_agent = 'AGT-000' . $create_agent->id;
            $create_user->agent_id          = $create_agent->id;
            $create_user->code_agency       = $create_agent->owner_code;
            $create_user->is_agent          = true;
            $create_user->name              = $this->name;
            $create_user->email             = $this->email;
            $create_user->password          = $validated['password'];
            $create_user->link_agent = env('APP_URL') . '/agent/c/' . Crypt::encryptString($create_agent->id);
            $create_user->status = 'ACTIVO';
            $create_user->save();

            /**
             * Notificacion por correo electronico
             * CARTA DE BIENVENIDA
             * @param Agent $record
             */
            $create_agent->sendCartaBienvenida($create_agent->id, $create_agent->name, $create_agent->email);

            Notification::make()
                ->title('AGENTE REGISTRADA')
                ->body('El registro fue enviado con exito')
                ->icon('heroicon-m-user-plus')
                ->iconColor('success')
                ->success()
                ->seconds(5)
                ->send();

            $this->redirect(route('filament.agents.auth.login'));
            
            //code...
        } catch (\Throwable $th) {
            dd($th);
        }

        
    }
}; ?>

<div class="flex flex-col gap-6">

    <x-auth-header :title="__('Registro de Agentes')" :description="__('Todos los campos son requeridos')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <flux:input input icon="user" wire:model="name" :label="__('Nombre y Apellido')" type="text" required autofocus
            autocomplete="fill_name" placeholder="Nombre Apellido"
            oninput="this.value = this.value.replace(/[^a-zA-Z\sáéíóúÁÉÍÓÚÑñ]/g, '')" />

        <!-- Email Address -->
        <flux:input input icon="at-symbol" wire:model=" email" :label="__('Correo Electrónico')" type="email" required
            autocomplete="email" placeholder="email@example.com" />

        <!-- Password -->
        <flux:input input icon="key" wire:model="password" :label="__('Password')" type="password" required
            autocomplete="new-password" :placeholder="__('Contraseña')" viewable />

        <!-- Confirm Password -->
        <flux:input input icon="key" wire:model="password_confirmation" :label="__('Confirm password')" type="password"
            required autocomplete="new-password" :placeholder="__('Confirmar Contraseña')" viewable />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Registrar Agente') }}
            </flux:button>
        </div>
    </form>

    <!-- <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Ya tienes una cuenta registrada en INTEGRACORP?') }}
        <flux:link :href="route('filament.agents.auth.login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div> -->
</div>