{{-- Sección del bloque de cálculos del plan generado (incluida desde plan-generator-plan-body). --}}
@php
    use App\Support\PlanGenerators\PlanGeneratorGroupTotalCalculator;
@endphp
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

