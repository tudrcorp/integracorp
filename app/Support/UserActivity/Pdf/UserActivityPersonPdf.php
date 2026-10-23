<?php

declare(strict_types=1);

namespace App\Support\UserActivity\Pdf;

use App\Models\User;
use App\Support\Telemedicine\TelemedicinePdfPageNumbers;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * PDF de actividad de una persona en un rango. Se dibuja en una sola pasada y se
 * entrega al instante: el contenido fluye sin acomodos ni mediciones y los gráficos
 * son imágenes SVG.
 */
final class UserActivityPersonPdf
{
    /** Centro y altura del folio «Página X de Y» (A4 vertical, bajo el margen inferior de 12 mm). */
    private const FOLIO_CENTER_MM = 105.0;

    private const FOLIO_TOP_MM = 288.0;

    private const MEMORY_BYTES = 1024 * 1024 * 1024;

    /**
     * @param  array<string, mixed>  $data  Salida de {@see UserActivityPersonPdfData::build()}.
     */
    public static function render(array $data): string
    {
        /** Un mes de barras pide memoria a DomPDF; solo se sube el límite, nunca se baja. */
        if (self::bytes((string) ini_get('memory_limit')) < self::MEMORY_BYTES) {
            @ini_set('memory_limit', '1024M');
        }

        @set_time_limit(120);

        $dompdf = Pdf::loadView('documents.user-activity-person-report', ['data' => $data])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)
            ->getDomPDF();

        $dompdf->render();
        TelemedicinePdfPageNumbers::applyTo($dompdf, [self::FOLIO_CENTER_MM], self::FOLIO_TOP_MM);

        return (string) $dompdf->output();
    }

    private static function bytes(string $limit): int
    {
        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $value = (int) $limit;

        return match (strtolower(substr($limit, -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    public static function filename(User $user, CarbonImmutable $from, CarbonImmutable $to): string
    {
        $name = Str::slug((string) $user->name) ?: 'usuario-'.$user->id;

        return 'actividad-'.$name.'-'.$from->format('Ymd').($from->isSameDay($to) ? '' : '-'.$to->format('Ymd')).'.pdf';
    }
}
