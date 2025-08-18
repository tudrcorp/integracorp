<?php

use Flux\Flux;
use App\Models\Plan;
use Barryvdh\DomPDF\PDF;
use App\Models\BenefitPlan;
use Livewire\Volt\Component;
use App\Models\IndividualQuote;
use Livewire\Attributes\Layout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Filament\Agents\Widgets\IndividualQuoteChart;

$h = 60;
new #[Layout('components.layouts.interactive')] class extends Component {
    //
    public $quote;

    public $data;

    public $plan_id;

    public $definition_plan;

    public $benefits;

    public $collect, $group_collect, $totalColumns;

    public $prueba = 0;

    public $show_inicial    = 'hidden';
    public $show_ideal      = 'hidden';
    public $show_especial   = 'hidden';

    public $details = [];

    public $agent;

    public function mount()
    {
        // Recibir el parámetro de la URL
        $this->quote = Crypt::decryptString(Route::current()->parameter('quote'));
        // Ahora puedes usar $this->quote para cargar datos
        // Ej: $this->affiliates = Affiliate::where('corporate_quote_id', $this->quote)->get();

        $record = IndividualQuote::where('id', $this->quote)->first();

        if ($record->plan == 1) {
            $detalle = DB::table('detail_individual_quotes')
                ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                ->where('individual_quote_id', $record->id)
                ->get()
                ->toArray();
            $details = [
                'plan' => $record->plan,
                'code' => $record->code,
                'name' => $record->full_name,
                'email' => $record->email,
                'phone' => $record->phone,
                'date' => $record->created_at->format('d-m-Y'),
                'data' => $detalle
            ];

            $this->collect = collect($details['data'][0]);
        }

        if ($record->plan != 1) {
            $detalle = DB::table('detail_individual_quotes')
                ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                ->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                ->where('individual_quote_id', $this->quote)
                ->get()
                ->toArray();

            $details = [
                'plan' => $record->plan,
                'code' => $record->code,
                'name' => $record->full_name,
                'email' => $record->email,
                'phone' => $record->phone,
                'date' => $record->created_at->format('d-m-Y'),
                'data' => $detalle
            ];

            $this->collect = collect($details['data']);
        }


        // dd($detalle);
        /**
         * Se envia el certificado del afiliado
         * ----------------------------------------------------------------------------------------------------
         */


        $this->plan_id = $details['plan'];

        $this->details = $details;

        $this->benefits = BenefitPlan::where('plan_id', $this->plan_id)->get();

        $this->definition_plan = Plan::where('id', $this->plan_id)->first()->description;

        $this->group_collect = $this->collect->groupBy('age_range');

        $this->agent = $record->created_by;


        if ($this->plan_id == 2) {
            $totalColumns = [0, 0, 0, 0, 0];
        }
        if ($this->plan_id == 3) {
            $totalColumns = [0, 0, 0, 0, 0, 0];
        }

        // Recorrer los datos para sumar por columna
        foreach ($this->group_collect as $key => $value) {
            foreach ($value as $index => $item) {
                if (isset($totalColumns[$index])) {
                    $totalColumns[$index] += round($item->subtotal_anual);
                    $this->totalColumns = $totalColumns;
                }
            }
        }
    }

    public function download()
    {
        $id = $this->quote;
        $individual_quote = IndividualQuote::where('id', $id)->first();
        //descargo la cotizacion de mi carpeta storage
        $file = public_path('storage/quotes/' . $individual_quote->code .'.pdf');
        return response()->download($file);
    }
}; ?>

<div
    class="container flex flex-col w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0 md:px-10">

    <div class="flex flex-col justify-center items-center mb-5 mt-5">

        <div class="flex justify-center items-center h-12">
            <img class="h-12" src="{{ asset('image/logo_new.png') }}" alt="Logo" />
        </div>

        <flux:text class="mt-2 mb-6 text-base text-center">
            <div class="flex flex-col justify-center items-center text-md mb-5">
                <p>PROPUESTA ECONÓMICA</p>
                <p>Sr(a): {{ $details['name'] }}, Fecha: {{ $details['date'] }}</p>
                <p>Agente: {{ $agent }}</p>
            </div>
        </flux:text>

        <flux:separator variant=" subtle" class="mb-5" />

        <flux:button wire:click="download()" class="mb-3 cursor-pointer" icon="arrow-down-tray">Descargar Cotización
        </flux:button>
    </div>

    @if ($plan_id == 1)
    <!-- Plan Beneficios -->
    <div
        class=" w-full lg:px-8 lg:py-4 overflow-x-auto p-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5">

        <flux:table align="center">

            @include('header_inicial')

            @foreach ($benefits as $value)
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell align="left">{{ $value->description }}</flux:table.cell>
                    <flux:table.cell align=" center">

                        <div class="flex justify-center items-center text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round"
                                class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>

                        </div>

                    </flux:table.cell>
                </flux:table.row>
            </flux:table.rows>
            @endforeach

        </flux:table>

    </div>
    <!-- Plan Tarifas Generales -->
    <div
        class=" w-full lg:px-8 lg:py-4 overflow-x-auto p-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5 mt-5">
        <flux:heading size="xl" level="1" class="text-center">Tarifas Generales
        </flux:heading>
        <flux:text class="mt-2 mb-6 text-base text-center">Calculo de tarifas de acuerdo con la frecuencia de
            pago
        </flux:text>

        <flux:table align="center">
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell align="center">RANGO DE EDAD</flux:table.cell>
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ $collect['age_range'] }} US$
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell align="center">POBLACIÓN</flux:table.cell>
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ $collect['total_persons'] }} Persona(s)
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell align="center">TARIFA INDIVIDUAL</flux:table.cell>
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ $collect['fee'] }} US$
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>

            </flux:table.rows>
        </flux:table>

        <flux:separator text="tarifa grupales" />

        <flux:table align="center">
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell align="center">TARIFA ANUAL</flux:table.cell>
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ $collect['subtotal_anual'] }} US$
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell align="center">TARIFA SEMESTRAL</flux:table.cell>
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ $collect['subtotal_biannual'] }} US$
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell align="center">TARIFA TRIMESTRAL</flux:table.cell>
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ $collect['subtotal_quarterly'] }} US$
                        </flux:badge>
                    </flux:table.cell>
                </flux:table.row>

            </flux:table.rows>
        </flux:table>



    </div>

    @else
    @include('interative_quote_ideal_especial')
    @endif

</div>