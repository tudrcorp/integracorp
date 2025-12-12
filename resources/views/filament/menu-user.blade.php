@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@php

    $visible = false;
    // 3. Obtener el usuario autenticado (útil para verificar permisos/roles)
    $user = auth()->user()->departament;
    if(in_array('SUPERADMIN', $user)){
        $visible = true;
    }

@endphp

<div class="{{ $visible ? 'sm:hidden lg:block' : 'hidden' }} ">

    <nav class="flex items-center space-x-2 p-4">


        <!-- 
            Nota: En un entorno Filament, la clase 'brand-primary' ya está configurada 
            automáticamente en tu paleta de colores para el color principal (primary).
            Usaremos 'bg-primary-500' y 'text-primary-500' como referencia. 
        -->

        <!-- Botón 2: Proyectos (Inactivo) -->
        <a href="{{ route('filament.administration.pages.dashboard') }}" class="

            flex items-center justify-center space-x-2
            px-5 py-2 
            text-sm font-medium 
            text-gray-700 dark:text-gray-300 
            bg-white dark:bg-black
            rounded-full 
            shadow-sm 
            transition-all duration-200 
            hover:bg-gray-100 hover:text-primary-600
            dark:hover:bg-gray-700 dark:hover:text-primary-400
            border-2 border-[#00a7d1] dark:ring-[#00a7d1]

        " style="background-color: #0000;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6" />
            </svg>                        
            <span class="xs:hidden sm:hidden lg:block">ADMINISTRACIÓN</span>
        </a>

        <!-- Botón 3: Reportes (Inactivo) -->
        <a href="{{ route('filament.marketing.pages.dashboard') }}" class="
            flex items-center justify-center space-x-2
            px-5 py-2 
            text-sm font-medium 
            text-gray-700 dark:text-gray-300 
            bg-white dark:bg-black
            rounded-full 
            shadow-sm 
            transition-all duration-200 
            hover:bg-gray-100 hover:text-primary-600
            dark:hover:bg-gray-700 dark:hover:text-primary-400
            border-2 border-[#00a7d1] dark:ring-[#00a7d1]

        " style="background-color: #0000;">

            <!-- Icono de reportes -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
            </svg>                          
            <span>MARKETING</span>
        </a>
        <!-- Botón 3: Reportes (Inactivo) -->
        <a href="{{ route('filament.operations.pages.dashboard') }}" class="
            flex items-center justify-center space-x-2
            px-5 py-2 
            text-sm font-medium 
            text-gray-700 dark:text-gray-300 
            bg-white dark:bg-black
            rounded-full 
            shadow-sm 
            transition-all duration-200 
            hover:bg-gray-100 hover:text-primary-600
            dark:hover:bg-gray-700 dark:hover:text-primary-400
            border-2 border-[#00a7d1] dark:ring-[#00a7d1]

        " style="background-color: #0000;">

            <!-- Icono de reportes -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 0 0 5.304 0l6.401-6.402M6.75 21A3.75 3.75 0 0 1 3 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 0 0 3.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008Z" />
            </svg>              
            <span>PROVEEDORES</span>
        </a>

    </nav>

</div>
