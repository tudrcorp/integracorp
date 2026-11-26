<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Support\Affiliations\IndividualPreAffiliationPricing;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/**
 * Resumen de montos del formulario de pre-afiliación individual: lo que se va a guardar, persona
 * por persona, con la tarifa vigente según la edad. Avisa si alguien no tiene tarifa (no se podrá
 * guardar) y si el total difiere de lo cotizado. El servidor vuelve a calcular al guardar.
 */
final class IndividualPreAffiliationSummary
{
    public static function make(): Placeholder
    {
        return Placeholder::make('pre_affiliation_amounts_summary')
            ->label('Montos de la pre-afiliación')
            ->columnSpanFull()
            ->hiddenOn('edit')
            ->content(fn (Get $get): HtmlString => self::render($get));
    }

    public static function render(Get $get): HtmlString
    {
        $pricing = IndividualPreAffiliationPricing::make();
        $planId = IndividualPreAffiliationPricing::intOrNull($get('plan_id'));
        $coverageId = IndividualPreAffiliationPricing::intOrNull($get('coverage_id'));
        $frequency = IndividualPreAffiliationPricing::frequency($get('payment_frequency'));

        $result = $pricing->price($planId, $coverageId, $frequency, IndividualPreAffiliationPricing::peopleFromFormData([
            'full_name_ti' => $get('full_name_ti'),
            'birth_date_ti' => $get('birth_date_ti'),
            'feedback' => $get('feedback'),
            'affiliates' => $get('affiliates'),
        ]));

        $quoted = $pricing->quotedAnnualFor(IndividualPreAffiliationPricing::intOrNull($get('individual_quote_id')), $planId, $coverageId);

        return new HtmlString(self::html($result, $frequency, $quoted));
    }

    /**
     * @param  array{lines: list<array{name: string, age: int|null, annual_fee: float|null, period_amount: float|null, problem: string|null}>, problems: list<string>, fee_anual: float, total_amount: float, persons: int}  $result
     */
    public static function html(array $result, string $frequency, ?float $quoted): string
    {
        $usd = static fn (?float $amount): string => $amount === null ? '—' : 'US$ '.number_format($amount, 2, ',', '.');
        $period = mb_strtolower($frequency);
        $rows = '';

        foreach ($result['lines'] as $line) {
            $rows .= '<tr style="border-top:1px solid rgba(128,128,128,.25)">'
                .'<td style="padding:6px 8px">'.e($line['name']).'</td>'
                .'<td style="padding:6px 8px;text-align:center">'.($line['age'] !== null ? e((string) $line['age']) : '—').'</td>'
                .'<td style="padding:6px 8px;text-align:right">'.($line['problem'] !== null ? '<span style="color:#dc2626">Sin tarifa</span>' : e($usd($line['annual_fee']))).'</td>'
                .'<td style="padding:6px 8px;text-align:right">'.($line['problem'] !== null ? '—' : e($usd($line['period_amount']))).'</td>'
                .'</tr>';
        }

        $html = '<div style="font-size:13px">'
            .'<table style="width:100%;border-collapse:collapse">'
            .'<thead><tr style="text-align:left;opacity:.75"><th style="padding:6px 8px">Persona</th><th style="padding:6px 8px;text-align:center">Edad</th><th style="padding:6px 8px;text-align:right">Tarifa anual</th><th style="padding:6px 8px;text-align:right">Cuota '.e($period).'</th></tr></thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'<tfoot><tr style="border-top:2px solid rgba(128,128,128,.4);font-weight:600"><td style="padding:6px 8px" colspan="2">Total ('.e((string) $result['persons']).' '.($result['persons'] === 1 ? 'persona' : 'personas').')</td>'
            .'<td style="padding:6px 8px;text-align:right">'.e($usd($result['fee_anual'])).'</td>'
            .'<td style="padding:6px 8px;text-align:right">'.e($usd($result['total_amount'])).'</td></tr></tfoot>'
            .'</table>';

        if ($result['problems'] !== []) {
            $html .= '<div style="margin-top:10px;padding:8px 10px;border-radius:8px;background:rgba(220,38,38,.1);color:#b91c1c"><strong>No se podrá guardar así:</strong><ul style="margin:4px 0 0 18px;list-style:disc">';

            foreach ($result['problems'] as $problem) {
                $html .= '<li>'.e($problem).'</li>';
            }

            $html .= '</ul></div>';
        } elseif ($quoted !== null && abs($quoted - $result['fee_anual']) >= 0.01) {
            $html .= '<div style="margin-top:10px;padding:8px 10px;border-radius:8px;background:rgba(217,119,6,.12);color:#b45309">'
                .'La cotización indicaba '.e($usd($quoted)).' al año. Con las edades y personas cargadas, el monto real es '.e($usd($result['fee_anual'])).'. Se guardará el monto real.'
                .'</div>';
        }

        return $html.'</div>';
    }
}
