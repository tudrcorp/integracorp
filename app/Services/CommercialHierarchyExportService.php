<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agency;
use App\Support\CommercialStructure\CommercialHierarchyDetailColumns;
use App\Support\CommercialStructure\CommercialHierarchyExportRows;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;

/**
 * Exporta la jerarquía comercial de una agencia a Excel y PDF.
 *
 * Ni una hoja de cálculo ni un PDF dibujan las líneas de conexión del diagrama, así que la
 * estructura se transmite por tres vías a la vez: el nivel, el nombre sangrado y la ruta
 * completa de códigos. Cualquiera de las tres basta para reconstruir de quién cuelga cada
 * nodo, incluso si el usuario ordena o filtra la hoja.
 */
final class CommercialHierarchyExportService
{
    private const INDENT = '      ';

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Nivel',
            'Jerarquía',
            'Tipo',
            'Código',
            'Nombre',
            'Estatus',
            'Depende de',
            'Ruta jerárquica',
            'Estructura',
            ...CommercialHierarchyDetailColumns::headers(),
        ];
    }

    public static function filenameFor(Agency $agency, string $extension): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]/', '', trim((string) $agency->code)) ?: 'AGENCIA';

        return 'jerarquia-comercial-'.$code.'-'.now()->format('Y-m-d_His').'.'.$extension;
    }

    /**
     * Sangría del nombre en la hoja: el conector se dibuja con texto porque un XLSX no
     * tiene forma nativa de expresar profundidad.
     */
    public static function indentedName(int $level, string $name): string
    {
        if ($level <= 1) {
            return $name;
        }

        return str_repeat(self::INDENT, $level - 1).'└─ '.$name;
    }

    /**
     * Devuelve la ruta absoluta del .xlsx generado. Quien lo descargue debe borrarlo después.
     */
    public static function toXlsx(Agency $agency): string
    {
        $rows = CommercialHierarchyExportRows::forAgency($agency);

        $path = self::temporaryPath('xlsx');

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(self::headers(), self::headerStyle()));

        $entities = CommercialHierarchyDetailColumns::loadEntities($rows);
        $bodyStyle = self::bodyStyle();

        foreach ($rows as $row) {
            $record = CommercialHierarchyDetailColumns::resolve($row, $entities);

            $writer->addRow(Row::fromValues([
                $row['level'],
                self::indentedName($row['level'], $row['name']),
                $row['type'],
                $row['code'],
                $row['name'],
                $row['status'],
                $row['parent_code'] !== '' ? $row['parent_code'] : '—',
                $row['path'],
                $row['structure'] !== '' ? $row['structure'] : '—',
                ...CommercialHierarchyDetailColumns::values($record),
            ], $bodyStyle));
        }

        $writer->close();

        return $path;
    }

    /**
     * Devuelve la ruta absoluta del PDF generado. Quien lo descargue debe borrarlo después.
     */
    public static function toPdf(Agency $agency): string
    {
        $rows = CommercialHierarchyExportRows::forAgency($agency);

        $pdf = Pdf::loadView('documents.commercial-hierarchy', [
            'agency' => $agency,
            'rows' => $rows,
            'totals' => CommercialHierarchyExportRows::totals($rows),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $path = self::temporaryPath('pdf');

        file_put_contents($path, $pdf->output());

        return $path;
    }

    /**
     * `tempnam()` crea el archivo sin extensión y devuelve su ruta. Al añadirle la extensión
     * escribiríamos en otro archivo y el original quedaría huérfano en el temporal del
     * sistema, así que se borra: el nombre ya está reservado y no puede colisionar.
     */
    private static function temporaryPath(string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'commercial_hierarchy_');

        if ($base === false) {
            throw new RuntimeException('No se pudo preparar el archivo temporal de la exportación.');
        }

        @unlink($base);

        return $base.'.'.$extension;
    }

    private static function headerStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(9)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(252, 254, 253))
            ->setBackgroundColor(Color::rgb(5, 47, 96))
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER);
    }

    private static function bodyStyle(): Style
    {
        return (new Style)
            ->setFontSize(9)
            ->setFontName('Helvetica')
            ->setFontColor(Color::rgb(0, 0, 0))
            ->setCellAlignment(CellAlignment::LEFT)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP);
    }
}
