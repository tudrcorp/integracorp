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
use App\Livewire\Concerns\HandlesInternationalPhone;

new #[Layout('components.layouts.auth.split')] class extends Component {
    use HandlesInternationalPhone;

    public string $owner_code;

    #[Validate('required', message: 'Campo requerido')]
    public string $name;

    public string $type_doc;

    #[Validate('required', message: 'Debes ingresar un correo electrónico!')]
    #[Validate('email', message: 'El correo ingresado no es valido!')]
    #[Validate('unique:' . User::class, message: 'El correo ingresado ya se encuentra registrado!')]
    public string $email;

    #[Validate('required', message: 'Campo requerido!')]
    #[Validate('min:8', message: 'La contraseña debe tener al menos 8 caracteres!')]
    #[Validate('confirmed', message: 'Las contraseñas no coinciden, por favor verifica y intenta nuevamente!')]
    public string $password;

    public string $password_confirmation;

    public function mount($code = null)
    {
        $code_decrypted = isset($code) ? Crypt::decryptString($code) : 'TDG-100';
        $this->owner_code = $code_decrypted;
    }

    public function register(): void
    {
        $validated = $this->validate(array_merge([
            'name' => 'required',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ], $this->internationalPhoneValidationRules()));

        $validated['password'] = Hash::make($validated['password']);

        $create_agent = new Agent();
        $create_agent->owner_code = $this->owner_code;
        $create_agent->agent_type_id = 2;
        $create_agent->name = $this->name;
        $create_agent->email = $this->email;
        $create_agent->phone = $this->phoneForStorage();
        $create_agent->status = 'ACTIVO';
        $create_agent->save();

        $create_user = new User();
        $create_user->code_agent = 'AGT-000'.$create_agent->id;
        $create_user->agent_id = $create_agent->id;
        $create_user->code_agency = $create_agent->owner_code;
        $create_user->is_agent = true;
        $create_user->name = strtoupper($this->name);
        $create_user->email = $this->email;
        $create_user->password = $validated['password'];
        $create_user->link_agent = config('app.url').'/agent/c/'.Crypt::encryptString($create_agent->id);
        $create_user->status = 'ACTIVO';
        $create_user->save();

        $create_agent->sendCartaBienvenida($create_agent->id, $create_agent->name, $create_agent->email, $this->password);

        Notification::make()
            ->title('AGENTE REGISTRADO')
            ->body('BIENVENIDO A INTEGRACORP! Registro exitoso!.')
            ->icon('heroicon-m-user-plus')
            ->iconColor('success')
            ->success()
            ->seconds(5)
            ->send();

        $this->redirect(route('filament.agents.auth.login'));
    }
}; ?>

<div class="flex flex-col gap-6">

    <x-auth-header :title="__('Registro de Agentes')" :description="__('Todos los campos son requeridos')" />

    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <flux:input input icon="user" wire:model="name" :label="__('Nombre y Apellido')" type="text" autofocus
            autocomplete="fill_name" placeholder="Nombre Apellido"
            oninput="this.value = this.value.replace(/[^a-zA-Z\sáéíóúÁÉÍÓÚÑñ]/g, '')" />

        <flux:input input icon="at-symbol" wire:model="email" :label="__('Correo Electrónico')" type="email"
            autocomplete="email" placeholder="email@example.com" />

        @include('components.auth-phone-field')

        <flux:input input icon="key" wire:model="password" :label="__('Contraseña')" type="password"
            autocomplete="new-password" :placeholder="__('Contraseña')" viewable />

        <flux:input input icon="key" wire:model="password_confirmation" :label="__('Confirmar Contraseña')"
            type="password" autocomplete="new-password" :placeholder="__('Confirmar Contraseña')" viewable />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Registrar Agente') }}
            </flux:button>
        </div>
    </form>
</div>
