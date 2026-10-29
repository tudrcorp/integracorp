<?php

declare(strict_types=1);

namespace App\Support\Quotes;

use App\Models\IndividualQuote;
use App\Support\IndividualQuotePdfGenerator;
use App\Support\IndividualQuotePdfLayout;
use App\Support\TuDrQuote\QuoteDocumentAssembler;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use setasign\Fpdi\Fpdi;
use Throwable;

/**
 * Propuesta individual del microservicio con la página de cada plan compuesta aquí.
 *
 * El servicio entrega portada, «acerca», una página de cálculos por plan (con la imagen de
 * beneficios horneada), patologías y contraportada. Se conserva todo salvo esas páginas de
 * cálculos, que se reemplazan por la página dinámica (`planes-cotizacion-estructura`): beneficios
 * de la configuración del plan, tarifas y, debajo, los upgrades de la cotización.
 *
 * Si algo no cuadra (cantidad de páginas o de planes) se devuelve el documento del servicio tal
 * cual: nunca se pone la página de un plan en lugar de otro.
 */
final class IndividualQuoteProposalPages
{
    /**
     * @param  list<int>  $planIds  planes en el orden en que el servicio dibujó sus páginas
     * @return array{document: string, calculation_pages: int}
     */
    public static function replaceCalculationPages(string $document, int $quoteId, array $planIds): array
    {
        $unchanged = ['document' => $document, 'calculation_pages' => count($planIds)];
        $quote = IndividualQuote::query()->find($quoteId);

        if ($quote === null || $planIds === []) {
            return $unchanged;
        }

        $files = [];

        try {
            $source = self::temporary($document, $files);
            $pdf = new Fpdi;
            $total = $pdf->setSourceFile($source);
            $before = QuoteDocumentAssembler::PAGES_BEFORE_CALCULATIONS;

            if ($total < $before + count($planIds)) {
                Log::warning('quote-pdf: el documento del servicio trae menos páginas de las esperadas; se deja como viene', ['quote_id' => $quoteId, 'pages' => $total, 'plans' => $planIds]);

                return $unchanged;
            }

            $planFiles = [];

            foreach ($planIds as $planId) {
                $details = IndividualQuotePdfGenerator::detailsPayload($quote, $planId, IndividualQuotePdfLayout::resolveForIndividual($planId));

                if ($details === null) {
                    return $unchanged;
                }

                $planFiles[] = self::temporary(self::renderPlanPage($details), $files);
            }

            $calculationPages = 0;
            self::copy($pdf, $source, 1, $before);

            foreach ($planFiles as $planFile) {
                $calculationPages += self::copy($pdf, $planFile);
            }

            self::copy($pdf, $source, $before + count($planIds) + 1, $total);

            return ['document' => $pdf->Output('S'), 'calculation_pages' => $calculationPages];
        } catch (Throwable $exception) {
            Log::error('quote-pdf: no se pudo componer la página dinámica del plan; se deja el documento del servicio', [
                'quote_id' => $quoteId,
                'message' => $exception->getMessage(),
            ]);

            return $unchanged;
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function renderPlanPage(array $details): string
    {
        return Pdf::loadView('documents.propuesta-plan-dinamica', [
            'details' => $details,
            'group_collect' => collect($details['data'])->groupBy('age_range'),
            'name_user' => (string) ($details['agent_name'] ?? ''),
        ])->setPaper('a4')->output();
    }

    /**
     * Copia páginas de un PDF al documento final. Devuelve cuántas copió.
     */
    private static function copy(Fpdi $pdf, string $file, int $from = 1, ?int $to = null): int
    {
        $pages = $pdf->setSourceFile($file);
        $to = min($to ?? $pages, $pages);
        $copied = 0;

        for ($page = $from; $page <= $to; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
            $copied++;
        }

        return $copied;
    }

    /**
     * @param  list<string>  $files
     */
    private static function temporary(string $contents, array &$files): string
    {
        $file = tempnam(sys_get_temp_dir(), 'iq-proposal-');
        file_put_contents($file, $contents);
        $files[] = $file;

        return $file;
    }
}
