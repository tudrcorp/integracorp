<?php

declare(strict_types=1);

namespace App\Support\Affiliations\DateCorrection;

use App\Models\AffiliationDateCorrection;
use Illuminate\Support\HtmlString;

/**
 * Vista previa «antes → después» de una corrección de vigencia y resumen de su reversión.
 */
final class AffiliationDateCorrectionPreview
{
    /**
     * @param  array<string, mixed>  $plan  Resultado de {@see AffiliationDateCorrector::plan()}
     */
    public static function html(array $plan): HtmlString
    {
        $html = '<div class="space-y-4 text-sm">';
        $html .= self::list($plan['blockers'] ?? [], 'No se puede corregir hasta resolver esto:', 'text-red-700 dark:text-red-300');

        $before = $plan['before'] ?? [];
        $after = $plan['after'] ?? [];

        $html .= '<div><p class="font-semibold mb-1">Afiliación</p><table class="w-full text-left border-collapse"><thead><tr>'
            .'<th class="px-2 py-1"></th><th class="px-2 py-1">Hoy</th><th class="px-2 py-1">Queda</th></tr></thead><tbody>'
            .self::row('Fecha de emisión', $before['activated_at'] ?? null, $after['activated_at'] ?? null)
            .self::row('Fecha de vigencia', $before['effective_date'] ?? null, $after['effective_date'] ?? null)
            .self::row('Inicio del ciclo de cobro (según los avisos)', $before['cycle_start'] ?? null, $after['effective_date'] ?? null)
            .self::row('Próxima renovación', $before['renewal'] ?? null, $after['renewal'] ?? null)
            .'</tbody></table></div>';

        $collections = $plan['collections'] ?? [];

        if ($collections !== []) {
            $html .= '<div><p class="font-semibold mb-1">Avisos POR PAGAR que se reprograman ('.e((string) ($plan['frequency'] ?? '')).')</p>'
                .'<table class="w-full text-left border-collapse"><thead><tr><th class="px-2 py-1">Aviso</th><th class="px-2 py-1">Cuota</th>'
                .'<th class="px-2 py-1">Hoy</th><th class="px-2 py-1">Queda</th><th class="px-2 py-1 text-right">Monto</th></tr></thead><tbody>';

            foreach ($collections as $collection) {
                $old = e($collection['old']).($collection['was_overdue'] ? ' <span class="text-red-700 dark:text-red-300">(vencido)</span>' : '');
                $new = '<strong>'.e($collection['new']).'</strong>'.($collection['is_overdue']
                    ? ' <span class="text-red-700 dark:text-red-300">(vencido)</span>'
                    : ($collection['was_overdue'] ? ' <span class="text-green-700 dark:text-green-300">(deja de estar vencido)</span>' : ''));

                $html .= '<tr><td class="px-2 py-1">'.e($collection['invoice']).'</td><td class="px-2 py-1">'.e($collection['installment']).'</td>'
                    .'<td class="px-2 py-1">'.$old.'</td><td class="px-2 py-1">'.$new.'</td>'
                    .'<td class="px-2 py-1 text-right">'.e(number_format((float) $collection['amount'], 2, ',', '.')).'</td></tr>';
            }

            $html .= '</tbody></table><p class="mt-1 text-gray-600 dark:text-gray-400">Los montos no cambian. Los avisos PAGADOS y los pagos registrados no se tocan.</p></div>';
        } elseif (($plan['blockers'] ?? []) === []) {
            $html .= '<p class="text-gray-600 dark:text-gray-400">No hay avisos POR PAGAR que reprogramar.</p>';
        }

        $other = [];

        foreach ($plan['renovations'] ?? [] as $renovation) {
            $other[] = match ($renovation['action']) {
                'annul' => 'Propuesta de renovación del '.$renovation['old'].': se anula (quedaría duplicada).',
                'keep' => 'Propuesta de renovación del '.$renovation['new'].': ya está en la fecha correcta.',
                default => 'Propuesta de renovación: '.$renovation['old'].' → '.$renovation['new'].' ('.$renovation['remaining_days'].' días).',
            };
        }

        foreach ($plan['payments'] ?? [] as $payment) {
            $other[] = 'Pago #'.$payment['id'].' (solo fechas informativas): próximo pago '.($payment['prox_old'] ?? '—').' → '.$payment['prox_new']
                .'; renovación '.($payment['renewal_old'] ?? '—').' → '.$payment['renewal_new'].'.';
        }

        if (($plan['annual'] ?? []) !== []) {
            $other[] = 'Planificación anual: '.count($plan['annual']).' fila(s) con los meses de cuota recalculados.';
        }

        $html .= self::list($other, 'También se actualiza:', '');
        $html .= self::list($plan['warnings'] ?? [], 'Advertencias (no bloquean):', 'text-amber-700 dark:text-amber-300');

        return new HtmlString($html.'</div>');
    }

    /**
     * @param  list<string>  $blockers
     */
    public static function rollbackHtml(?AffiliationDateCorrection $correction, array $blockers): HtmlString
    {
        if (! $correction instanceof AffiliationDateCorrection) {
            return new HtmlString('<p class="text-sm">No hay una corrección aplicada que revertir.</p>');
        }

        $html = '<div class="space-y-3 text-sm"><p>Corrección del <strong>'.e($correction->applied_at?->format('d/m/Y H:i') ?? '').'</strong> por '
            .e($correction->applied_by_name).': fecha corregida <strong>'.e($correction->new_date).'</strong>. Vuelve a: emisión <strong>'
            .e($correction->activated_at_before ?? '—').'</strong>, vigencia <strong>'.e($correction->effective_date_before ?? '—').'</strong>.</p>'
            .'<p class="text-gray-600 dark:text-gray-400">Motivo: '.e($correction->reason).'</p>';

        $html .= $blockers === []
            ? '<p class="font-semibold text-green-700 dark:text-green-300">Nada cambió desde la corrección: se puede revertir de forma segura.</p>'
            : self::list($blockers, 'No se puede revertir todavía:', 'text-red-700 dark:text-red-300');

        return new HtmlString($html.'</div>');
    }

    private static function row(string $label, ?string $before, ?string $after): string
    {
        $changed = $before !== $after;

        return '<tr><td class="px-2 py-1">'.e($label).'</td><td class="px-2 py-1">'.e($before ?? '—').'</td>'
            .'<td class="px-2 py-1">'.($changed ? '<strong>' : '').e($after ?? '—').($changed ? '</strong>' : '').'</td></tr>';
    }

    /**
     * @param  list<string>  $items
     */
    private static function list(array $items, string $title, string $class): string
    {
        if ($items === []) {
            return '';
        }

        $lis = '';

        foreach ($items as $item) {
            $lis .= '<li>'.e($item).'</li>';
        }

        return '<div class="'.$class.'"><p class="font-semibold mb-1">'.e($title).'</p><ul class="list-disc pl-5 space-y-1">'.$lis.'</ul></div>';
    }
}
