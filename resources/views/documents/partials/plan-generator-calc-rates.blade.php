{{-- Sección del bloque de cálculos del plan generado (incluida desde plan-generator-plan-body). --}}
@php
    use App\Support\PlanGenerators\PlanGeneratorPreviewBuilder;
@endphp
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

