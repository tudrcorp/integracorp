<?php

use App\Models\User;
use App\Models\TelemedicineDoctor;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use PhpParser\Node\Stmt\TryCatch;

new #[Layout('components.layouts.auth.split')] class extends Component {


    #[Validate('required', message: 'Campo requerido')]
    public string $first_name;

    #[Validate('required', message: 'Campo requerido')]
    public string $last_name;

    #[Validate('required', message: 'Campo requerido')]
    public string $nro_identificacion;

    #[Validate('required', message: 'Campo requerido')]
    public string $phone;

    #[Validate('required', message: 'Debes ingresar un correo electrónico!')]
    #[Validate('email', message: 'El correo ingresado no es valido!')]
    #[Validate('unique:' . User::class, message: 'El correo ingresado ya se encuentra registrado!')]
    public string $email;

    #[Validate('required', message: 'Campo requerido!')]
    #[Validate('min:8', message: 'La contraseña debe tener al menos 8 caracteres!')]
    #[Validate('confirmed', message: 'Las contraseñas no coinciden, por favor verifica y intenta nuevamente!')]
    public string $password;

    public string $password_confirmation;

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        try {

            $validated = $this->validate([
                'password'              => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ]);

            $validated['password'] = Hash::make($validated['password']);

            /**
             * Creamos el agente
             * y cargamos la informacion previa en la tabla de agent
             */
            $create_doctor = new TelemedicineDoctor();
            $create_doctor->first_name          = $this->first_name;
            $create_doctor->last_name           = $this->last_name;
            $create_doctor->nro_identificacion  = $this->nro_identificacion;
            $create_doctor->phone               = $this->phone;
            $create_doctor->email               = $this->email;
            $create_doctor->save();

            /**
             * Creamos el registro en la tabla de usuarios
             */
            $create_user = new User();
            $create_user->doctor_id         = $create_doctor->id;
            $create_user->is_doctor         = true;
            $create_user->name              = strtoupper($this->first_name . ' ' . $this->last_name);
            $create_user->email             = $this->email;
            $create_user->password          = $validated['password'];
            $create_user->status            = 'ACTIVO';
            $create_user->save();

            /**
             * Notificacion por correo electronico
             * CARTA DE BIENVENIDA
             * @param Agent $record
             */
            // $create_agent->sendCartaBienvenida($create_agent->id, $create_agent->name, $create_agent->email);

            Notification::make()
                ->title('DOCTOR REGISTRADO')
                ->body('BIENVENIDO Dr(a). ' . $this->first_name . ' ' . $this->last_name . ' A INTEGRACORP! Registro exitoso!.')
                ->icon('heroicon-m-user-plus')
                ->iconColor('success')
                ->success()
                ->seconds(5)
                ->send();

            $this->redirect(route('filament.telemedicina.auth.login'));
            //code...
            //code...
        } catch (\Throwable $th) {
            dd($th);
        }
    }
}; ?>

<div class="flex flex-col gap-6">

    <x-auth-header :title="__('Registro de Doctores')" :description="__('Todos los campos son requeridos')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- First Name -->
        <flux:input input icon="user" wire:model="first_name" :label="__('Nombre(s)')" type="text" autofocus
            autocomplete="first_name" placeholder="Carlos Eduardo"
            oninput="this.value = this.value.replace(/[^a-zA-Z\sáéíóúÁÉÍÓÚÑñ]/g, '')" />

        <!-- Last Name -->
        <flux:input input icon="user" wire:model="last_name" :label="__('Apellido(s)')" type="text" autofocus
            autocomplete="last_name" placeholder="Navas Pereira"
            oninput="this.value = this.value.replace(/[^a-zA-Z\sáéíóúÁÉÍÓÚÑñ]/g, '')" />

        <!-- Nro Identificacion -->
        <flux:input input icon="user" wire:model="nro_identificacion" :label="__('Cédula de Identidad')" type="text"
            autofocus autocomplete="nro_identificacion" placeholder="12345678" mask="99999999" />

        <!-- Phone -->
        <flux:input input icon="phone" wire:model="phone" :label="__('Número de Teléfono')" type="tel"
            autocomplete="phone" placeholder="04127018390" mask="(9999) 999-9999" />

        <!-- Email Address -->
        <flux:input input icon="at-symbol" wire:model="email" :label="__('Correo Electrónico')" type="email"
            autocomplete="email" placeholder="email@example.com" />

        <!-- Password -->
        <flux:input input icon="key" wire:model="password" :label="__('Contraseña')" type="password"
            autocomplete="new-password" :placeholder="__('Contraseña')" viewable />
        <flux:description>
            La Contraseña debe contener al menos 8 caracteres entre mayúsculas, minúsculas y números, sin espacios en
            blanco.
        </flux:description>

        <!-- Confirm Password -->
        <flux:input input icon="key" wire:model="password_confirmation" :label="__('Confirmar Contraseña')"
            type="password" autocomplete="new-password" :placeholder="__('Confirmar Contraseña')" viewable />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Registrar Doctor') }}
            </flux:button>
        </div>
    </form>

    <!-- <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Ya tienes una cuenta registrada en INTEGRACORP?') }}
        <flux:link :href="route('filament.agents.auth.login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div> -->
</div>