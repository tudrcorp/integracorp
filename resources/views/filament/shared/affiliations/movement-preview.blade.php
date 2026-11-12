<div class="space-y-2 text-sm">
    @if ($preview['blockers'] !== [])
        <div class="rounded-lg bg-rose-50 px-3 py-2 text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            <p class="font-semibold">No se puede registrar así:</p>
            <ul class="list-disc space-y-0.5 ps-5">
                @foreach ($preview['blockers'] as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-inset ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            <p class="font-semibold text-gray-950 dark:text-white">
                {{ $kind === \App\Models\AffiliateMovement::KIND_ADD ? 'Al guardar la inclusión:' : 'Al dar de baja:' }}
            </p>
            <ul class="mt-1 list-disc space-y-0.5 ps-5 text-gray-700 dark:text-gray-200">
                <li>{{ $kind === \App\Models\AffiliateMovement::KIND_ADD ? 'La persona suma en la población, la tarifa anual y la cuota de la afiliación.' : 'La persona deja de sumar en la población, la tarifa anual y la cuota de la afiliación.' }}</li>
                @forelse ($preview['summary'] as $line)
                    <li>{{ $line }}</li>
                @empty
                    <li>No hay avisos de cobro que ajustar.</li>
                @endforelse
                <li>{{ $kind === \App\Models\AffiliateMovement::KIND_ADD ? 'Si ya tenía paciente de telemedicina en esta afiliación, vuelve a quedar activo.' : 'Su paciente de telemedicina en esta afiliación queda inactivo.' }}</li>
            </ul>
        </div>
    @endif

    @foreach ($preview['warnings'] as $warning)
        <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-900 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-400/30">{{ $warning }}</p>
    @endforeach
</div>
