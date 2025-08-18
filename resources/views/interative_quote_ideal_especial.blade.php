<!-- Plan Beneficios -->
<div class="hidden flex flex-col px-5 lg:block">
    <!-- Plan Beneficios -->
    <div class=" w-full lg:px-8 lg:py-4 overflow-x-auto p-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5">
        <flux:table align="center">

            @if($this->details['plan'] == 3)
            @include('header_especial')
            @else
            @include('header_ideal')
            @endif

            @foreach ($benefits as $value)
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell align="left">{{ $value->description }}</flux:table.cell>
                    <flux:table.cell align=" center">

                        <div class="flex justify-center items-center text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>

                        </div>

                    </flux:table.cell>
                    <flux:table.cell align="center">

                        <div class="flex justify-center items-center text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>

                        </div>

                    </flux:table.cell>
                    <flux:table.cell align="center">

                        <div class="flex justify-center items-center text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>

                        </div>

                    </flux:table.cell>
                    <flux:table.cell align="center">

                        <div class="flex justify-center items-center text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>

                        </div>

                    </flux:table.cell>
                    <flux:table.cell align="center">

                        <div class="flex justify-center items-center text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>

                        </div>

                    </flux:table.cell>
                    <flux:table.cell align="center" class="{{ $this->details['plan'] == 3 ? '' : 'hidden'}}">

                        <div class="flex justify-center items-center text-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
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

    <!-- Plan Tarifas por rango de edad -->
    <div class=" w-full lg:px-8 lg:py-4 overflow-x-auto p-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5">

        <flux:heading size="xl" level="1" class="text-center">Cotizacion</flux:heading>
        <flux:text class="mt-2 mb-5 text-base text-center">Calculo de tarifas por rango de edad</flux:text>

        <!-- Tabla Cotización -->
        <flux:table align="center">
            <flux:table.columns align="center">
                <flux:table.column align="center">RANGO DE EDAD</flux:table.column>
                <flux:table.column align="center">POBLACIÓN</flux:table.column>
                @if($this->details['plan'] == 3)
                @include('header_cotizacion_especial')
                @else
                @include('header_cotizacion_ideal')
                @endif
            </flux:table.columns>

            @foreach ($this->group_collect as $key => $value)
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell align="center">{{ $key }} años</flux:table.cell>
                    <flux:table.cell align=" center">{{ $value[0]->total_persons }} Persona(s)
                    </flux:table.cell>
                    @foreach ($value as $value2)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ round($value2->subtotal_anual) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
            </flux:table.rows>
            @endforeach

            </flux:table.column>

    </div>

    <!-- Plan Tarifas Generales -->
    <div class=" w-full lg:px-8 lg:py-4 overflow-x-auto p-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5">
        <flux:heading size="xl" level="1" class="text-center">Tarifas Generales
        </flux:heading>
        <flux:text class="mt-2 mb-6 text-base text-center">Calculo de tarifas de acuerdo con la frecuencia de
            pago
        </flux:text>
        <flux:table align="center">
            <flux:table.columns align="center">
                <flux:table.column align="center">TARIFAS</flux:table.column>
                @if($this->details['plan'] == 3)
                @include('header_cotizacion_especial')
                @else
                @include('header_cotizacion_ideal')
                @endif
            </flux:table.columns>
            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell align="center">GRUPAL ANUAL</flux:table.cell>
                    @foreach ($this->totalColumns as $value)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ round($value, 2) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell align="center">GRUPAL SEMESTRAL</flux:table.cell>
                    @foreach ($this->totalColumns as $value)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ round($value / 2) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
                <flux:table.row>
                    <flux:table.cell align="center">GRUPAL TRIMESTRAL</flux:table.cell>
                    @foreach ($this->totalColumns as $value)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ round($value / 4) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
            </flux:table.rows>

        </flux:table>

    </div>

</div>

<!-- Responsive -->
<div class="flex flex-col px-5 lg:hidden">
    <div class="border-2 px-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5 mt-5">
        <div class="p-5">
            <flux:heading size="xl" level="1" class="text-center">BENEFICIOS</flux:heading>
            <flux:text class="mt-2 mb-6 text-base text-center">Lista de coberturas y sus beneficios</flux:text>
            <flux:accordion transition exclusive class="mb-4">
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <flux:badge variant="solid" color="green">
                            COBERTURA US$ 5K
                        </flux:badge>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        @foreach ($benefits as $value)
                        <div class="flex flex-row justify-start">
                            <div class="flex justify-center items-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                    <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                    <path d="m9 11 3 3L22 4" />
                                </svg>
                            </div>
                            <div class="p-2">
                                <p>{{ $value->description }}</p>

                            </div>
                        </div>
                        @endforeach
                    </flux:accordion.content>
                </flux:accordion.item>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <flux:badge variant="solid" color="green">
                            COBERTURA US$ 10K
                        </flux:badge>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        @foreach ($benefits as $value)
                        <div class="flex flex-row justify-start">
                            <div class="flex justify-center items-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                    <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                    <path d="m9 11 3 3L22 4" />
                                </svg>
                            </div>
                            <div class="p-2">
                                <p>{{ $value->description }}</p>

                            </div>
                        </div>
                        @endforeach
                    </flux:accordion.content>
                </flux:accordion.item>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <flux:badge variant="solid" color="green">
                            COBERTURA US$ 20K
                        </flux:badge>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        @foreach ($benefits as $value)
                        <div class="flex flex-row justify-start">
                            <div class="flex justify-center items-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                    <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                    <path d="m9 11 3 3L22 4" />
                                </svg>
                            </div>
                            <div class="p-2">
                                <p>{{ $value->description }}</p>

                            </div>
                        </div>
                        @endforeach
                    </flux:accordion.content>
                </flux:accordion.item>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <flux:badge variant="solid" color="green">
                            COBERTURA US$ 30K
                        </flux:badge>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        @foreach ($benefits as $value)
                        <div class="flex flex-row justify-start">
                            <div class="flex justify-center items-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                    <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                    <path d="m9 11 3 3L22 4" />
                                </svg>
                            </div>
                            <div class="p-2">
                                <p>{{ $value->description }}</p>

                            </div>
                        </div>
                        @endforeach
                    </flux:accordion.content>
                </flux:accordion.item>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <flux:badge variant="solid" color="green">
                            COBERTURA US$ 40K
                        </flux:badge>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        @foreach ($benefits as $value)
                        <div class="flex flex-row justify-start">
                            <div class="flex justify-center items-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                    <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                    <path d="m9 11 3 3L22 4" />
                                </svg>
                            </div>
                            <div class="p-2">
                                <p>{{ $value->description }}</p>

                            </div>
                        </div>
                        @endforeach
                    </flux:accordion.content>
                </flux:accordion.item>
                <flux:accordion.item>
                    <flux:accordion.heading>
                        <flux:badge variant="solid" color="green">
                            COBERTURA US$ 50K
                        </flux:badge>
                    </flux:accordion.heading>
                    <flux:accordion.content>
                        @foreach ($benefits as $value)
                        <div class="flex flex-row justify-start">
                            <div class="flex justify-center items-center text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-square-check-big-icon lucide-square-check-big">
                                    <path d="M21 10.656V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.344" />
                                    <path d="m9 11 3 3L22 4" />
                                </svg>
                            </div>
                            <div class="p-2">
                                <p>{{ $value->description }}</p>

                            </div>
                        </div>
                        @endforeach
                    </flux:accordion.content>
                </flux:accordion.item>
            </flux:accordion>

        </div>
    </div>
    <!-- Plan Especial Tarifas por rango de edad -->
    <div class=" w-full lg:px-8 lg:py-4 overflow-x-auto p-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5 mt-5">

        <flux:heading size="xl" level="1" class="text-center">Cotizacion</flux:heading>
        <flux:text class="mt-2 mb-5 text-base text-center">Calculo de tarifas por rango de edad</flux:text>

        <!-- Tabla Cotización -->
        <flux:table align="center">
            <flux:table.columns align="center">
                <!-- <flux:table.column align="center">RANGO</flux:table.column> -->
                <!-- <flux:table.column align="center">POBLACIÓN</flux:table.column> -->
                @if($this->details['name'] == 'PLAN ESPECIAL')
                @include('header_cotizacion_especial')
                @else
                @include('header_cotizacion_ideal')
                @endif
            </flux:table.columns>

            @foreach ($this->group_collect as $key => $value)
            <flux:table.rows>
                <flux:table.row>
                    <!-- <flux:table.cell align="center">{{ $key }} años</flux:table.cell> -->
                    <!-- <flux:table.cell align="center">{{ $value[0]->total_persons }} Persona(s) -->
                    </flux:table.cell>
                    @foreach ($value as $value2)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            {{ $key }} años
                            <br>
                            {{ $value[0]->total_persons }} Persona(s)
                            <br>
                            {{ round($value2->subtotal_anual) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
            </flux:table.rows>
            @endforeach

            </flux:table.column>

    </div>

    <!-- Plan Tarifas Generales -->
    <div class=" w-full lg:px-8 lg:py-4 overflow-x-auto p-5 rounded-xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-zinc-900 mb-5 mt-5">
        <flux:heading size="xl" level="1" class="text-center">Tarifas Generales
        </flux:heading>
        <flux:text class="mt-2 mb-6 text-base text-center">Calculo de tarifas de acuerdo con la frecuencia de
            pago
        </flux:text>
        <flux:table align="center">
            <flux:table.columns align="center">
                <!-- <flux:table.column align="center">TARIFA GRUPAL</flux:table.column> -->
                @if($this->details['name'] == 'PLAN ESPECIAL')
                @include('header_cotizacion_especial')
                @else
                @include('header_cotizacion_ideal')
                @endif
            </flux:table.columns>
            <flux:table.rows>
                <flux:table.row>
                    <!-- <flux:table.cell align="center">ANUAL</flux:table.cell> -->
                    @foreach ($this->totalColumns as $value)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            ANUAL
                            <br>
                            {{ round($value, 2) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
                <flux:table.row>
                    <!-- <flux:table.cell align="center">SEMESTRAL</flux:table.cell> -->
                    @foreach ($this->totalColumns as $value)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            SEMESTRAL
                            <br>
                            {{ round($value / 2) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
                <flux:table.row>
                    <!-- <flux:table.cell align="center">TRIMESTRAL</flux:table.cell> -->
                    @foreach ($this->totalColumns as $value)
                    <flux:table.cell align="center">
                        <flux:badge variant="solid" color="green">
                            TRIMESTRAL
                            <br>
                            {{ round($value / 4) }} US$
                        </flux:badge>
                    </flux:table.cell>
                    @endforeach
                </flux:table.row>
            </flux:table.rows>

        </flux:table>

    </div>

</div>
