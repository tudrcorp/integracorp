@php
    use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
    use App\Support\Companies\CompanyAssociatesTableContext;
@endphp

<div class="space-y-5">
    @forelse ($responsibles as $responsible)
        @php
            $associatesCount = $responsible->associates_count ?? $responsible->associates->count();
            $isExpanded = $this->isResponsibleExpanded((int) $responsible->id);
        @endphp
        <div
            class="overflow-hidden rounded-[1.25rem] border border-slate-200/90 bg-white/80 shadow-sm dark:border-white/10 dark:bg-white/5">
            <div
                class="flex flex-col gap-4 border-b border-slate-200/80 bg-gradient-to-r from-slate-50/90 to-white/70 px-4 py-4 dark:border-white/10 dark:from-slate-900/80 dark:to-slate-950/40 sm:flex-row sm:items-center sm:justify-between sm:px-5 {{ $associatesCount > 0 ? '' : 'border-b-0' }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-base font-semibold tracking-tight text-slate-900 dark:text-white">
                            {{ $responsible->full_name }}
                        </h3>
                        <span
                            class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-700 dark:bg-blue-500/15 dark:text-blue-300">
                            {{ $associatesCount }} asociado(s)
                        </span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                        <span>Cédula: <strong class="text-slate-700 dark:text-slate-200">{{ $responsible->identity_card }}</strong></span>
                        @if (filled($responsible->phone))
                            <span>Tel: <strong class="text-slate-700 dark:text-slate-200">{{ $responsible->phone }}</strong></span>
                        @endif
                        @if (filled($responsible->email))
                            <span>Correo Electrónico: <strong class="text-slate-700 dark:text-slate-200">{{ $responsible->email }}</strong></span>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if (filled($responsible->state?->definition))
                        <span
                            class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200">
                            {{ $responsible->state->definition }}
                        </span>
                    @endif
                    @if (filled($responsible->zone?->zone))
                        <span
                            class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200">
                            {{ $responsible->zone->zone }}
                        </span>
                    @endif
                    <span
                        class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-300">
                        {{ number_format((int) $responsible->contracted_days, 0, ',', '.') }} días
                    </span>
                    @if ($associatesCount > 0)
                        <button
                            type="button"
                            wire:click="toggleResponsible({{ $responsible->id }})"
                            wire:loading.attr="disabled"
                            wire:target="toggleResponsible({{ $responsible->id }})"
                            class="inline-flex items-center gap-1.5 rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-sky-400/40 dark:hover:bg-sky-500/10 dark:hover:text-sky-300">
                            <span>{{ $isExpanded ? 'Ocultar lista' : 'Ver lista' }}</span>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                class="size-4 shrink-0 transition-transform duration-200 {{ $isExpanded ? 'rotate-180' : '' }}"
                                aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z"
                                    clip-rule="evenodd" />
                            </svg>
                        </button>
                    @endif
                    <a href="{{ CompanyAssociatesTableContext::forResponsible($responsible) }}"
                        class="inline-flex items-center rounded-full border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200 dark:hover:border-sky-400/40 dark:hover:bg-sky-500/10 dark:hover:text-sky-300">
                        Ver en Tabla
                    </a>
                </div>
            </div>

            @if ($associatesCount > 0 && $isExpanded)
                <div class="px-4 py-4 sm:px-5" wire:key="responsible-associates-{{ $responsible->id }}">
                    <div class="overflow-x-auto rounded-xl border border-slate-200/80 dark:border-white/10">
                        <table class="min-w-full divide-y divide-slate-200/80 text-sm dark:divide-white/10">
                            <thead class="bg-slate-50/90 dark:bg-white/5">
                                <tr>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Asociado</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Cédula</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Edad</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Sexo</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Contacto</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Registrado</th>
                                    <th class="px-3 py-2.5 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">ILS</th>
                                    <th class="px-3 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200/70 bg-white/50 dark:divide-white/10 dark:bg-transparent">
                                @foreach ($responsible->associates->sortByDesc('registered_at') as $associate)
                                    <tr class="transition hover:bg-slate-50/80 dark:hover:bg-white/5" wire:key="associate-row-{{ $associate->id }}">
                                        <td class="px-3 py-3 font-medium text-slate-900 dark:text-white">
                                            {{ $associate->full_name }}
                                        </td>
                                        <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ $associate->identity_card }}</td>
                                        <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ $associate->age }} años</td>
                                        <td class="px-3 py-3">
                                            @php
                                                $sexBadgeClass = match (strtoupper((string) $associate->sex)) {
                                                    'FEMENINO' => 'associate-sex-badge associate-sex-badge--femenino bg-rose-100 text-rose-800 ring-1 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30',
                                                    'MASCULINO' => 'associate-sex-badge associate-sex-badge--masculino bg-sky-100 text-sky-800 ring-1 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30',
                                                    default => 'associate-sex-badge bg-slate-100 text-slate-700 ring-1 ring-slate-200 dark:bg-white/10 dark:text-slate-300 dark:ring-white/15',
                                                };
                                            @endphp
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $sexBadgeClass }}">
                                                {{ $associate->sex }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-slate-600 dark:text-slate-300">
                                            <div class="space-y-0.5">
                                                @if (filled($associate->phone))
                                                    <div>{{ $associate->phone }}</div>
                                                @endif
                                                @if (filled($associate->email))
                                                    <div class="text-xs">{{ $associate->email }}</div>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 whitespace-nowrap text-slate-600 dark:text-slate-300">
                                            {{ $associate->registered_at?->format('d/m/Y H:i:s') ?? '—' }}
                                        </td>
                                        <td class="px-3 py-3">
                                            @if ($associate->hasVoucherIls())
                                                <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                                                    Cargado
                                                </span>
                                            @else
                                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                                    Pendiente
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <div class="flex items-center justify-end gap-2">
                                                <x-filament::button
                                                    size="xs"
                                                    color="info"
                                                    wire:click="mountAction('voucherIls', { associateId: {{ $associate->id }} })"
                                                    wire:loading.attr="disabled"
                                                    wire:target="mountAction('voucherIls', { associateId: {{ $associate->id }} })">
                                                    Voucher ILS
                                                </x-filament::button>
                                                <a href="{{ CompanyAssociateResource::getUrl('view', ['record' => $associate]) }}"
                                                    class="inline-flex items-center rounded-full bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200">
                                                    Ver
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @elseif ($associatesCount === 0)
                <div class="px-4 py-4 sm:px-5">
                    <p class="rounded-xl border border-dashed border-slate-200/90 px-4 py-4 text-center text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
                        Este responsable aún no tiene asociados registrados.
                    </p>
                </div>
            @endif
        </div>
    @empty
        <p class="rounded-xl border border-dashed border-slate-200/90 px-4 py-8 text-center text-sm text-slate-500 dark:border-white/10 dark:text-slate-400">
            No hay responsables registrados para este negocio.
        </p>
    @endforelse

    <x-filament-actions::modals />

    @once
        @push('styles')
            <style>
                .dark .associate-sex-badge--femenino {
                    background-color: rgb(244 63 94 / 0.16) !important;
                    color: rgb(253 164 175) !important;
                    box-shadow: inset 0 0 0 1px rgb(244 63 94 / 0.28);
                }

                .dark .associate-sex-badge--masculino {
                    background-color: rgb(14 165 233 / 0.16) !important;
                    color: rgb(125 211 252) !important;
                    box-shadow: inset 0 0 0 1px rgb(14 165 233 / 0.28);
                }
            </style>
        @endpush
    @endonce
</div>
