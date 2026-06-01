@php
    $groups = $groups ?? collect();
@endphp

<div class="space-y-4">
    <div
        class="overflow-hidden rounded-[1.25rem] border border-gray-200/80 bg-gray-50/90 shadow-inner ring-1 ring-black/[0.03] dark:border-white/10 dark:bg-white/[0.06] dark:ring-white/[0.05]">
        <div class="flex items-center justify-between gap-3 border-b border-gray-200/70 px-4 py-3.5 dark:border-white/10">
            <div class="flex min-w-0 items-center gap-3">
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-black/5 dark:bg-gray-800 dark:ring-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-gray-500 dark:text-gray-400">
                        Grupos registrados
                    </p>
                    <p class="truncate text-sm font-semibold tracking-tight text-gray-900 dark:text-white">
                        {{ $groups->count() === 1 ? '1 grupo disponible' : $groups->count().' grupos disponibles' }}
                    </p>
                </div>
            </div>
        </div>
        <div class="px-4 py-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Los grupos definen equipos reutilizables y la cuota máxima de tickets que pueden recibir. Cree grupos arriba o edite integrantes con «Editar grupo».
            </p>
        </div>
    </div>

    @forelse ($groups as $group)
        @php
            $members = is_array($group->team_members) ? $group->team_members : [];
            $memberNames = collect($members)
                ->map(static fn (mixed $member): string => is_array($member) ? trim((string) ($member['name'] ?? '')) : '')
                ->filter()
                ->values();
            $isActive = strtoupper((string) $group->status) === 'ACTIVO';
            $ticketQuota = (int) $group->total_tickets_assigned;
            $ticketsUsed = $group->ticketsCreatedCount();
            $quotaReached = $ticketQuota > 0 && $ticketsUsed >= $ticketQuota;
        @endphp

        <div
            wire:key="helpdesk-work-group-{{ $group->getKey() }}"
            class="overflow-hidden rounded-[1.35rem] border border-gray-200/80 bg-gradient-to-b from-white/95 to-gray-50/90 p-4 shadow-[0_8px_30px_rgb(0,0,0,0.08)] ring-1 ring-black/[0.04] dark:border-white/10 dark:from-gray-950/90 dark:to-gray-900/80 dark:ring-white/[0.06]"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $group->name }}</p>
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200' => $isActive,
                            'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200' => ! $isActive,
                        ])>
                            {{ $isActive ? 'Activo' : 'Inactivo' }}
                        </span>
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200' => ! $quotaReached,
                            'bg-amber-100 text-amber-900 dark:bg-amber-500/25 dark:text-amber-100' => $quotaReached,
                        ]) title="Tickets registrados / cuota máxima">
                            {{ $ticketsUsed }} / {{ $ticketQuota === 1 ? '1 ticket' : $ticketQuota.' tickets' }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $memberNames->count() }} integrante(s)
                        @if ($quotaReached)
                            · <span class="font-medium text-amber-700 dark:text-amber-300">Cuota agotada: los integrantes no pueden crear tickets hasta que Tecnología amplíe la cuota.</span>
                        @endif
                        @if (filled($group->created_by))
                            · Creado por {{ $group->created_by }}
                        @endif
                        @if (filled($group->created_at))
                            · {{ $group->created_at->format('d/m/Y H:i') }}
                        @endif
                    </p>
                    @if ($memberNames->isNotEmpty())
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $memberNames->implode(', ') }}
                        </p>
                    @endif
                </div>
                <div class="flex shrink-0 flex-wrap justify-end gap-2">
                    @if (\App\Support\HelpdeskUserAccess::hasSystemsDepartment())
                        <button
                            type="button"
                            wire:click="mountEditHelpdeskWorkGroup({{ $group->getKey() }})"
                            title="Editar nombre, estado e integrantes del grupo"
                            class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold tracking-tight text-white transition-all duration-200 hover:bg-indigo-700 active:scale-[0.98]"
                        >
                            Editar grupo
                        </button>
                        <button
                            type="button"
                            wire:click="mountUpdateHelpdeskWorkGroupQuota({{ $group->getKey() }})"
                            title="Solo Tecnología (SISTEMAS) puede ampliar la cuota del grupo"
                            class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-sky-600 px-4 py-2 text-sm font-semibold tracking-tight text-white transition-all duration-200 hover:bg-sky-700 active:scale-[0.98]"
                        >
                            Actualizar cuota
                        </button>
                    @endif
                    <button
                        type="button"
                        wire:click="mountDeleteHelpdeskWorkGroup({{ $group->getKey() }})"
                        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full bg-red-600 px-4 py-2 text-sm font-semibold tracking-tight text-white transition-all duration-200 hover:bg-red-700 active:scale-[0.98]"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-[1.35rem] border border-dashed border-gray-300/90 bg-white/60 px-4 py-12 text-center text-sm text-gray-500 dark:border-white/15 dark:bg-white/[0.04] dark:text-gray-400">
            <p class="font-medium text-gray-600 dark:text-gray-300">Aún no hay grupos de trabajo</p>
            <p class="mt-1 text-xs">Cree el primer grupo con el formulario superior.</p>
        </div>
    @endforelse
</div>
