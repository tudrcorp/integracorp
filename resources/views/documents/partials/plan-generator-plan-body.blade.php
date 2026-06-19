@php
    /** @var \App\Models\PlanGenerator $planGenerator */
    /** @var array<int, array<string, mixed>> $columns */
    /** @var array<string, array<string, mixed>> $rows */
    /** @var array<string, array<string, mixed>> $rateRows */
    /** @var \Illuminate\Support\Carbon $generatedAt */
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
    use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
    use App\Support\PlanGenerators\PlanGeneratorMatrixColumnLayout;
    use App\Support\PlanGenerators\PlanGeneratorBrandColor;
    use App\Services\PlanGeneratorPdfService;

    $brandColor = PlanGeneratorBrandColor::resolve($planGenerator->brand_color ?? null);
    $columnCount = count($columns);
    $leadWidthMm = PlanGeneratorMatrixColumnLayout::leadWidthMm();
    $rateAgeWidthMm = PlanGeneratorMatrixColumnLayout::rateAgeWidthMm();
    $ratePopWidthMm = PlanGeneratorMatrixColumnLayout::ratePopWidthMm();
    $planWidthMm = PlanGeneratorMatrixColumnLayout::planColumnWidthMm(max(1, $columnCount));
    $groupTotals = PlanGeneratorGroupTotalCalculator::totalsByColumn((array) $columns, (array) $rateRows);
    $groupRows = [
        ['key' => PlanGeneratorGroupTotalCalculator::ROW_ANNUAL, 'label' => 'Tarifa anual', 'bold' => true],
        ['key' => PlanGeneratorGroupTotalCalculator::ROW_SEMESTRAL, 'label' => 'Tarifa Semestral', 'bold' => false],
        ['key' => PlanGeneratorGroupTotalCalculator::ROW_TRIMESTRAL, 'label' => 'Tarifa Trimestral', 'bold' => false],
    ];
@endphp

<div class="header">
    <table>
        <tr>
            <td>
                @if ($logoDataUri !== '')
                    <img src="{{ $logoDataUri }}" alt="Tu Doctor en Casa" class="logo">
                @endif
            </td>
            <td class="title">
                <h1>{{ $planGenerator->name ?? 'Plan generado' }}</h1>
                <p>Código: <strong>{{ PlanGeneratorPdfService::codeLabel($planGenerator) }}</strong></p>
                <p>Generado: <strong>{{ PlanGeneratorPdfService::generatedAtLabel($generatedAt) }}</strong></p>
            </td>
        </tr>
    </table>
</div>

<p class="proposal-title">Propuesta Comercial</p>
<div class="proposal-block">
<table class="proposal-table">
    <tr>
        <td class="proposal-label">Nro. Control:</td>
        <td><span class="proposal-value">{{ $planGenerator->control_number ?? '—' }}</span></td>
    </tr>
    <tr>
        <td class="proposal-label">Datos del cliente:</td>
        <td><span class="proposal-value">{{ $planGenerator->client_data ?? '—' }}</span></td>
    </tr>
    <tr>
        <td class="proposal-label">Fecha de emisión:</td>
        <td><span class="proposal-value">{{ optional($planGenerator->issued_at)->format('d/m/Y') ?? '—' }}</span></td>
    </tr>
    <tr>
        <td class="proposal-label">Agente:</td>
        <td><span class="proposal-value">{{ $planGenerator->agent_name ?? '—' }}</span></td>
    </tr>
    <tr>
        <td class="proposal-label">Población:</td>
        <td><span class="proposal-value">{{ $planGenerator->population_summary ?? '—' }}</span></td>
    </tr>
</table>
</div>

@if ($columnCount === 0)
    <p>Sin columnas configuradas para este plan.</p>
@else
    <div class="matrix-section">
    <p class="section-title">Matriz de beneficios y coberturas</p>
    <table class="matrix-table">
        @include('filament.business.plan-generators.partials.matrix-column-colgroup', [
            'columns' => $columns,
            'type' => 'benefits',
            'usePdfWidths' => true,
        ])
        <thead>
            <tr>
                <th colspan="2" class="benefit-col" style="width: {{ $leadWidthMm }}mm;">Beneficios del Plan</th>
                @foreach ($columns as $column)
                    <th style="width: {{ $planWidthMm }}mm;">{{ $column['header_label'] ?? '—' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td colspan="2" class="benefit-col">{{ $row['benefit_label'] ?? '—' }}</td>
                    @foreach ($columns as $column)
                        @php
                            $columnKey = (string) ($column['column_key'] ?? '');
                            $cell = (array) data_get($row, "cells.{$columnKey}", []);
                            $isSelected = (bool) ($cell['is_selected'] ?? false);
                            $coverage = $cell['coverage_amount'] ?? null;
                            $coverageLabel = is_numeric($coverage)
                                ? PlanGeneratorPreviewBuilder::formatCoverageAmount((float) $coverage)
                                : '';
                        @endphp
                        <td style="text-align: center;">
                            @if ($isSelected)
                                <span class="check">✓</span>
                                @if ($coverageLabel !== '')
                                    <br><span class="amount">US$ {{ $coverageLabel }}</span>
                                @endif
                            @else
                                <span class="dash">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount + 2 }}" style="text-align: center; color: #6b7280;">
                        Sin beneficios registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="matrix-section">
    <p class="section-title">Tarifa individual anual</p>
    <table class="matrix-table">
        @include('filament.business.plan-generators.partials.matrix-column-colgroup', [
            'columns' => $columns,
            'type' => 'rates',
            'usePdfWidths' => true,
        ])
        <thead>
            <tr>
                <th style="width: {{ $rateAgeWidthMm }}mm; text-align: left;">Tarifa individual Anual</th>
                <th style="width: {{ $ratePopWidthMm }}mm;">Población</th>
                @foreach ($columns as $column)
                    <th style="width: {{ $planWidthMm }}mm;">{{ $column['header_label'] ?? '—' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rateRows as $rateRow)
                <tr>
                    <td style="text-align: left;">{{ $rateRow['age_range_label'] ?? '—' }}</td>
                    <td style="text-align: center;">
                        {{ filled($rateRow['population'] ?? null) ? number_format((int) $rateRow['population'], 0, ',', '.') : '—' }}
                    </td>
                    @foreach ($columns as $column)
                        @php
                            $columnKey = (string) ($column['column_key'] ?? '');
                            $rate = data_get($rateRow, "cells.{$columnKey}.rate_amount");
                            $rateLabel = is_numeric($rate)
                                ? PlanGeneratorPreviewBuilder::formatRateAmount((float) $rate)
                                : '';
                        @endphp
                        <td style="text-align: center;">
                            <span class="rate-value">{{ $rateLabel !== '' ? $rateLabel : '—' }}</span>
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount + 2 }}" style="text-align: center; color: #6b7280;">
                        Sin tarifas individuales anuales registradas.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>

    <div class="matrix-section">
    <p class="section-title">Total grupal</p>
    <table class="matrix-table">
        @include('filament.business.plan-generators.partials.matrix-column-colgroup', [
            'columns' => $columns,
            'type' => 'group-total',
            'usePdfWidths' => true,
        ])
        <thead>
            <tr>
                <th style="width: {{ $rateAgeWidthMm }}mm; text-align: left;">Total Grupal</th>
                <th style="width: {{ $ratePopWidthMm }}mm;">&nbsp;</th>
                @foreach ($columns as $column)
                    <th style="width: {{ $planWidthMm }}mm;">{{ $column['header_label'] ?? '—' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($groupRows as $groupRow)
                <tr>
                    <td style="text-align: left;">{{ $groupRow['label'] }}</td>
                    <td>&nbsp;</td>
                    @foreach ($columns as $column)
                        @php
                            $columnKey = (string) ($column['column_key'] ?? '');
                            $amount = (float) ($groupTotals[$groupRow['key']][$columnKey] ?? 0);
                            $label = PlanGeneratorGroupTotalCalculator::formatGroupTotal($amount > 0 ? $amount : null);
                        @endphp
                        <td style="text-align: center;" @if($groupRow['bold']) class="group-total-bold" @endif>
                            {{ $label }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
@endif

<div class="footer">
    Integracorp · Tu Dr en Casa · Plan generado
</div>
