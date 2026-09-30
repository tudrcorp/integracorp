<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Formato de salida del generador de reportes de Operaciones.
 */
enum OperationReportFormat: string
{
    case Excel = 'xlsx';
    case Csv = 'csv';
    case Pdf = 'pdf';

    public function label(): string
    {
        return match ($this) {
            self::Excel => 'Excel',
            self::Csv => 'CSV',
            self::Pdf => 'PDF',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Csv => 'text/csv; charset=UTF-8',
            self::Pdf => 'application/pdf',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Excel => 'heroicon-o-table-cells',
            self::Csv => 'heroicon-o-document-text',
            self::Pdf => 'heroicon-o-document',
        };
    }
}
