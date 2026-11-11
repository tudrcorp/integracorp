@php
    use App\Support\Affiliations\Certificates\PlanCertificateTemplateEngine as Engine;
    $mk = fn (string $key): string => '{{'.$key.'}}';
@endphp
<div class="space-y-4 text-sm">
    <div class="rounded-lg bg-sky-50 px-3 py-2 text-sky-900 ring-1 ring-inset ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-100 dark:ring-sky-400/30">
        Hoja carta de 816 × 1056 px. Maquete con <strong>tablas y anchos fijos</strong> (sin flex ni grid), márgenes con <em>padding</em> en celdas.
        Imágenes: solo <code>src="{{ $mk('imagen.logo') }}"</code> y las demás de la lista, o incrustadas en base64. Lo externo (http://…) se elimina.
        El diseño puede ocupar hasta {{ \App\Support\Affiliations\Certificates\PlanCertificateCover::MAX_PAGES }} hojas.
    </div>

    @foreach (Engine::catalog() as $group => $items)
        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $group }}</p>
            <table class="w-full text-xs">
                @foreach ($items as $key => [$description, $example])
                    <tr class="border-b border-gray-950/5 dark:border-white/10">
                        <td class="w-64 py-1 pe-2 font-mono text-primary-700 dark:text-primary-300">{{ $mk($key) }}</td>
                        <td class="py-1 pe-2 text-gray-700 dark:text-gray-200">{{ $description }}</td>
                        <td class="py-1 text-gray-500 dark:text-gray-400">{{ $example }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach

    @foreach (Engine::loopCatalog() as $blockName => $items)
        <div>
            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bloque que se repite: {{ $blockName }}</p>
            <p class="mb-1 font-mono text-xs text-gray-600 dark:text-gray-300">&lt;!-- inicio:{{ $blockName }} --&gt; … &lt;!-- fin:{{ $blockName }} --&gt;{{ $blockName === 'afiliados' ? ' (hasta '.Engine::COVER_PEOPLE_LIMIT.' en la hoja)' : '' }}</p>
            <table class="w-full text-xs">
                @foreach ($items as $key => $description)
                    <tr class="border-b border-gray-950/5 dark:border-white/10">
                        <td class="w-64 py-1 pe-2 font-mono text-primary-700 dark:text-primary-300">{{ $mk($key) }}</td>
                        <td class="py-1 text-gray-700 dark:text-gray-200">{{ $description }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endforeach

    <div>
        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Condiciones</p>
        <p class="mb-1 font-mono text-xs text-gray-600 dark:text-gray-300">&lt;!-- si:pagado --&gt; … &lt;!-- fin:pagado --&gt; · lo contrario: &lt;!-- si:no_pagado --&gt; … &lt;!-- fin:no_pagado --&gt;</p>
        <table class="w-full text-xs">
            @foreach (Engine::conditions() as $key => $description)
                <tr class="border-b border-gray-950/5 dark:border-white/10">
                    <td class="w-64 py-1 pe-2 font-mono text-primary-700 dark:text-primary-300">si:{{ $key }}</td>
                    <td class="py-1 text-gray-700 dark:text-gray-200">{{ $description }}</td>
                </tr>
            @endforeach
        </table>
    </div>
</div>
