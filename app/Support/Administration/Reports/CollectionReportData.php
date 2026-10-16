<?php

declare(strict_types=1);

namespace App\Support\Administration\Reports;

/**
 * Contenido de un reporte de cobranza listo para escribir en cualquier formato.
 *
 * `lines` es un iterable perezoso de líneas tipadas: los datos se leen por lotes
 * y nunca se cargan enteros en memoria. Cada línea es una fila de datos o, en el
 * reporte por estructura, un encabezado de agencia/agente o un subtotal; la
 * última siempre es el total general.
 */
final readonly class CollectionReportData
{
    public const LINE_ROW = 'row';

    public const LINE_AGENCY = 'agency';

    public const LINE_AGENT = 'agent';

    public const LINE_SUBTOTAL = 'subtotal';

    public const LINE_TOTAL = 'total';

    /**
     * @param  list<string>  $headings
     * @param  iterable<int, array{kind: string, values: list<scalar|null>, label?: string}>  $lines
     * @param  list<int>  $numericColumns  Índices de columnas numéricas (alineación y formato).
     * @param  list<string>  $criteria  Periodo, estatus y alcance en texto, para el encabezado.
     * @param  list<int>  $pdfColumns  Columnas que caben en el PDF horizontal.
     */
    public function __construct(
        public string $title,
        public array $headings,
        public iterable $lines,
        public array $numericColumns,
        public array $criteria,
        public array $pdfColumns,
    ) {}
}
