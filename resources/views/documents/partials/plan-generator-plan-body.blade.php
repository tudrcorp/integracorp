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
    use App\Enums\PlanGeneratorPopulationUnit;
    use App\Services\PlanGeneratorPdfService;
    use App\Support\PlanGenerators\PlanGeneratorPdfPagination;
    use App\Support\PlanGenerators\PlanGeneratorConditions;

    $conditions = PlanGeneratorConditions::normalize($planGenerator->conditions ?? '');

    $brandColor = PlanGeneratorBrandColor::resolve($planGenerator->brand_color ?? null);
    $populationUnitLabel = PlanGeneratorPopulationUnit::resolve($planGenerator->population_unit ?? null)->label();
    $columnCount = count($columns);
    $leadPercent = PlanGeneratorMatrixColumnLayout::LEAD_PERCENT;
    $rateAgePercent = PlanGeneratorMatrixColumnLayout::RATE_AGE_PERCENT;
    $ratePopPercent = PlanGeneratorMatrixColumnLayout::RATE_POP_PERCENT;
    $planPercent = PlanGeneratorMatrixColumnLayout::planColumnPercent(max(1, $columnCount));
    $groupTotals = PlanGeneratorGroupTotalCalculator::totalsByColumn((array) $columns, (array) $rateRows);
    $includeMonthlyTotal = (bool) ($planGenerator->include_monthly_total ?? false);
    $groupRows = PlanGeneratorGroupTotalCalculator::groupTotalRows($includeMonthlyTotal);
    $calculationsOnNextPage = $columnCount > 0 && PlanGeneratorPdfPagination::calculationsStartOnNextPage(
        (array) $columns,
        (array) $rows,
        (array) $rateRows,
        $includeMonthlyTotal,
        $populationUnitLabel,
        $conditions,
    );
@endphp

<table class="pdf-plan-sheet" cellpadding="0" cellspacing="0">
<tr>
<td class="pdf-plan-margin-cell pdf-plan-margin-cell-first {{ $columnCount === 0 ? 'pdf-plan-margin-cell-last' : '' }}">
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
        <td class="proposal-label">{{ $populationUnitLabel }}:</td>
        <td><span class="proposal-value">{{ $planGenerator->population_summary ?? '—' }}</span></td>
    </tr>
</table>
</div>

@if ($columnCount === 0)
    <p>Sin columnas configuradas para este plan.</p>
    <div class="footer">
        Integracorp · Tu Dr en Casa · Plan generado
    </div>
@endif
</td>
</tr>
</table>

@if ($columnCount > 0)
    <p class="pdf-benefits-title">Matriz de beneficios y coberturas</p>
    <table class="matrix-table pdf-benefits-table">
        @include('filament.business.plan-generators.partials.matrix-column-colgroup', [
            'columns' => $columns,
            'type' => 'benefits',
            'usePdfWidths' => true,
        ])
        <thead>
            <tr>
                <th class="benefit-col" style="width: {{ $leadPercent }}%;">Beneficios del Plan</th>
                @foreach ($columns as $column)
                    <th style="width: {{ $planPercent }}%;">{{ $column['header_label'] ?? '—' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="benefit-col" style="width: {{ $leadPercent }}%;">{{ $row['benefit_label'] ?? '—' }}</td>
                    @foreach ($columns as $column)
                        @php
                            $columnKey = (string) ($column['column_key'] ?? '');
                            $cell = (array) data_get($row, "cells.{$columnKey}", []);
                            $isSelected = (bool) ($cell['is_selected'] ?? false);
                            $coverage = $cell['coverage_amount'] ?? null;
                            $display = PlanGeneratorPreviewBuilder::benefitCellDisplay($isSelected, $coverage);
                            $coverageLabel = $display === 'amount'
                                ? PlanGeneratorPreviewBuilder::formatCoverageAmount((float) $coverage)
                                : '';
                        @endphp
                        <td style="width: {{ $planPercent }}%; text-align: center;">
                            {{-- Con tope se muestra solo el monto: el check encima sería redundante. --}}
                            @if ($display === 'amount')
                                <span class="amount">US$ {{ $coverageLabel }}</span>
                            @elseif ($display === 'check')
                                <span class="check">✓</span>
                            @else
                                <span class="dash">—</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount + 1 }}" style="text-align: center; color: #6b7280;">
                        Sin beneficios registrados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="pdf-plan-sheet pdf-plan-calc-keep {{ $calculationsOnNextPage ? 'pdf-plan-calc-next-page' : '' }}" cellpadding="0" cellspacing="0">
    <tr>
    <td class="pdf-plan-margin-cell pdf-plan-calc-cell">
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
                <th style="width: {{ $rateAgePercent }}%; text-align: left;">Tarifa individual Anual</th>
                <th style="width: {{ $ratePopPercent }}%;">{{ $populationUnitLabel }}</th>
                @foreach ($columns as $column)
                    <th style="width: {{ $planPercent }}%;">{{ $column['header_label'] ?? '—' }}</th>
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
                <th style="width: {{ $leadPercent }}%; text-align: left;">Total Grupal</th>
                @foreach ($columns as $column)
                    <th style="width: {{ $planPercent }}%;">{{ $column['header_label'] ?? '—' }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($groupRows as $groupRow)
                <tr>
                    <td style="text-align: left;">{{ $groupRow['label'] }}</td>
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

    @if ($conditions !== '')
        <div class="matrix-section">
            <p class="section-title">Condiciones</p>
            <div class="conditions-block">{{ $conditions }}</div>
        </div>
    @endif

    <div class="footer">
        Integracorp · Tu Dr en Casa · Plan generado
    </div>
    </td>
    </tr>
    </table>
@endif
