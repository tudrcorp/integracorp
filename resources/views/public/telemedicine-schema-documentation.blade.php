<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentación · Esquema de telemedicina</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script type="module">
        import mermaid from 'https://cdn.jsdelivr.net/npm/mermaid@11/dist/mermaid.esm.min.mjs';

        mermaid.initialize({
            startOnLoad: true,
            theme: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'default',
            er: { useMaxWidth: true },
        });
    </script>
    <style>
        html {
            scroll-behavior: smooth;
        }

        .doc-nav a.is-active {
            background: rgb(0 100 161 / 0.12);
            color: rgb(0 100 161);
            font-weight: 600;
        }

        .table-card {
            transition: opacity .2s ease, transform .2s ease;
        }

        .table-card.is-hidden {
            opacity: 0;
            transform: translateY(6px);
            display: none;
        }

        #backToTop {
            opacity: 0;
            transform: translateY(10px);
            pointer-events: none;
            transition: all .25s ease;
        }

        #backToTop.is-visible {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }

        @media (prefers-color-scheme: dark) {
            .doc-nav a.is-active {
                background: rgb(82 148 113 / 0.2);
                color: rgb(148 210 180);
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased dark:bg-[#0a0f14] dark:text-slate-100 font-[Instrument_Sans,sans-serif]">
    @php
        $tablesCount = count($tables);
        $relationsCount = count($relationships);
        $columnsCount = collect($tables)->sum(static fn (array $table): int => count($table['columns']));
    @endphp

    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:font-semibold focus:text-[#0064a1]">
        Saltar al contenido principal
    </a>

    <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-[#0a0f14]/90">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <div class="flex items-center gap-3">
                <div class="rounded-xl bg-white px-2 py-1 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                    <img src="{{ asset('image/logoNewPdf.png') }}" alt="Logo Tu Doctor en Casa" class="h-10 w-auto sm:h-12">
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-[#0064a1] dark:text-emerald-300">Documentación pública</p>
                    <h1 class="text-sm font-semibold sm:text-base">Esquema de datos · Telemedicina</h1>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs text-slate-500 dark:text-slate-400">Versión {{ $version }}</p>
                <p class="text-xs text-slate-500 dark:text-slate-400">Actualizado {{ $updatedAt }}</p>
            </div>
        </div>
    </header>

    <div class="mx-auto max-w-7xl px-4 pt-6 pb-4 sm:px-6">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-r from-[#0064a1] via-[#0b77ba] to-[#0a90d4] p-6 text-white shadow-sm dark:border-slate-700">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-sky-100">Data Catalog</p>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">Diccionario de datos y diagrama E/R de telemedicina</h2>
                    <p class="mt-3 text-sm leading-relaxed text-sky-50/95 sm:text-base">
                        Consulta centralizada para navegar relaciones, estructura de columnas y convenciones del esquema legado. Ideal para desarrollo, QA, auditoría y onboarding técnico.
                    </p>
                </div>
                <div class="grid grid-cols-3 gap-2 text-center sm:gap-3">
                    <div class="rounded-xl bg-white/15 px-3 py-2">
                        <p class="text-xl font-bold">{{ $tablesCount }}</p>
                        <p class="text-[11px] uppercase tracking-wide text-sky-100">Tablas</p>
                    </div>
                    <div class="rounded-xl bg-white/15 px-3 py-2">
                        <p class="text-xl font-bold">{{ $relationsCount }}</p>
                        <p class="text-[11px] uppercase tracking-wide text-sky-100">Relaciones</p>
                    </div>
                    <div class="rounded-xl bg-white/15 px-3 py-2">
                        <p class="text-xl font-bold">{{ $columnsCount }}</p>
                        <p class="text-[11px] uppercase tracking-wide text-sky-100">Columnas</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 pb-10 lg:flex-row lg:px-6">
        <aside class="lg:w-64 lg:shrink-0">
            <div class="sticky top-20 space-y-3">
                <label for="tableSearch" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    Buscar tabla o columna
                </label>
                <div class="relative">
                    <svg aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none">
                        <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Zm10 2-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                    <input
                        id="tableSearch"
                        type="search"
                        placeholder="Ej: telemedicine_case_id"
                        class="w-full rounded-xl border border-slate-200 bg-white py-2 pl-9 pr-3 text-sm text-slate-700 outline-none ring-0 placeholder:text-slate-400 focus:border-[#0064a1] dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    >
                </div>

                <nav class="doc-nav max-h-[70vh] space-y-1 overflow-auto rounded-xl border border-slate-200 bg-white p-3 text-sm dark:border-slate-800 dark:bg-slate-900/60" aria-label="Contenido">
                    <p class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Contenido</p>
                    <a href="#intro" class="block rounded-lg px-2 py-1.5 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Introducción</a>
                    <a href="#er-diagram" class="block rounded-lg px-2 py-1.5 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Diagrama E/R</a>
                    <a href="#relaciones" class="block rounded-lg px-2 py-1.5 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Relaciones</a>
                    <p class="mt-3 mb-1 px-2 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Diccionario</p>
                    @foreach ($tables as $table)
                        <a
                            href="#{{ $table['slug'] }}"
                            class="table-link block rounded-lg px-2 py-1.5 text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                            data-table-link="{{ strtolower($table['name'].' '.$table['title']) }}"
                        >
                            <span class="font-mono text-[11px]">{{ $table['name'] }}</span>
                        </a>
                    @endforeach
                </nav>
                <p class="text-xs text-slate-500 dark:text-slate-400">Tip: presiona <kbd class="rounded bg-slate-200 px-1 py-0.5 text-[10px] dark:bg-slate-800">/</kbd> para enfocar el buscador.</p>
            </div>
        </aside>

        <main id="main-content" class="min-w-0 flex-1 space-y-12">
            <section id="intro" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/30">
                <h2 class="text-2xl font-bold tracking-tight text-[#0064a1] dark:text-emerald-300">Introducción</h2>
                <p class="mt-3 max-w-4xl text-slate-600 leading-relaxed dark:text-slate-300">
                    Esta página documenta el modelo de datos del módulo de telemedicina según el esquema legado de producción.
                    Incluye un diagrama entidad-relación, el mapa de relaciones lógicas entre tablas y un diccionario de datos
                    con tipos, nulabilidad y descripción de cada columna.
                </p>
                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="font-semibold">Motor base: InnoDB · utf8mb4</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">Optimizado para consistencia transaccional y compatibilidad Unicode.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="font-semibold">Relaciones declaradas como lógicas</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">Las FK no están explícitas en DDL; se infieren por convención de columnas.</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="font-semibold">Tablas externas referenciadas</p>
                        <p class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-300">telemedicine_doctors, catálogos de servicio y coordinación</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-800/50">
                        <p class="font-semibold">Cobertura documental</p>
                        <p class="mt-1 text-slate-600 dark:text-slate-300">{{ $tablesCount }} tablas y {{ $columnsCount }} columnas revisadas.</p>
                    </div>
                </div>
            </section>

            <section id="er-diagram" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/30">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-2xl font-bold tracking-tight">Diagrama entidad-relación</h2>
                    <span class="rounded-full bg-[#0064a1]/10 px-3 py-1 text-xs font-semibold text-[#0064a1] dark:bg-emerald-900/40 dark:text-emerald-300">
                        Mermaid ER
                    </span>
                </div>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Vista arquitectónica del flujo principal. Puedes desplazar horizontalmente el diagrama en dispositivos pequeños.
                </p>
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/50">
                    <pre class="mermaid text-center">{{ $mermaidErDiagram }}</pre>
                </div>
            </section>

            <section id="relaciones" class="scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/30">
                <h2 class="text-2xl font-bold tracking-tight">Relaciones lógicas</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Referencias entre tablas con su cardinalidad y la columna de enlace usada en la práctica.
                </p>
                <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-100 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold">Origen</th>
                                <th class="px-4 py-2 text-left font-semibold">Cardinalidad</th>
                                <th class="px-4 py-2 text-left font-semibold">Destino</th>
                                <th class="px-4 py-2 text-left font-semibold">Columna</th>
                                <th class="px-4 py-2 text-left font-semibold">Etiqueta</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-900/20">
                            @foreach ($relationships as $relationship)
                                <tr class="align-top">
                                    <td class="px-4 py-2 font-mono text-xs">{{ $relationship['from'] }}</td>
                                    <td class="px-4 py-2">
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">{{ $relationship['cardinality'] }}</span>
                                    </td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $relationship['to'] }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-[#0064a1] dark:text-emerald-300">{{ $relationship['via'] }}</td>
                                    <td class="px-4 py-2 text-slate-600 dark:text-slate-300">{{ $relationship['label'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="scroll-mt-24">
                <h2 class="text-2xl font-bold tracking-tight">Diccionario de datos</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                    Definición columna por columna según el esquema SQL de referencia.
                </p>
            </section>

            @foreach ($tables as $table)
                <section
                    id="{{ $table['slug'] }}"
                    class="table-card scroll-mt-24 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/30"
                    data-table-name="{{ strtolower($table['name'].' '.$table['title'].' '.$table['description']) }}"
                >
                    <div class="flex flex-wrap items-baseline gap-2">
                        <h3 class="text-xl font-bold">{{ $table['title'] }}</h3>
                        <code class="rounded bg-slate-200 px-2 py-0.5 text-xs dark:bg-slate-800">{{ $table['name'] }}</code>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ count($table['columns']) }} columnas
                        </span>
                    </div>
                    <p class="mt-2 text-slate-600 dark:text-slate-300">{{ $table['description'] }}</p>

                    @if (! empty($table['notes']))
                        <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-amber-800 dark:text-amber-200/90">
                            @foreach ($table['notes'] as $note)
                                <li>{{ $note }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                            <thead class="bg-slate-100 dark:bg-slate-800/60">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold">Columna</th>
                                    <th class="px-3 py-2 text-left font-semibold">Tipo</th>
                                    <th class="px-3 py-2 text-left font-semibold">Nulo</th>
                                    <th class="px-3 py-2 text-left font-semibold">Default</th>
                                    <th class="px-3 py-2 text-left font-semibold">Descripción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white dark:divide-slate-800 dark:bg-slate-900/20">
                                @foreach ($table['columns'] as $column)
                                    <tr
                                        class="align-top"
                                        data-column-row="{{ strtolower($column['name'].' '.$column['type'].' '.$column['description'].' '.($column['default'] ?? '')) }}"
                                    >
                                        <td class="px-3 py-2 font-mono text-xs font-medium">{{ $column['name'] }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $column['type'] }}</td>
                                        <td class="px-3 py-2">
                                            @if ($column['nullable'])
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">Sí</span>
                                            @else
                                                <span class="rounded bg-[#0064a1]/10 px-1.5 py-0.5 text-xs text-[#0064a1] dark:bg-emerald-900/40 dark:text-emerald-300">No</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs">
                                            @if ($column['default'] !== null)
                                                {{ $column['default'] }}
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $column['description'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </main>
    </div>

    <button
        id="backToTop"
        type="button"
        class="fixed bottom-5 right-5 z-20 rounded-full bg-[#0064a1] px-4 py-2 text-xs font-semibold text-white shadow-lg hover:bg-[#005080] focus:outline-none focus:ring-2 focus:ring-[#0064a1] dark:bg-emerald-700 dark:hover:bg-emerald-600"
    >
        Subir
    </button>

    <footer class="border-t border-slate-200 py-6 text-center text-xs text-slate-500 dark:border-slate-800 dark:text-slate-500">
        &copy; {{ date('Y') }} TuDrEnCasa.com · Documentación de esquema de telemedicina
    </footer>

    <script>
        (function () {
            const links = document.querySelectorAll('.doc-nav a[href^="#"]');
            const sections = [...links].map((a) => document.querySelector(a.getAttribute('href'))).filter(Boolean);
            const searchInput = document.getElementById('tableSearch');
            const tableCards = document.querySelectorAll('.table-card');
            const tableLinks = document.querySelectorAll('.table-link');
            const backToTop = document.getElementById('backToTop');

            const setActive = () => {
                let current = sections[0]?.id;
                const offset = 120;

                for (const section of sections) {
                    if (section.getBoundingClientRect().top <= offset) {
                        current = section.id;
                    }
                }

                links.forEach((link) => {
                    const href = link.getAttribute('href').slice(1);
                    link.classList.toggle('is-active', href === current);
                });
            };

            const applySearchFilter = (rawTerm) => {
                const term = rawTerm.trim().toLowerCase();

                tableLinks.forEach((link) => {
                    const haystack = link.dataset.tableLink ?? '';
                    const match = term === '' || haystack.includes(term);
                    link.classList.toggle('hidden', !match);
                });

                tableCards.forEach((card) => {
                    const tableName = card.dataset.tableName ?? '';
                    const rows = card.querySelectorAll('[data-column-row]');
                    let hasVisibleRows = false;

                    rows.forEach((row) => {
                        const rowText = row.dataset.columnRow ?? '';
                        const matchRow = term === '' || rowText.includes(term);
                        row.classList.toggle('hidden', !matchRow);
                        hasVisibleRows = hasVisibleRows || matchRow;
                    });

                    const matchTable = term === '' || tableName.includes(term);
                    card.classList.toggle('is-hidden', !(matchTable || hasVisibleRows));
                });
            };

            const syncBackToTopVisibility = () => {
                backToTop.classList.toggle('is-visible', window.scrollY > 500);
            };

            window.addEventListener('scroll', () => {
                setActive();
                syncBackToTopVisibility();
            }, { passive: true });

            if (searchInput) {
                searchInput.addEventListener('input', (event) => {
                    applySearchFilter(event.target.value);
                });
            }

            window.addEventListener('keydown', (event) => {
                if (event.key === '/' && document.activeElement !== searchInput) {
                    event.preventDefault();
                    searchInput?.focus();
                }
            });

            backToTop?.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });

            setActive();
            syncBackToTopVisibility();
        })();
    </script>
</body>
</html>
