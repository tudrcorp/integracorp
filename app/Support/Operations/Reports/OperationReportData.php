<?php

declare(strict_types=1);

namespace App\Support\Operations\Reports;

/**
 * Contenido de un reporte listo para escribir en cualquier formato.
 *
 * Las filas son un iterable perezoso: el detalle y la tabla completa se leen
 * por lotes y nunca se cargan enteros en memoria.
 */
final readonly class OperationReportData
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<int, list<scalar|null>>  $rows
     * @param  list<scalar|null>|null  $totals
     * @param  list<int>  $numericColumns  Índices de columnas numéricas (alineación y formato en PDF/Excel).
     * @param  list<string>  $criteria  Periodo y filtros en texto, para el encabezado del PDF.
     * @param  list<int>|null  $pdfColumns  Columnas que caben en el PDF horizontal; null = todas.
     * @param  int|null  $totalRows  Filas antes de aplicar el tope del PDF (sólo resúmenes).
     */
    public function __construct(
        public string $title,
        public array $headings,
        public iterable $rows,
        public ?array $totals = null,
        public array $numericColumns = [],
        public array $criteria = [],
        public ?array $pdfColumns = null,
        public ?int $totalRows = null,
    ) {}
}
