@php
    use App\Enums\FiscalBook;
    use App\Support\Administration\FiscalBooks\FiscalAmount;

    /** @var FiscalBook $book */
    $credit = $book === FiscalBook::Sales ? 'Débito fiscal' : 'Crédito fiscal';
    $cards = [
        ['label' => 'Documentos', 'value' => number_format($totals['documents'], 0, ',', '.'), 'hint' => $totals['annulled'] > 0 ? $totals['annulled'].' anulados' : 'ninguno anulado'],
        ['label' => 'Total del mes', 'value' => 'Bs. '.FiscalAmount::format($totals['total']), 'hint' => 'con IVA y exentas'],
        ['label' => 'Exento o no sujeto', 'value' => 'Bs. '.FiscalAmount::format($totals['exempt']), 'hint' => 'sin derecho a crédito'],
        ['label' => 'Base imponible', 'value' => 'Bs. '.FiscalAmount::format($totals['taxable_base']), 'hint' => 'gravada'],
        ['label' => $credit, 'value' => 'Bs. '.FiscalAmount::format($totals['tax']), 'hint' => 'IVA del mes'],
        ['label' => 'IVA retenido', 'value' => 'Bs. '.FiscalAmount::format($totals['withholding']), 'hint' => 'comprobantes de retención'],
    ];
@endphp

<div class="fbk">
    <div class="fbk-bar">
        <label class="fbk-month">
            <span class="fbk-label">Mes del libro</span>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="month" aria-label="Mes del libro">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <div class="fbk-status {{ $closed ? 'fbk-status--closed' : 'fbk-status--open' }}" role="status">
            <x-filament::icon :icon="$closed ? 'heroicon-o-lock-closed' : 'heroicon-o-lock-open'" class="fbk-status-icon" />
            <div>
                <div class="fbk-status-title">{{ $closed ? 'Mes cerrado' : 'Mes abierto' }}</div>
                <div class="fbk-status-text">
                    @if ($closed)
                        Cerrado por {{ $record?->closed_by_name ?? '—' }} el {{ $record?->closed_at?->format('d/m/Y h:i a') }}. No admite cambios.
                    @elseif ($record?->reopened_at)
                        Reabierto por {{ $record->reopened_by_name }} el {{ $record->reopened_at->format('d/m/Y') }}: «{{ $record->reopen_reason }}».
                    @else
                        Admite cambios. Ciérrelo cuando lo declare.
                    @endif
                </div>
            </div>
        </div>

        <div class="fbk-loading" wire:loading.flex wire:target="month">
            <x-filament::loading-indicator class="fbk-spinner" />
            Cargando {{ mb_strtolower($periodLabel) }}…
        </div>
    </div>

    <div class="fbk-cards">
        @foreach ($cards as $card)
            <div class="fbk-card">
                <div class="fbk-label">{{ $card['label'] }}</div>
                <div class="fbk-value">{{ $card['value'] }}</div>
                <div class="fbk-hint">{{ $card['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <details class="fbk-summary" @if ($totals['documents'] > 0) open @endif>
        <summary>Resumen de {{ $book === FiscalBook::Sales ? 'débitos' : 'créditos' }} fiscales · {{ mb_strtolower($periodLabel) }}</summary>
        <div class="fbk-summary-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Concepto</th>
                        <th scope="col">Ítem</th>
                        <th scope="col" class="fbk-num">Base imponible</th>
                        <th scope="col" class="fbk-num">{{ $credit }}</th>
                        <th scope="col" class="fbk-num">IVA retenido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary as $line)
                        <tr class="{{ $line['strong'] ? 'fbk-strong' : '' }}">
                            <td>{{ $line['label'] }}</td>
                            <td>{{ $line['item'] }}</td>
                            <td class="fbk-num">{{ FiscalAmount::format($line['base']) }}</td>
                            <td class="fbk-num">{{ $line['tax'] === null ? '' : FiscalAmount::format($line['tax']) }}</td>
                            <td class="fbk-num">{{ $line['withholding'] === null ? '' : FiscalAmount::format($line['withholding']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    <style>
        .fbk { display: flex; flex-direction: column; gap: 16px; }
        .fbk-bar { display: flex; flex-wrap: wrap; align-items: stretch; gap: 16px; }
        .fbk-month { display: flex; flex-direction: column; gap: 6px; min-width: 220px; }
        .fbk-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .fbk-status { display: flex; align-items: center; gap: 12px; flex: 1 1 320px; padding: 10px 14px; border-radius: 12px; border: 1px solid; }
        .fbk-status--open { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .fbk-status--closed { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .fbk-status-icon { width: 24px; height: 24px; flex: none; }
        .fbk-status-title { font-weight: 700; font-size: 14px; }
        .fbk-status-text { font-size: 13px; opacity: .9; }
        .fbk-loading { align-items: center; gap: 8px; font-size: 13px; color: #64748b; }
        .fbk-spinner { width: 18px; height: 18px; }
        .fbk-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
        .fbk-card { padding: 14px 16px; border-radius: 12px; background: #ffffff; border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .fbk-value { font-size: 18px; font-weight: 700; color: #0f172a; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .fbk-hint { font-size: 12px; color: #64748b; }
        .fbk-summary { border: 1px solid #e2e8f0; border-radius: 12px; background: #ffffff; }
        .fbk-summary summary { cursor: pointer; padding: 12px 16px; font-weight: 700; color: #052f60; }
        .fbk-summary-scroll { overflow-x: auto; padding: 0 16px 14px; }
        .fbk-summary table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .fbk-summary th { text-align: left; font-size: 12px; color: #64748b; font-weight: 600; padding: 6px 8px; border-bottom: 1px solid #e2e8f0; }
        .fbk-summary td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
        .fbk-summary .fbk-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .fbk-summary .fbk-strong td { font-weight: 700; background: #f8fafc; }
        .dark .fbk-card, .dark .fbk-summary { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.1); }
        .dark .fbk-value, .dark .fbk-summary td { color: #f1f5f9; }
        .dark .fbk-summary summary { color: #93c5fd; }
        .dark .fbk-summary th { border-color: rgba(255,255,255,.1); }
        .dark .fbk-summary td { border-color: rgba(255,255,255,.06); }
        .dark .fbk-summary .fbk-strong td { background: rgba(255,255,255,.05); }
        .dark .fbk-status--open { background: rgba(22,163,74,.12); border-color: rgba(22,163,74,.35); color: #86efac; }
        .dark .fbk-status--closed { background: rgba(245,158,11,.12); border-color: rgba(245,158,11,.35); color: #fcd34d; }
    </style>
</div>
