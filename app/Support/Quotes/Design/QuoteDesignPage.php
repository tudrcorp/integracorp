<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use App\Models\Plan;
use App\Support\QuotePdfCoverageTable;
use App\Support\QuotePdfPlanStructure;
use App\Support\TuDrQuote\QuoteDocumentLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Página de cálculos de un plan con el diseño de cotización aplicado.
 *
 * Los números salen de los mismos constructores que la página de siempre
 * ({@see QuotePdfCoverageTable}, {@see QuotePdfPlanStructure}): el diseño cambia el aspecto, nunca
 * las tarifas. La plantilla respeta las reglas de DomPDF del proyecto (CLAUDE.md §4.2.2).
 */
final class QuoteDesignPage
{
    /**
     * Dibuja la página. Si no cabe en una hoja, la vuelve a dibujar ajustada; si aun así no cabe,
     * se entrega en dos hojas antes que recortar tarifas.
     *
     * @param  array<string, mixed>  $details  payload de los generadores de propuestas
     */
    public static function render(array $details, QuoteDesignTheme $theme, string $scope): string
    {
        $document = self::renderDocument($details, $theme, $scope, fitToPage: false);

        if (self::pageCount($document) <= 1) {
            return $document;
        }

        return self::renderDocument($details, $theme, $scope, fitToPage: true);
    }

    /**
     * Datos que consume la plantilla `documents.propuesta-plan-disenio`.
     *
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public static function viewData(array $details, QuoteDesignTheme $theme, string $scope, bool $fitToPage = false): array
    {
        $planId = (int) ($details['plan'] ?? 0);
        $grouped = collect($details['data'] ?? [])->groupBy('age_range');
        $coverageTable = QuotePdfCoverageTable::build($grouped, $planId);
        $matrix = QuotePdfPlanStructure::benefitsMatrix($planId);
        $coverageColumns = $coverageTable['coverageColumns'];
        $flatRows = $coverageColumns === [] ? QuotePdfPlanStructure::flatRateRows($grouped) : [];

        return [
            'theme' => $theme,
            'fitToPage' => $fitToPage,
            'planName' => self::planName($planId),
            'clientLabel' => $scope === QuoteDocumentLayout::SCOPE_CORPORATE ? 'Cliente' : 'Afiliado titular',
            'clientName' => self::clientName((string) ($details['name'] ?? ''), $scope),
            'agentName' => trim((string) ($details['agent_name'] ?? '')),
            'issuedAt' => self::issuedAt((string) ($details['date'] ?? '')),
            'controlNumber' => self::controlNumber((string) ($details['code'] ?? '')),
            'benefitColumns' => $matrix['columns'],
            'benefitRows' => $matrix['rows'],
            'isDense' => $matrix['isDense'],
            'coverageColumns' => $coverageColumns,
            'tableRows' => $coverageTable['rows'],
            'totals' => $coverageTable['totals'],
            'flatRows' => $flatRows,
            'summary' => self::summary($coverageColumns, $coverageTable['rows'], $coverageTable['totals'], $flatRows),
            'upgrades' => (array) ($details['upgrades'] ?? []),
            'fonts' => self::fonts($theme),
        ];
    }

    /**
     * Tarjetas «anual / semestral / trimestral» del total del grupo, cuando el plan tiene una sola
     * columna de precio (una cobertura o un paquete). Con varias coberturas no hay un único total:
     * se devuelve `null` y la plantilla dibuja la matriz completa.
     *
     * @param  list<float>  $coverageColumns
     * @param  list<array{age_range: string, total_persons: int, amounts: array<string, float>}>  $tableRows
     * @param  array<string, float|null>  $totals
     * @param  list<array{age_range: string, total_persons: int, annual: float, biannual: float, quarterly: float}>  $flatRows
     * @return array{coverage_label: ?string, rows: list<array{age_range: string, persons: int, annual: float}>, annual: float, biannual: float, quarterly: float}|null
     */
    public static function summary(array $coverageColumns, array $tableRows, array $totals, array $flatRows): ?array
    {
        if (count($coverageColumns) === 1) {
            $key = QuotePdfCoverageTable::coverageKey($coverageColumns[0]);
            $annual = (float) ($totals[$key] ?? 0);

            return [
                'coverage_label' => QuotePdfCoverageTable::formatLabel($coverageColumns[0]),
                'rows' => array_map(static fn (array $row): array => [
                    'age_range' => (string) $row['age_range'],
                    'persons' => (int) $row['total_persons'],
                    'annual' => (float) ($row['amounts'][$key] ?? 0),
                ], $tableRows),
                'annual' => $annual,
                'biannual' => $annual / 2,
                'quarterly' => $annual / 4,
            ];
        }

        if ($coverageColumns === [] && $flatRows !== []) {
            return [
                'coverage_label' => null,
                'rows' => array_map(static fn (array $row): array => [
                    'age_range' => (string) $row['age_range'],
                    'persons' => (int) $row['total_persons'],
                    'annual' => (float) $row['annual'],
                ], $flatRows),
                'annual' => (float) array_sum(array_column($flatRows, 'annual')),
                'biannual' => (float) array_sum(array_column($flatRows, 'biannual')),
                'quarterly' => (float) array_sum(array_column($flatRows, 'quarterly')),
            ];
        }

        return null;
    }

    public static function money(float $amount): string
    {
        return number_format(round($amount), 0, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function renderDocument(array $details, QuoteDesignTheme $theme, string $scope, bool $fitToPage): string
    {
        /** DomPDF guarda aquí las métricas de las fuentes subidas; sin la carpeta, el PDF falla. */
        File::ensureDirectoryExists(storage_path('fonts'));

        return Pdf::loadView('documents.propuesta-plan-disenio', self::viewData($details, $theme, $scope, $fitToPage))
            ->setPaper('a4')
            ->output();
    }

    /**
     * Familias CSS a usar: las subidas en TTF si las hay; si no, las incluidas en el motor.
     *
     * @return array{sans: string, mono: string, face_regular: ?string, face_bold: ?string, face_mono: ?string}
     */
    private static function fonts(QuoteDesignTheme $theme): array
    {
        $regular = $theme->absolutePath($theme->fontRegularPath);
        $bold = $theme->absolutePath($theme->fontBoldPath);
        $mono = $theme->absolutePath($theme->fontMonoPath);

        return [
            'sans' => $regular !== null ? "'QdSans', '{$theme->sansFamily}'" : "'{$theme->sansFamily}'",
            'mono' => $mono !== null ? "'QdMono', '{$theme->monoFamily}'" : "'{$theme->monoFamily}'",
            'face_regular' => $regular,
            /** Sin negrita propia, la negrita usa la misma fuente: mejor que saltar a Helvetica. */
            'face_bold' => $bold ?? $regular,
            'face_mono' => $mono,
        ];
    }

    private static function planName(int $planId): string
    {
        $description = $planId > 0 ? Plan::query()->whereKey($planId)->value('description') : null;

        return filled($description) ? (string) $description : 'Propuesta económica';
    }

    private static function clientName(string $name, string $scope): string
    {
        $name = trim($name);

        if ($name === '' || $scope === QuoteDocumentLayout::SCOPE_CORPORATE) {
            return $name;
        }

        return 'Sr(a). '.$name;
    }

    /**
     * Fecha de emisión de la cotización (no la de hoy): una propuesta regenerada conserva su fecha.
     */
    private static function issuedAt(string $date): string
    {
        try {
            return $date !== '' ? Carbon::createFromFormat('d-m-Y', $date)->format('d/m/Y') : now()->format('d/m/Y');
        } catch (Throwable) {
            return str_replace('-', '/', $date);
        }
    }

    private static function controlNumber(string $code): string
    {
        return str_replace(['COT-IND-', 'COT-CORP-'], '', $code);
    }

    private static function pageCount(string $document): int
    {
        try {
            return (new Fpdi)->setSourceFile(StreamReader::createByString($document));
        } catch (Throwable) {
            return 1;
        }
    }
}
