<?php

use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Validate;
use Illuminate\Validation\Rules\In;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\NotificationController;

new #[Layout('components.layouts.auth.split')] class extends Component {


    public string $owner_code;
    public string $name;
    public string $email;
    public string $type;
    public string $phone;
    public string $password;
    public string $password_confirmation;

    public function mount($code = null)
    {
        $code_decrypted = isset($code) ? Crypt::decryptString($code) : 'TDG-100';
        $this->owner_code = $code_decrypted;
    }

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        // dd($this->type);
        try {

            $validated = $this->validate([
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . Agency::class],
                'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            ]);

            $validated['password'] = Hash::make($validated['password']);


            /**
             * Registro de agencia general
             */
            if ($this->type == '3g') {

                /**
                 * Generamos el código de la agencia
                 * ---------------------------------------------------
                 */
                $code = AgencyController::generate_code_agency();


                $create_agency = new Agency();
                $create_agency->owner_code       = $this->owner_code;
                $create_agency->code             = $code;
                $create_agency->agency_type_id   = '3';
                $create_agency->name_corporative = $this->name;
                $create_agency->email            = $this->email;
                $create_agency->save();

                /**
                 * Creamos el registro en la tabla de usuarios
                 * -------------------------------------------------------------------------------------------------------
                 */
                $create_user = new User();
                $create_user->name = $create_agency->name_corporative;
                $create_user->email = $create_agency->email;
                $create_user->password = $validated['password'];
                $create_user->is_agency = true;
                $create_user->code_agency = $create_agency->code;
                $create_user->agency_type = 'GENERAL';
                $create_user->link_agency = env('APP_URL') . '/agency/c/' . Crypt::encryptString($create_agency->code);
                $create_user->status = 'ACTIVO';
                $create_user->save();

                /**
                 * Notificación por whatsapp
                 * -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
                 * @param Agency $create_agency
                 */
                $phone = $create_agency->phone;
                $email = $create_agency->email;
                $nofitication = NotificationController::agency_activated($create_agency->code, $phone, $email, $create_agency->agency_type_id == 1 ? config('parameters.PATH_MASTER') : config('parameters.PATH_GENERAL'), $this->password);

                /**
                 * Notificacion por correo electronico
                 * CARTA DE BIENVENIDA
                 * ----------------------------------------------------------------------------------------------------------
                 * @param Agency $create_agency
                 */
                $create_agency->sendCartaBienvenida($create_agency->code, $create_agency->name_corporative, $create_agency->email);

                Notification::make()
                    ->title('AGENCIA REGISTRADA')
                    ->body('El registro fue enviado con exito')
                    ->icon('heroicon-m-user-plus')
                    ->iconColor('success')
                    ->success()
                    ->seconds(5)
                    ->send();

                $this->redirect(route('filament.general.auth.login'));
            }
            
            /**
             * Registro de agente
             * ------------------------------------------------------------------------------------------------
             */
            if ($this->type == '2a') {

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
                $create_user->code_agent        = 'AGT-000' . $create_agent->id;
                $create_user->agent_id          = $create_agent->id;
                $create_user->code_agency       = $create_agent->owner_code;
                $create_user->is_agent          = true;
                $create_user->name              = $this->name;
                $create_user->email             = $this->email;
                $create_user->password          = $validated['password'];
                $create_user->link_agent        = env('APP_URL') . '/agent/c/' . Crypt::encryptString($create_agent->id);
                $create_user->status            = 'ACTIVO';
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
                
            }

            /**
             * Registro de subagente
             * ------------------------------------------------------------------------------------------------
             */
            // if ($this->type == '3sa') {

            //     /**
            //      * Creamos el agente
            //      * y cargamos la informacion previa en la tabla de agent
            //      */
            //     $create_agent = new Agent();
            //     $create_agent->owner_code       = $this->owner_code;
            //     $create_agent->agent_type_id    = 2;
            //     $create_agent->name             = $this->name;
            //     $create_agent->email            = $this->email;
            //     $create_agent->save();

            //     /**
            //      * Creamos el registro en la tabla de usuarios
            //      */
            //     $create_user = new User();
            //     $create_user->code_agent = 'AGT-000' . $create_agent->id;
            //     $create_user->agent_id          = $create_agent->id;
            //     $create_user->code_agency       = $create_agent->owner_code;
            //     $create_user->is_agent          = true;
            //     $create_user->name              = $this->name;
            //     $create_user->email             = $this->email;
            //     $create_user->password          = $validated['password'];
            //     $create_user->link_agent = env('APP_URL') . '/agent/c/' . Crypt::encryptString($create_agent->id);
            //     $create_user->status = 'ACTIVO';
            //     $create_user->save();

            //     /**
            //      * Notificacion por correo electronico
            //      * CARTA DE BIENVENIDA
            //      * @param Agent $record
            //      */
            //     $create_agent->sendCartaBienvenida($create_agent->id, $create_agent->name, $create_agent->email);

            //     Notification::make()
            //         ->title('AGENTE REGISTRADA')
            //         ->body('El registro fue enviado con exito')
            //         ->icon('heroicon-m-user-plus')
            //         ->iconColor('success')
            //         ->success()
            //         ->seconds(5)
            //         ->send();

            //     $this->redirect(route('filament.agents.auth.login'));
            // }

            //code...
        } catch (\Throwable $th) {
            dd($th); //$th;
        }
    }
}; ?>

<div class="flex flex-col gap-6">

    <x-auth-header :title="__('Registro Principal')" :description="__('Estructura comercial')" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">

        <flux:radio.group wire:model="type" variant="segmented" label="Tipo" :indicator="false" required
            class="max-sm:flex-col">
            <flux:radio value="3g" icon="cube" label="Agencia General"
                description="Agencias con estructura comercial" />
            <flux:radio value="2a" icon="user" label="Agente" />
            <!-- <flux:radio value="3sa" icon="building-storefront" label="Subagente" /> -->
        </flux:radio.group>


        <!-- Name -->
        <flux:input input icon="user" wire:model="name" :label="__('Nombre/Razón Social')" type="text" required
            autofocus autocomplete="name" placeholder="Nombre Apellido"
            oninput="this.value = this.value.replace(/[^a-zA-Z\sáéíóúÁÉÍÓÚÑñ]/g, '')" />

        <!-- Email Address -->
        <flux:input input icon="at-symbol" wire:model=" email" :label="__('Correo Electrónico')" type="email" required
            autocomplete="email" placeholder="email@example.com" />

        <!-- Email Address -->
        <flux:input input icon="phone" wire:model=" phone" :label="__('Nro. de Telefono')" type="tel" required
            autocomplete="email" placeholder="04127018390" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />

        <!-- Password -->
        <flux:input input icon="key" wire:model="password" :label="__('Password')" type="password" required
            autocomplete="new-password" :placeholder="__('Contraseña')" viewable />

        <!-- Confirm Password -->
        <flux:input input icon="key" wire:model="password_confirmation" :label="__('Confirm password')" type="password"
            required autocomplete="new-password" :placeholder="__('Confirmar Contraseña')" viewable />

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Registrar Agencia') }}
            </flux:button>
        </div>
    </form>

    <!-- <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
        {{ __('Ya tienes una cuenta registrada en INTEGRACORP?') }}
        <flux:link :href="route('filament.agents.auth.login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div> -->
</div>