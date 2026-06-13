<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\DoctorNurse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class NaturalProvidersExportService extends AbstractScheduledEntityExportService
{
    protected function exportConfigKey(): string
    {
        return 'natural_providers';
    }

    protected function defaultFilenamePrefix(): string
    {
        return 'integracorp_proveedores_naturales';
    }

    /**
     * @return array{recordCount: int, rowCount: int, noteCount: int}
     */
    protected function populateSpreadsheet(Writer $writer): array
    {
        $recordCount = 0;
        $rowCount = 0;
        $noteCount = 0;

        DoctorNurse::query()
            ->with([
                'supplierClasificacion:id,description',
                'state:id,definition',
                'city:id,definition',
                'doctorNurseObservacions' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            ])
            ->orderBy('id')
            ->chunkById(100, function ($providers) use (&$recordCount, &$rowCount, &$noteCount, $writer): void {
                foreach ($providers as $provider) {
                    /** @var DoctorNurse $provider */
                    $recordCount++;
                    $observations = $provider->doctorNurseObservacions;
                    $noteCount += $observations->count();

                    $writer->addRow(Row::fromValues(self::mapRow($provider, $observations)));
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
            'Nombre',
            'Especialidad',
            'Razón Social',
            'RIF',
            'Estatus Convenio',
            'Estatus Sistema',
            'Clasificación',
            'Estado',
            'Ciudad',
            'Zona Cobertura',
            'Tipo Clínica',
            'Horario',
            'Teléfono Personal',
            'Teléfono Local',
            'Correo Principal',
            'Ubicación Principal',
            'Convenio Pago',
            'Tiempo Crédito',
            'Afiliación Proveedor',
            'Cantidad Notas',
            'Notas',
            'Creado Por',
            'Actualizado Por',
            'Fecha Creación',
            'Fecha Actualización',
        ];
    }

    /**
     * @return list<string|int|null>
     */
    private static function mapRow(DoctorNurse $provider, $observations): array
    {
        $notesText = self::concatObservationEntries(
            $observations,
            fn ($observation) => $observation->observation,
            fn ($observation) => $observation->created_by,
            fn ($observation) => $observation->created_at,
        );

        return [
            $provider->id,
            self::stringValue($provider->name),
            self::stringValue($provider->speciality),
            self::stringValue($provider->razon_social),
            self::stringValue($provider->rif),
            self::stringValue($provider->status_convenio),
            self::stringValue($provider->status_sistema),
            self::stringValue($provider->supplierClasificacion?->description),
            self::stringValue($provider->state?->definition ?? $provider->state),
            self::stringValue($provider->city?->definition ?? $provider->city),
            self::stringValue($provider->coverage_zone),
            self::stringValue($provider->tipo_clinica),
            self::stringValue($provider->horario),
            self::stringValue($provider->personal_phone),
            self::stringValue($provider->local_phone),
            self::stringValue($provider->correo_principal),
            self::stringValue($provider->ubicacion_principal),
            self::stringValue($provider->convenio_pago),
            self::stringValue($provider->tiempo_credito),
            self::stringValue($provider->afiliacion_proveedor),
            $observations->count(),
            $notesText,
            self::stringValue($provider->created_by),
            self::stringValue($provider->updated_by),
            self::stringValue($provider->created_at),
            self::stringValue($provider->updated_at),
        ];
    }
}
