{{-- Vista previa del cambio de plan (ChangeAffiliationPlanBulkAction). Cada entrada es el resultado real del servicio, revertido. --}}
@php
    $money = static fn ($value): string => 'US$ '.number_format((float) $value, 2, ',', '.');
    $blockedCount = collect($previews)->filter(fn ($preview) => ($preview['blocked'] ?? null) !== null)->count();
@endphp
<div class="pcp">
    <style>
        .pcp { display: flex; flex-direction: column; gap: 12px; font-size: 13px; color: #1f2937; }
        .pcp-card { border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; background: #fff; }
        .pcp-card.is-blocked { border-color: #fecaca; background: #fef2f2; }
        .pcp-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; padding: 10px 14px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
        .pcp-card.is-blocked .pcp-head { border-bottom: 0; background: transparent; }
        .pcp-title { font-weight: 700; font-size: 14px; }
        .pcp-chip { display: inline-flex; align-items: center; border-radius: 999px; padding: 2px 9px; font-size: 11px; font-weight: 700; }
        .pcp-chip.is-ok { background: #dcfce7; color: #166534; }
        .pcp-chip.is-bad { background: #fee2e2; color: #991b1b; }
        .pcp-body { padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; }
        .pcp-reason { padding: 0 14px 12px; color: #991b1b; line-height: 1.5; }
        .pcp-compare { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
        .pcp-compare div { border: 1px solid #f3f4f6; border-radius: 10px; padding: 8px 10px; }
        .pcp-compare span { display: block; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
        .pcp-compare strong { display: block; margin-top: 2px; }
        .pcp-compare em { font-style: normal; color: #6b7280; text-decoration: line-through; margin-right: 4px; }
        .pcp-table { width: 100%; border-collapse: collapse; }
        .pcp-table th { text-align: left; font-size: 11px; color: #6b7280; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; padding: 4px 6px; border-bottom: 1px solid #e5e7eb; }
        .pcp-table td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; }
        .pcp-note { margin: 0; color: #4b5563; line-height: 1.5; }
        .pcp-warn { margin: 0; color: #92400e; line-height: 1.5; }
        .dark .pcp { color: rgb(229 231 235); }
        .dark .pcp-card { border-color: rgb(255 255 255 / 10%); background: rgb(17 24 39); }
        .dark .pcp-card.is-blocked { border-color: rgb(248 113 113 / 40%); background: rgb(127 29 29 / 20%); }
        .dark .pcp-head { border-color: rgb(255 255 255 / 10%); background: rgb(255 255 255 / 4%); }
        .dark .pcp-reason { color: rgb(252 165 165); }
        .dark .pcp-compare div { border-color: rgb(255 255 255 / 8%); }
        .dark .pcp-table th, .dark .pcp-table td { border-color: rgb(255 255 255 / 8%); }
        .dark .pcp-note { color: rgb(209 213 219); }
        .dark .pcp-warn { color: rgb(253 230 138); }
        @media (max-width: 760px) { .pcp-compare { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>

    @if ($previews === [])
        <p class="pcp-note">Elija el plan en el paso anterior para ver la vista previa.</p>
    @else
        <p class="pcp-note">
            {{ count($previews) - $blockedCount }} de {{ count($previews) }} afiliaciones se pueden cambiar.
            @if ($blockedCount > 0) Las bloqueadas se omiten; el motivo está en cada una. @endif
        </p>
    @endif

    @foreach ($previews as $preview)
        @if (($preview['blocked'] ?? null) !== null)
            <section class="pcp-card is-blocked">
                <div class="pcp-head">
                    <span class="pcp-title">{{ $preview['label'] }}</span>
                    <span class="pcp-chip is-bad">Bloqueada</span>
                </div>
                <p class="pcp-reason">{{ $preview['blocked'] }}</p>
            </section>
        @else
            @php
                $previous = $preview['previous'];
                $new = $preview['new'];
                $billing = $preview['billing'];
            @endphp
            <section class="pcp-card">
                <div class="pcp-head">
                    <span class="pcp-title">{{ $preview['label'] }}</span>
                    <span class="pcp-chip is-ok">{{ $preview['action'] === 'CAMBIO DE PLAN' ? 'Cambio de plan' : 'Cambio de frecuencia' }}</span>
                </div>
                <div class="pcp-body">
                    <div class="pcp-compare">
                        <div><span>Plan</span><strong>@if ($previous['plan'] !== $new['plan'] || $previous['coverage'] !== $new['coverage'])<em>{{ $previous['plan'] }}{{ $previous['coverage'] ? ' · '.$previous['coverage'] : '' }}</em>@endif{{ $new['plan'] }}{{ $new['coverage'] ? ' · '.$new['coverage'] : '' }}</strong></div>
                        <div><span>Frecuencia</span><strong>@if (strtoupper((string) $previous['frequency']) !== $new['frequency'])<em>{{ $previous['frequency'] ?: '—' }}</em>@endif{{ $new['frequency'] }}</strong></div>
                        <div><span>Tarifa anual</span><strong>@if ((float) $previous['fee_anual'] !== (float) $new['fee_anual'])<em>{{ $money($previous['fee_anual']) }}</em>@endif{{ $money($new['fee_anual']) }}</strong></div>
                        <div><span>Monto por período</span><strong>@if ((float) $previous['total_amount'] !== (float) $new['total_amount'])<em>{{ $money($previous['total_amount']) }}</em>@endif{{ $money($new['total_amount']) }}</strong></div>
                    </div>

                    <table class="pcp-table">
                        <thead><tr><th>Afiliado</th><th>Edad</th><th>Rango</th><th>Tarifa anual</th><th>Por período</th></tr></thead>
                        <tbody>
                            @foreach ($preview['affiliates'] as $line)
                                <tr>
                                    <td>{{ $line['name'] }}@if ($line['relationship'] !== '') <span style="color:#6b7280;">· {{ $line['relationship'] }}</span>@endif</td>
                                    <td>{{ $line['age'] !== null ? $line['age'].' años' : 'Sin dato' }}</td>
                                    <td>{{ $line['new_age_range'] }}@if ($line['overridden']) <span style="color:#1d4ed8;">(elegido a mano)</span>@elseif ($line['kept']) <span style="color:#6b7280;">(se conserva)</span>@endif</td>
                                    <td>@if ((float) $line['previous_fee'] !== (float) $line['new_fee'])<em style="font-style:normal;color:#6b7280;text-decoration:line-through;">{{ $money($line['previous_fee']) }}</em> @endif{{ $money($line['new_fee']) }}</td>
                                    <td>{{ $money($line['new_period']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <p class="pcp-note">
                        @switch($billing['mode'])
                            @case('updated')
                                <strong>Cobranza:</strong> se recalculan {{ count($billing['updated']) }} cuota(s) POR PAGAR con su mismo número de aviso
                                ({{ collect($billing['updated'])->map(fn ($item) => $item['invoice'].': '.$money($item['previous_amount']).' → '.$money($item['amount']))->implode('; ') }}).
                                @break
                            @case('regenerated')
                                <strong>Cobranza:</strong> se anulan {{ count($billing['cancelled']) }} cuota(s) POR PAGAR ({{ collect($billing['cancelled'])->pluck('invoice')->implode(', ') }})
                                y se crean {{ count($billing['created']) }} con frecuencia {{ mb_strtolower($new['frequency']) }} para los mismos {{ $billing['months'] }} meses:
                                {{ collect($billing['created'])->take(12)->map(fn ($item) => $item['date'].' '.$money($item['amount']))->implode(' · ') }}{{ count($billing['created']) > 12 ? ' …' : '' }}.
                                @break
                            @default
                                <strong>Cobranza:</strong> no tiene cuotas POR PAGAR; las próximas se generarán con el plan nuevo.
                        @endswitch
                        Las cuotas pagadas no cambian.
                    </p>

                    @if ($preview['white_company'])
                        <p class="pcp-note"><strong>Empresa aliada {{ $preview['white_company']['company'] }}:</strong> tarifa negociada recalculada con el plan nuevo · venta {{ $money($preview['white_company']['sale_price']) }} · neta {{ $money($preview['white_company']['neta']) }} al año.</p>
                    @endif

                    @foreach ($preview['warnings'] as $warning)
                        <p class="pcp-warn">Aviso: {{ $warning }}</p>
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach
</div>
