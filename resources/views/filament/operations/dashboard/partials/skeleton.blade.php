{{--
    Esqueleto de un widget del dashboard mientras carga (carga diferida): mismo
    ancho en la grilla y altura aproximada, con un brillo que recorre la tarjeta.
    Así la página aparece al instante y cada bloque se completa al llegar.
--}}
@props([
    'columnSpan' => [],
    'columnStart' => [],
    'height' => null,
])

<div
    {{
        (new \Illuminate\View\ComponentAttributeBag)
            ->gridColumn($columnSpan, $columnStart)
            ->class(['ops-skeleton fi-section relative overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10'])
            ->style(['height: '.($height ?? '18rem')])
    }}
    aria-busy="true"
    aria-label="Cargando"
>
    <div class="space-y-3 p-6">
        <div class="h-3 w-40 rounded-full bg-gray-200 dark:bg-white/10"></div>
        <div class="h-2.5 w-64 max-w-full rounded-full bg-gray-100 dark:bg-white/5"></div>
    </div>
    <div class="absolute inset-x-6 bottom-6 top-20 rounded-lg bg-gray-50 dark:bg-white/[0.03]"></div>
</div>
