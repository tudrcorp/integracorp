<?php

use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use App\Models\IndividualQuote;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UtilsController;

new #[Layout('components.layouts.interactive')] class extends Component {
    //
    public $quote;
    public $message;

    public $client;

    public $individualQuote;

    #[On('refresh-the-component')]
    public function refresh()
    {
        $this->redirect(route('volt.home', ['quote' => $this->quote]), navigate: true);
    }

    public function mount()
    {
        // Recibir el parámetro de la URL
        // $this->quote = Crypt::decryptString(Route::current()->parameter('quote'));
        $this->quote = Route::current()->parameter('quote');

        $this->client = UtilsController::getClient($this->quote);
        // Ahora puedes usar $this->quote para cargar datos
        // Ej: $this->affiliates = Affiliate::where('corporate_quote_id', $this->quote)->get();
    }

    public function downloadConditions()
    {
        $id = Crypt::decryptString($this->quote);
        $individual_quote = IndividualQuote::where('id', $id)->first();

        if ($individual_quote->plan == 1) {
            return response()->download(public_path('storage/condicionados/CondicionesINICIAL.pdf'));
        }
        if ($individual_quote->plan == 2) {
            return response()->download(public_path('storage/condicionados/CondicionesIDEAL.pdf'));
        }
        if ($individual_quote->plan == 3) {
            return response()->download(public_path('storage/condicionados/CondicionesESPECIAL.pdf'));
        }
    }

    public function sendMessage()
    {

        $params = array(
            'token' => 'yuvh9eq5kn8bt666',
            'to' => '+584245718777',
            'body' => $this->message
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.ultramsg.com/instance117518/messages/chat",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            Log::error($err);
            return false;
        } else {
            $array = json_decode($response, true);
            if (isset($array['error'][0])) {
                Log::info($array['error'][0]['to']);
                $data = [
                    'action' => 'N-WApp => Envio de link interactivo de Cotizacion Individual',
                    'objeto' => 'NotificationController::sendLinkIndividualQuote',
                    'message' => $array['error'][0]['to'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                UtilsController::notificacionToAdmin($data);
                return false;
            }

            $this->notificationToUser();
            $this->dispatch('refresh-the-component');
        }
    }

    public function notificationToUser()
    {
        Flux::toast(
            heading: 'Mensaje Enviado!',
            text: 'El Agente se pondrá en contacto Usted a la brevedad posible. Gracias por confiar en nosotros.',
            variant: 'success'
        );
    }
}; ?>


<div class="flex max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
    <div
        class="text-[13px] leading-[20px] flex-1 p-6 pb-12 lg:p-20 bg-white dark:bg-[#161615] dark:text-[#EDEDEC] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d] rounded-es-lg rounded-ee-lg lg:rounded-ss-lg lg:rounded-ee-none">
        <h1 class="sm:mt-5 md:mt-20 mb-1 font-medium">Bienvenido(a)</h1>
        <h1 class="mb-1 font-medium">Sr(a): {{ $client }}</h1>
        <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">Agradecemos la oportunidad de presentarle nuestra
            Propuesta de Servicio.
        </p>
        <ul class="flex flex-col mb-4 lg:mb-6">
            <li
                class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:top-1/2 before:bottom-0 before:left-[0.4rem] before:absolute">
                <span class="relative py-1 bg-white dark:bg-[#161615]">
                    <span
                        class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)] w-3.5 h-3.5 border dark:border-[#3E3E3A] border-[#e3e3e0]">
                        <span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>
                    </span>
                </span>
                <span>
                    Costos
                    <a href="{{ route('volt.in.individual_quote', ['quote' => $this->quote]) }}"
                        class="inline-flex items-center space-x-1 font-medium underline underline-offset-4 text-[#f53003] dark:text-[#FF4433] ms-1">
                        <span>ver cotización</span>
                        <svg width="10" height="11" viewBox="0 0 10 11" fill="none" xmlns="http://www.w3.org/2000/svg"
                            class="w-2.5 h-2.5">
                            <path d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001" stroke="currentColor"
                                stroke-linecap="square" />
                        </svg>
                    </a>
                </span>
            </li>
            <li
                class="flex items-center gap-4 py-2 relative before:border-l before:border-[#e3e3e0] dark:before:border-[#3E3E3A] before:bottom-1/2 before:top-0 before:start-[0.4rem] before:absolute">
                <span class="relative py-1 bg-white dark:bg-[#161615]">
                    <span
                        class="flex items-center justify-center rounded-full bg-[#FDFDFC] dark:bg-[#161615] shadow-[0px_0px_1px_0px_rgba(0,0,0,0.03),0px_1px_2px_0px_rgba(0,0,0,0.06)] w-3.5 h-3.5 border dark:border-[#3E3E3A] border-[#e3e3e0]">
                        <span class="rounded-full bg-[#dbdbd7] dark:bg-[#3E3E3A] w-1.5 h-1.5"></span>
                    </span>
                </span>
                <span>
                    Condiciones
                    <div wire:click="downloadConditions" target="_blank"
                        class="inline-flex items-center space-x-1 font-medium underline underline-offset-4 text-[#f53003] dark:text-[#FF4433] ms-1 cursor-pointer">
                        <span>descargar documento</span>
                        <svg width="10" height="11" viewBox="0 0 10 11" fill="none" xmlns="http://www.w3.org/2000/svg"
                            class="w-2.5 h-2.5">
                            <path d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001" stroke="currentColor"
                                stroke-linecap="square" />
                        </svg>
                    </div>
                </span>
            </li>

        </ul>
        <ul class="flex flex-col gap-3 text-sm leading-normal mb-4">
            <li>
                <!-- Apariencia -->
                <div class="">
                    <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">
                        Apariencia
                    </p>
                    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
                        <flux:radio value="light" icon="sun" />
                        <flux:radio value="dark" icon="moon" />
                        <flux:radio value="system" icon="computer-desktop" />
                    </flux:radio.group>
                </div>

            </li>
            <li>
                <!-- Boton de contacto -->
                <div class="mt-3">
                    <p class="mb-2 text-[#706f6c] dark:text-[#A1A09A]">
                        Ponte en contacto con tu Agente!
                    </p>
                    <flux:dropdown>
                        <flux:button icon="chat-bubble-oval-left" icon:variant="micro" icon:class="text-zinc-300">
                            Enviar Mensaje
                        </flux:button>

                        <flux:popover class="min-w-[30rem] flex flex-col gap-4">
                            <div class="relative">
                                <flux:textarea wire:model="message" rows="8" class="dark:bg-transparent!"
                                    placeholder="Para comunicarte con su agente, déjanos tu comentario y el agente se pondrá en contacto contigo." />
                            </div>
                            <div class="flex gap-2 justify-end">
                                <flux:button @click="open = false" variant="filled" size="sm" kbd="esc" class="w-28">
                                    Cancelar</flux:button>
                                <flux:button wire:click="sendMessage" variant="primary" size="sm" kbd="⏎" class="w-28">
                                    Enviar</flux:button>
                            </div>
                        </flux:popover>
                    </flux:dropdown>
                </div>
            </li>

        </ul>

    </div>
    <div
        class="bg-[#fff2f2] dark:bg-[#1D0002] relative lg:-ms-px -mb-px lg:mb-0 rounded-t-lg lg:rounded-t-none lg:rounded-e-lg! aspect-[335/376] lg:aspect-auto w-full lg:w-[438px] shrink-0 overflow-hidden">
        <img src="{{ asset('image/prueba.png') }}" alt="">
        <div
            class="absolute inset-0 rounded-t-lg lg:rounded-t-none lg:rounded-e-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
        </div>
    </div>

    {{-- {{ $slot }} --}}
</div>