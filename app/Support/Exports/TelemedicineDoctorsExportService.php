<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\TelemedicineDoctor;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class TelemedicineDoctorsExportService extends AbstractScheduledEntityExportService
{
    protected function exportConfigKey(): string
    {
        return 'doctors';
    }

    protected function defaultFilenamePrefix(): string
    {
        return 'integracorp_doctores';
    }

    /**
     * @return array{recordCount: int, rowCount: int, noteCount: int}
     */
    protected function populateSpreadsheet(Writer $writer): array
    {
        $recordCount = 0;
        $rowCount = 0;

        TelemedicineDoctor::query()
            ->with([
                'supplier:id,name,razon_social,rif',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($doctors) use (&$recordCount, &$rowCount, $writer): void {
                foreach ($doctors as $doctor) {
                    /** @var TelemedicineDoctor $doctor */
                    $recordCount++;
                    $writer->addRow(Row::fromValues(self::mapRow($doctor)));
                    $rowCount++;
                }
            });

        return [
            'recordCount' => $recordCount,
            'rowCount' => $rowCount,
            'noteCount' => 0,
        ];
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'ID',
            'Código',
            'Nombre Completo',
            'Número Identificación',
            'Email',
            'Teléfono',
            'Código CM',
            'Código MPPS',
            'Especialidad',
            'Dirección',
            'Estatus',
            'Gestionado Por',
            'ID Proveedor Jurídico',
            'Nombre Proveedor Jurídico',
            'RIF Proveedor Jurídico',
            'Creado Por',
            'Actualizado Por',
            'Fecha Creación',
            'Fecha Actualización',
        ];
    }

    /**
     * @return list<string|int|null>
     */
    private static function mapRow(TelemedicineDoctor $doctor): array
    {
        return [
            $doctor->id,
            self::stringValue($doctor->code),
            self::stringValue($doctor->full_name),
            self::stringValue($doctor->nro_identificacion),
            self::stringValue($doctor->email),
            self::stringValue($doctor->phone),
            self::stringValue($doctor->code_cm),
            self::stringValue($doctor->code_mpps),
            self::stringValue($doctor->specialty),
            self::stringValue($doctor->address),
            self::stringValue($doctor->status),
            self::stringValue($doctor->managed_by),
            $doctor->supplier_id,
            self::stringValue($doctor->supplier?->name ?? $doctor->supplier?->razon_social),
            self::stringValue($doctor->supplier?->rif),
            self::stringValue($doctor->created_by),
            self::stringValue($doctor->updated_by),
            self::stringValue($doctor->created_at),
            self::stringValue($doctor->updated_at),
        ];
    }
}
