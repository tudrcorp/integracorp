{{-- Sección del bloque de cálculos del plan generado (incluida desde plan-generator-plan-body): un bloque por upgrade. --}}
@php
    use App\Support\PlanGenerators\PlanGeneratorUpgrades;
@endphp
@foreach ((array) ($upgradeQuote['upgrades'] ?? []) as $upgrade)
    @php
        $pricedRows = PlanGeneratorUpgrades::chargedRows($upgrade);
        $services = array_values((array) ($upgrade['services'] ?? []));
    @endphp
    <div class="matrix-section">
    <p class="section-title">{{ $upgrade['upgrade_name'] }}:</p>
    <table class="matrix-table" style="table-layout: fixed; width: 100%;">
        <colgroup>
            <col style="width: 64%;">
            <col style="width: 36%;">
        </colgroup>
        <thead>
            <tr>
                <th style="text-align: left;">{{ $upgrade['upgrade_name'] }}</th>
                <th>{{ PlanGeneratorUpgrades::coverageLabel($upgrade) }}</th>
            </tr>
        </thead>
        <tbody>
            @if ($upgrade['uniform_price'] !== null)
                <tr>
                    <td style="text-align: left;">Tarifa anual individual por persona</td>
                    <td style="text-align: center;" class="group-total-bold">{{ PlanGeneratorUpgrades::money($upgrade['uniform_price']) }}</td>
                </tr>
            @else
                @foreach ($pricedRows as $row)
                    <tr>
                        <td style="text-align: left;">Tarifa anual individual · {{ $row['status'] === PlanGeneratorUpgrades::STATUS_MANUAL && filled($row['covered'] ?? null) ? $row['covered'] : $row['label'] }}</td>
                        <td style="text-align: center;" class="group-total-bold">{{ PlanGeneratorUpgrades::money($row['price']) }}</td>
                    </tr>
                @endforeach
            @endif
            <tr>
                <td style="text-align: left;">Tarifa anual grupal ({{ number_format((int) $upgrade['persons'], 0, ',', '.') }} {{ (int) $upgrade['persons'] === 1 ? 'persona' : 'personas' }})</td>
                <td style="text-align: center;" class="group-total-bold">{{ PlanGeneratorUpgrades::money($upgrade['annual']) }}</td>
            </tr>
        </tbody>
    </table>
    @if ($services !== [])
        <table class="matrix-table" style="table-layout: fixed; width: 100%; margin-top: 4px;">
            <colgroup>
                <col style="width: 100%;">
            </colgroup>
            <thead>
                <tr>
                    <th style="text-align: left;">Servicios incluidos:</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($services as $service)
                    <tr>
                        <td style="text-align: left;">{{ $loop->iteration }}. {{ $service['name'] ?? '' }}@if (filled($service['description'] ?? null)) — {{ $service['description'] }}@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    </div>
@endforeach
