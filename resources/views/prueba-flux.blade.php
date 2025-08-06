<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>

</head>
<style>
    .hide-scrollbar {
        -ms-overflow-style: none;
        /* IE y Edge */
        scrollbar-width: none;
        /* Firefox */
    }

    .hide-scrollbar::-webkit-scrollbar {
        display: none;
        /* Chrome, Safari, Opera */
    }

</style>

<body class="min-h-screen bg-white dark:bg-zinc-800 p-5">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">

        <!-- Contenido principal (de fondo) -->
        {{-- <div class="flex items-center justify-center h-full">
            <h1 class="text-2xl font-bold text-gray-700">Contenido Principal</h1>
        </div> --}}

        <!-- Scroll de imágenes (posición absoluta en la parte inferior) -->
        <div class="absolute inset-0 object-cover w-full h-full p-5">

            <h2 class="mb-2 text-sm font-semibold text-white text-center">{{ $name }}</h2>
            <h2 class="mb-2 text-sm font-semibold text-white text-center">Sr(a). Gustavo Camacho</h2>
            <h2 class="mb-2 text-sm font-semibold text-white text-center">Cotización Plan Inicial</h2>


            {{-- grid-flow-col auto-rows-min --}}

            <div class="flex flex-col gap-3 overflow-x-scroll scroll-smooth hide-scrollbar">
                <!-- Imagen 1 -->
                <div class="flex-shrink-0 w-32 h-full rounded-lg overflow-hidden">
                    <img src="{{ asset('image/prueba.png') }}" alt="Imagen 1" class="object-cover w-full h-full">
                </div>

                <!-- Imagen 2 -->
                <div class="flex-shrink-0 w-32 h-full rounded-lg overflow-hidden">
                    <img src="{{ asset('image/prueba2.png') }}" alt="Imagen 2" class="object-cover w-full h-full">
                </div>

                <!-- Imagen 3 -->
                <div class="flex-shrink-0 w-32 h-full rounded-lg overflow-hidden">
                    <img src="{{ asset('image/prueba3.png') }}" alt="Imagen 2" class="object-cover w-full h-full">
                </div>



                <!-- Imagen 1 -->
                {{-- <div class="flex-shrink-0 w-32 h-full rounded-lg overflow-hidden ">
                    <flux:table class="w-full">
                        <flux:table.columns>
                            <flux:table.column>Customer</flux:table.column>
                            <flux:table.column>Date</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Amount</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            <flux:table.row>
                                <flux:table.cell>Lindsey Aminoff</flux:table.cell>
                                <flux:table.cell>Jul 29, 10:45 AM</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="green" size="sm" inset="top bottom">Paid</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell variant="strong">$49.00</flux:table.cell>
                            </flux:table.row>

                            <flux:table.row>
                                <flux:table.cell>Hanna Lubin</flux:table.cell>
                                <flux:table.cell>Jul 28, 2:15 PM</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="green" size="sm" inset="top bottom">Paid</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell variant="strong">$312.00</flux:table.cell>
                            </flux:table.row>

                            <flux:table.row>
                                <flux:table.cell>Kianna Bushevi</flux:table.cell>
                                <flux:table.cell>Jul 30, 4:05 PM</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="zinc" size="sm" inset="top bottom">Refunded</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell variant="strong">$132.00</flux:table.cell>
                            </flux:table.row>

                            <flux:table.row>
                                <flux:table.cell>Gustavo Geidt</flux:table.cell>
                                <flux:table.cell>Jul 27, 9:30 AM</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="green" size="sm" inset="top bottom">Paid</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell variant="strong">$31.00</flux:table.cell>
                            </flux:table.row>
                        </flux:table.rows>
                    </flux:table>
                </div> --}}

                <h2 class="mb-2 text-sm font-semibold text-white text-center mb-5">Galería rápida</h2>
            </div>


                    {{-- <img src="{{ asset('image/mobileBackgroundUno.jpg') }}" alt="Fondo full screen" class="absolute inset-0 object-cover w-full h-full">

                    <img src="{{ asset('image/mobileBackgroundDos.jpg') }}" alt="Fondo full screen" class="absolute inset-0 object-cover w-full h-full"> --}}


                    <!-- Contenido opcional sobre la imagen -->
                    {{-- <div class="relative z-10 flex items-center justify-center h-full text-white">
                        <h1 class="text-3xl font-bold text-center md:text-5xl">
                            Bienvenido a tu App
                        </h1>
                    </div> --}}

                    {{-- <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                        <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                        </div>
                        <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                        </div>
                        <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    </div> --}}
        </div>
    </div>

    


    @fluxScripts
</body>
</html>
