<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Certificates;

use App\Models\PlanCertificateTemplate;
use App\Support\DomPdfBatchRenderOptions;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Pone el diseño propio del plan en lugar de la hoja del certificado (portada y
 * continuación de beneficios). Relación de afiliados, carnets, firma y pie siguen
 * con el diseño estándar.
 *
 * En `pages` el diseño es UNA entrada `kind = custom` que ocupa `pageCount` hojas:
 * los tramos, la vista previa y los envíos parciales siguen funcionando igual, y
 * la numeración «Página X de Y» de las demás hojas cuenta esas hojas.
 *
 * Si el diseño no se puede dibujar, el certificado sale con el diseño
 * predeterminado (nunca se queda sin certificado) y se reporta el error.
 */
final class PlanCertificateCover
{
    public const KIND = 'custom';

    /** Hojas máximas que puede ocupar el diseño de la hoja del certificado. */
    public const MAX_PAGES = 4;

    /**
     * @param  array<string, mixed>  $data  salida de CertificateDocumentData::assemble
     * @param  list<array<string, mixed>>  $benefitRows
     * @param  list<array<string, mixed>>  $people
     * @return array<string, mixed>
     */
    public static function apply(array $data, PlanCertificateTemplate $template, array $benefitRows, array $people): array
    {
        try {
            $data['benefitRows'] = $benefitRows;
            $data['coverPeople'] = array_slice($people, 0, PlanCertificateTemplateEngine::COVER_PEOPLE_LIMIT);

            $rest = array_values(array_filter($data['pages'], static fn (array $page): bool => ! in_array($page['kind'], ['cert', 'cont'], true)));
            $pageCount = self::pageCount((string) $template->html, array_merge($data, ['totalPaginas' => count($rest) + 1]));

            array_unshift($rest, [
                'num' => 1,
                'kind' => self::KIND,
                'isCert' => true,
                'title' => '',
                'note' => '',
                'rows' => [],
                'hasBenef' => false,
                'hasSeal' => false,
                'people' => [],
                'lastRel' => false,
                'hasInfo' => false,
                'pageCount' => $pageCount,
                'template' => (string) $template->html,
                'templateId' => (int) $template->getKey(),
            ]);

            [$data['pages'], $data['totalPaginas']] = self::renumber($rest);
            $data['relPaginas'] = CertificateDocumentData::relationPagesLabel($data['pages']);
            $data['customTemplateId'] = (int) $template->getKey();

            return $data;
        } catch (Throwable $exception) {
            report($exception);

            return $data;
        }
    }

    /**
     * Numera las hojas contando las que ocupa el diseño propio.
     *
     * @param  list<array<string, mixed>>  $pages
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    public static function renumber(array $pages): array
    {
        $num = 1;
        foreach ($pages as $index => $page) {
            $pages[$index]['num'] = $num;
            $num += max(1, (int) ($page['pageCount'] ?? 1));
        }

        return [$pages, $num - 1];
    }

    public static function isCustom(array $page): bool
    {
        return ($page['kind'] ?? null) === self::KIND;
    }

    /**
     * HTML final del diseño con los datos del documento (totales ya renumerados).
     *
     * @param  array<string, mixed>  $data
     */
    public static function html(array $page, array $data): string
    {
        return PlanCertificateTemplateEngine::render((string) $page['template'], $data);
    }

    /**
     * PDF del diseño propio, dibujado aparte: así ningún estilo del documento
     * estándar se mezcla con el del analista (y viceversa).
     */
    public static function pdf(string $html): string
    {
        return (string) self::dompdf($html)->output();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function pageCount(string $template, array $data): int
    {
        $html = PlanCertificateTemplateEngine::render($template, $data);

        return (int) Cache::remember('plan-certificate-cover-pages:'.sha1($html), now()->addDay(), static function () use ($html): int {
            $pdf = self::dompdf($html);
            $pdf->output();

            return max(1, (int) $pdf->getDomPDF()->getCanvas()->get_page_count());
        });
    }

    private static function dompdf(string $html): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadHTML($html)->setPaper('letter', 'portrait');
        DomPdfBatchRenderOptions::apply($pdf);
        $pdf->setOptions([
            'dpi' => 96,
            'defaultFont' => 'Helvetica',
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'chroot' => array_values(array_unique(array_filter([
                realpath(base_path()),
                realpath(public_path()),
                realpath(storage_path()),
            ]))),
        ], mergeWithDefaults: true);

        return $pdf;
    }
}
