<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-white dark:bg-zinc-800 p-8">
    @php
        use App\Models\IndividualQuote;
            $individual_quote = IndividualQuote::where('id', $individual_quote_id)->first();
    @endphp

    {{-- <div class="flex h-full w-full flex-col gap-4 rounded-xl">
        <div class="relative flex flex-col gap-3 overflow-x-scroll scroll-smooth hide-scrollbar">

            <!-- Imagen 1 -->
            <div class="relative rounded-lg overflow-hidden">
                <!-- Imagen de fondo con z-index negativo -->
                <img src="{{ asset('image/prueba.png') }}" class="relative h-full w-full object-cover">
                
                <!-- Contenido delante -->
                <div style="position: absolute; top: 120px;" class="inset-0">
                    <div class="flex flex-col justity-start items-center">
                        <h1 class="text-xl font-semibold text-white">Propuesta económica</h1>
                        <div class="text-xl font-semibold text-white border-2 border-black">
                            Sr(a): {{ $individual_quote->full_name }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Imagen 2 -->
            <div class="flex-shrink-0 w-32 h-full rounded-lg overflow-hidden">
                <img src="{{ asset('image/prueba2.png') }}" alt="Imagen 2" class="object-cover w-full h-full">
            </div>

            <!-- Imagen 3 -->
            <div class="relative flex-shrink-0 w-32 h-full rounded-lg overflow-hidden">
                <!-- Imagen de fondo con z-index negativo -->
                <img src="{{ asset('image/footer-ideal.png') }}" class="relative h-full w-full object-cover">

                <!-- Contenido delante -->
                <div style="position: absolute; top: 0px; " >
                    <div class="flex flex-col justify-start items-center">
                        <h1 class="text-sm font-semibold text-black">Propuesta económica</h1>
                            <div class="text-sm sm:text-xl md:text-2xl font-semibold text-black border-2 border-black">
                                Sr(a): {{ $individual_quote->full_name }}
                            </div>
                    </div>
                </div>
            </div>

            <!-- Imagen 4 -->
            <div class="flex-shrink-0 w-32 h-full rounded-lg overflow-hidden">
                <img src="{{ asset('image/prueba3.png') }}" alt="Imagen 2" class="relative inset-0 object-cover w-full h-full">
            </div>

        </div>
    </div> --}}

    @fluxScripts
</body>
</html>

