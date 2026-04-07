@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
@php
    use App\Models\HelpDesk;
    use Filament\Facades\Filament;

    $visible = false;
    $user = auth()->user()->departament;
    if (in_array('SUPERADMIN', $user)) {
        $visible = true;
    }
    $userName = auth()->user()->name ?? '';
    $helpdeskCreateUrl = Filament::getResourceUrl(HelpDesk::class, 'create');
@endphp

{{-- Contenedor: botón visible para todos; pastilla de módulos solo SUPERADMIN --}}
<div class="hidden sm:flex sm:items-center sm:gap-4 sm:py-3">
    {{-- Crear Ticket: visible para todos los usuarios autenticados --}}
    <a
        href="{{ $helpdeskCreateUrl }}"
        class="ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold text-white transition-all duration-200 active:scale-[0.98]"
        title="Crear ticket de soporte"
        aria-label="Crear Ticket"
    >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" class="size-[1.125rem] shrink-0" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        <span class="tracking-tight">Crear Ticket</span>
    </a>

    @if ($visible)
    <nav class="px-1 shrink-0 flex items-center" role="navigation" aria-label="Módulos">

        {{-- Una sola pastilla tipo iOS / Dynamic Island --}}
        <div
            x-data="{
                defaultLabel: 'Módulos',
                typedText: '',
                timeouts: [],
                typeWriter(text) {
                    this.timeouts.forEach(t => clearTimeout(t));
                    this.timeouts = [];
                    this.typedText = '';
                    for (let i = 0; i < text.length; i++) {
                        const idx = i;
                        const id = setTimeout(() => {
                            this.typedText = text.slice(0, idx + 1);
                        }, idx * 28);
                        this.timeouts.push(id);
                    }
                },
                reset() {
                    this.timeouts.forEach(t => clearTimeout(t));
                    this.timeouts = [];
                    this.typedText = '';
                }
            }"
            @mouseleave="reset()"
            class="
                inline-flex items-center gap-3 rounded-full px-4 py-2
                bg-white shadow-lg ring-1 ring-gray-200/80
                dark:bg-black dark:shadow-black/40 dark:ring-white/10
                min-w-0
            "
        >
            {{-- Zona de etiqueta: escribe el nombre del módulo al hacer hover + nombre del usuario --}}
            <div class="min-w-[7rem] shrink-0 flex flex-col items-start gap-0.5 text-xs font-medium text-gray-500 dark:text-gray-500">
                <div class="flex items-center gap-0.5">
                    <span x-text="typedText || defaultLabel"></span>
                    <span
                        x-show="typedText.length > 0"
                        x-transition
                        class="inline-block w-0.5 h-3.5 bg-current align-middle"
                        style="animation: blink 0.8s ease-in-out infinite;"
                        aria-hidden="true"
                    ></span>
                </div>
                @if($userName)
                    <span class="text-[0.8rem] leading-tight text-gray-700 dark:text-gray-300 truncate max-w-full font-medium" title="{{ $userName }}">{{ $userName }}</span>
                @endif
            </div>

            <style>
                @keyframes blink {
                    0%, 50% { opacity: 1; }
                    51%, 100% { opacity: 0; }
                }
                .menu-btn-admin { background-color: #0284c7 !important; }
                .menu-btn-admin:hover { background-color: #0369a1 !important; }
                .menu-btn-negocios { background-color: #0ea5e9 !important; }
                .menu-btn-negocios:hover { background-color: #0284c7 !important; }
                .menu-btn-marketing { background-color: #0891b2 !important; }
                .menu-btn-marketing:hover { background-color: #0e7490 !important; }
                .menu-btn-operations { background-color: #075985 !important; }
                .menu-btn-operations:hover { background-color: #0c4a6e !important; }
                /* Crear Ticket: success + sombras tipo iOS (capas suaves + anillo) */
                .ticket-btn-ios {
                    background: linear-gradient(180deg, #22c55e 0%, #16a34a 100%);
                    box-shadow:
                        0 1px 2px rgba(0, 0, 0, 0.06),
                        0 4px 12px rgba(22, 163, 74, 0.35),
                        0 12px 28px rgba(0, 0, 0, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.25);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                }
                .ticket-btn-ios:hover {
                    background: linear-gradient(180deg, #4ade80 0%, #22c55e 100%);
                    box-shadow:
                        0 2px 4px rgba(0, 0, 0, 0.08),
                        0 6px 16px rgba(22, 163, 74, 0.4),
                        0 16px 32px rgba(0, 0, 0, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.3);
                }
                .dark .ticket-btn-ios {
                    background: linear-gradient(180deg, #15803d 0%, #166534 100%);
                    box-shadow:
                        0 1px 2px rgba(0, 0, 0, 0.4),
                        0 4px 16px rgba(34, 197, 94, 0.25),
                        0 12px 32px rgba(0, 0, 0, 0.35),
                        inset 0 1px 0 rgba(255, 255, 255, 0.12);
                    border-color: rgba(255, 255, 255, 0.08);
                }
                .dark .ticket-btn-ios:hover {
                    background: linear-gradient(180deg, #16a34a 0%, #15803d 100%);
                }
            </style>

            {{-- Separador entre nombre y botones --}}
            {{-- <span class="w-px min-h-4 self-stretch shrink-0 bg-gray-300 dark:bg-gray-400" aria-hidden="true"></span> --}}

            {{-- Negocios (Business) - paleta (#0ea5e9, #0284c7) --}}
            <a
                href="{{ route('filament.business.pages.dashboard') }}"
                title="Negocios"
                aria-label="Ir a Negocios"
                @mouseenter="typeWriter('Negocios')"
                class="menu-btn-negocios group flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all duration-200 active:scale-95 hover:opacity-90"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"
                    class="size-5 text-white"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                </svg>
            </a>

            {{-- Administración - paleta TotalSaleForEstructureChart (#0284c7, #0369a1) --}}
            <a
                href="{{ route('filament.administration.pages.dashboard') }}"
                title="Administración"
                aria-label="Ir a Administración"
                @mouseenter="typeWriter('Administración')"
                class="menu-btn-admin group flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all duration-200 active:scale-95 hover:opacity-90"
            >

                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-white">

                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>


            </a>

            {{-- Marketing - paleta TotalSaleForEstructureChart (#0891b2, #0e7490) --}}
            <a
                href="{{ route('filament.marketing.pages.dashboard') }}"
                title="Marketing"
                aria-label="Ir a Marketing"
                @mouseenter="typeWriter('Marketing')"
                class="menu-btn-marketing group flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all duration-200 active:scale-95 hover:opacity-90"
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"
                    class="size-5 text-white"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.383a14.406 14.406 0 0 1-3 0M14.25 18v-.192c0-.983.658-1.823 1.508-2.316a7.5 7.5 0 1 0-7.517 0c.85.493 1.509 1.333 1.509 2.316V18" />
                </svg>
            </a>

            {{-- Operaciones - paleta TotalSaleForEstructureChart (#075985, #0c4a6e) --}}
            <a
                href="{{ route('filament.operations.pages.dashboard') }}"
                title="Operaciones"
                aria-label="Ir a Operaciones"
                @mouseenter="typeWriter('Operaciones')"
                class="menu-btn-operations group flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all duration-200 active:scale-95 hover:opacity-90"
            >

                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-white">

                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 7.125C2.25 6.504 2.754 6 3.375 6h6c.621 0 1.125.504 1.125 1.125v3.75c0 .621-.504 1.125-1.125 1.125h-6a1.125 1.125 0 0 1-1.125-1.125v-3.75ZM14.25 8.625c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v8.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-8.25ZM3.75 16.125c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125v2.25c0 .621-.504 1.125-1.125 1.125h-5.25a1.125 1.125 0 0 1-1.125-1.125v-2.25Z" />
                </svg>




            </a>
        </div>
    </nav>
    @endif
</div>
