@php
    use App\Models\CorporatePopulationUpdateItem as Item;

    $rows = [
        Item::ACTION_ADD => 'Agregar',
        Item::ACTION_REACTIVATE => 'Reactivar',
        Item::ACTION_DEACTIVATE => 'Dar de baja',
        Item::ACTION_DUPLICATE_BASE => 'Dar de baja duplicados',
        Item::ACTION_FIX_DOCUMENT => 'Corregir cédula',
    ];
    $population = (int) $owner->poblation;
@endphp

<div class="space-y-3 text-sm">
    <table class="w-full text-left">
        <tbody class="divide-y divide-gray-950/5 dark:divide-white/5">
            @foreach ($rows as $action => $label)
                <tr>
                    <td class="py-1.5 text-gray-600 dark:text-gray-300">{{ $label }}</td>
                    <td class="py-1.5 text-right font-semibold text-gray-950 dark:text-white">{{ $selected[$action] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="rounded-lg bg-gray-50 px-3 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
        Población de la afiliación: <strong>{{ $population }}</strong> → <strong>{{ max(0, $population + $preview['persons']) }}</strong>
        ({{ $preview['persons'] >= 0 ? '+' : '' }}{{ $preview['persons'] }}) ·
        Tarifa anual: {{ $preview['fee'] >= 0 ? '+' : '' }}US$ {{ number_format($preview['fee'], 2, ',', '.') }}
    </div>

    @if ($preview['mass'])
        <div class="rounded-lg bg-rose-50 px-3 py-2 font-medium text-rose-800 ring-1 ring-inset ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-400/30">
            Atención: se darán de baja {{ $preview['deactivations'] }} afiliados. Si el archivo no es la población completa, NO apruebe.
        </div>
    @endif
</div>
