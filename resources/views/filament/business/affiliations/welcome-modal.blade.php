@php
    $cardClasses = implode(' ', [
        'overflow-hidden rounded-2xl',
        'bg-gradient-to-b from-blue-50 to-sky-100 dark:from-blue-950 dark:to-sky-900/90',
        'shadow-lg dark:shadow-xl dark:shadow-black/30',
    ]);
    $iconWrapperClasses = implode(' ', [
        'flex size-14 items-center justify-center rounded-2xl',
        'bg-blue-500/20 dark:bg-blue-400/25',
        'text-blue-700 dark:text-blue-300',
    ]);
@endphp
<div class="{{ $cardClasses }}">
    <div class="space-y-4 p-6 sm:p-8">
        <div class="flex justify-center">
            <span class="{{ $iconWrapperClasses }}">
                <svg class="size-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
            </span>
        </div>
        <div class="space-y-2 text-center">
            <h3 class="text-lg font-semibold tracking-tight text-gray-900 dark:text-white">
                Bienvenido al módulo de Afiliaciones Individuales
            </h3>
            <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                Desde aquí puedes gestionar las afiliaciones individuales: consultar el listado, crear nuevas afiliaciones, ver estadísticas por plan y por estado, y analizar el comportamiento de las afiliaciones a lo largo del tiempo.
            </p>
        </div>
        <p class="text-center text-xs text-gray-600 dark:text-gray-400">
            Cierra con Escape o tocando fuera
        </p>
    </div>
</div>
