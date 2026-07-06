<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Supplier;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class JuridicalProvidersExportService extends AbstractScheduledEntityExportService
{
    protected function exportConfigKey(): string
    {
        return 'juridical_providers';
    }

    protected function defaultFilenamePrefix(): string
    {
        return 'integracorp_proveedores_juridicos';
    }

    /**
     * @return array{recordCount: int, rowCount: int, noteCount: int}
     */
    protected function populateSpreadsheet(Writer $writer): array
    {
        $recordCount = 0;
        $rowCount = 0;
        $noteCount = 0;

        Supplier::query()
            ->with([
                'state:id,definition',
                'city:id,definition',
                'SupplierClasificacion:id,description',
                'supplierContactPrincipals:id,supplier_id,email',
                'supplierObservacions' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            ])
            ->orderBy('id')
            ->chunkById(100, function ($suppliers) use (&$recordCount, &$rowCount, &$noteCount, $writer): void {
                foreach ($suppliers as $supplier) {
                    /** @var Supplier $supplier */
                    $recordCount++;
                    $observations = $supplier->supplierObservacions;
                    $noteCount += $observations->count();

                    $writer->addRow(Row::fromValues(self::mapRow($supplier, $observations)));
                    $rowCount++;
                }
            });

        return compact('recordCount', 'rowCount', 'noteCount');
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'ID',
            'Estado',
            'Ciudad',
            'Zona Cobertura',
            'Clasificación',
            'Tipo Clínica',
            'Horario',
            'Estatus Convenio',
            'Estatus Sistema',
            'Nombre Proveedor',
            'RIF',
            'Razón Social',
            'Teléfono Celular',
            'Teléfono Local',
            'Correos Principales',
            'Afiliación Proveedor',
            'Ubicación Principal',
            'Convenio Pago',
            'Tiempo Crédito',
            'Observaciones (campo)',
            'Cantidad Notas Bitácora',
            'Notas Bitácora',
            'Creado Por',
            'Actualizado Por',
            'Fecha Creación',
            'Fecha Actualización',
        ];
    }

    /**
     * @return list<string|int|null>
     */
    private static function mapRow(Supplier $supplier, $observations): array
    {
        $notesText = self::concatObservationEntries(
            $observations,
            fn ($observation) => $observation->observation,
            fn ($observation) => $observation->created_by,
            fn ($observation) => $observation->created_at,
        );

        $emails = $supplier->supplierContactPrincipals
            ->pluck('email')
            ->map(fn ($email): string => is_string($email) ? trim($email) : '')
            ->filter(fn (string $email): bool => $email !== '')
            ->unique()
            ->values()
            ->implode('; ');

        return [
            $supplier->id,
            self::stringValue($supplier->state?->definition),
            self::stringValue($supplier->city?->definition),
            self::stringifyList($supplier->state_services),
            self::stringValue($supplier->SupplierClasificacion?->description),
            self::stringValue($supplier->tipo_clinica),
            self::stringValue($supplier->horario),
            self::stringValue($supplier->status_convenio),
            self::stringValue($supplier->status_sistema),
            self::stringValue($supplier->name),
            self::stringValue($supplier->rif),
            self::stringValue($supplier->razon_social),
            self::stringValue($supplier->personal_phone),
            self::stringValue($supplier->local_phone),
            $emails !== '' ? $emails : null,
            self::stringValue($supplier->afiliacion_proveedor),
            self::stringValue($supplier->ubicacion_principal),
            self::stringValue($supplier->convenio_pago),
            self::stringValue($supplier->tiempo_credito),
            self::stringValue($supplier->observaciones),
            $observations->count(),
            $notesText,
            self::stringValue($supplier->created_by),
            self::stringValue($supplier->updated_by),
            self::stringValue($supplier->created_at),
            self::stringValue($supplier->updated_at),
        ];
    }
}
