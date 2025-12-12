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
        <a href="{{ route('filament.business.pages.dashboard') }}" class="

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
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            <span class="xs:hidden sm:hidden lg:block">NEGOCIOS</span>
        </a>

    </nav>

</div>

