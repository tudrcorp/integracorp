<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Agency;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class AgenciesExportService extends AbstractScheduledEntityExportService
{
    protected function exportConfigKey(): string
    {
        return 'agencies';
    }

    protected function defaultFilenamePrefix(): string
    {
        return 'integracorp_agencias';
    }

    /**
     * @return array{recordCount: int, rowCount: int, noteCount: int}
     */
    protected function populateSpreadsheet(Writer $writer): array
    {
        $recordCount = 0;
        $rowCount = 0;
        $noteCount = 0;

        Agency::query()
            ->with([
                'country:id,name',
                'state:id,definition',
                'city:id,definition',
                'region:id,definition',
                'typeAgency:id,definition',
                'notes' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
                'observationCommercialStructures' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            ])
            ->orderBy('id')
            ->chunkById(100, function ($agencies) use (&$recordCount, &$rowCount, &$noteCount, $writer): void {
                foreach ($agencies as $agency) {
                    /** @var Agency $agency */
                    $recordCount++;
                    $blogNotes = $agency->notes;
                    $commercialObservations = $agency->observationCommercialStructures;
                    $noteCount += $blogNotes->count() + $commercialObservations->count();

                    $writer->addRow(Row::fromValues(self::mapRow($agency, $blogNotes, $commercialObservations)));
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
            'Código Agencia',
            'Pertenece A',
            'Tipo Agencia',
            'Nombre Corporativo',
            'RIF',
            'CI Responsable',
            'Representante Legal',
            'Dirección',
            'Email',
            'Teléfono Principal',
            'Instagram',
            'País',
            'Región',
            'Estado',
            'Ciudad',
            'Nombre Contacto',
            'Email Contacto',
            'Teléfono Contacto',
            'Estatus',
            'Comentarios',
            'Cantidad Notas Bitácora',
            'Notas Bitácora',
            'Cantidad Obs. Estructura Comercial',
            'Obs. Estructura Comercial',
            'Usuario TDEV',
            'Creado Por',
            'Fecha Creación',
            'Fecha Actualización',
        ];
    }

    /**
     * @return list<string|int|null>
     */
    private static function mapRow(Agency $agency, $blogNotes, $commercialObservations): array
    {
        $blogNotesText = self::concatObservationEntries(
            $blogNotes,
            fn ($note) => $note->note,
            fn ($note) => $note->created_by,
            fn ($note) => $note->created_at,
        );

        $commercialText = self::concatObservationEntries(
            $commercialObservations,
            fn ($observation) => $observation->observation,
            fn ($observation) => $observation->created_by,
            fn ($observation) => $observation->date ?? $observation->created_at,
        );

        return [
            $agency->id,
            self::stringValue($agency->code),
            self::stringValue($agency->owner_code),
            self::stringValue($agency->typeAgency?->definition),
            self::stringValue($agency->name_corporative),
            self::stringValue($agency->rif),
            self::stringValue($agency->ci_responsable),
            self::stringValue($agency->name_representative),
            self::stringValue($agency->address),
            self::stringValue($agency->email),
            self::stringValue($agency->phone),
            self::stringValue($agency->user_instagram),
            self::stringValue($agency->country?->name),
            self::stringValue($agency->region?->definition ?? $agency->getAttribute('region')),
            self::stringValue($agency->state?->definition),
            self::stringValue($agency->city?->definition),
            self::stringValue($agency->name_contact_2),
            self::stringValue($agency->email_contact_2),
            self::stringValue($agency->phone_contact_2),
            self::stringValue($agency->status),
            self::stringValue($agency->comments),
            $blogNotes->count(),
            $blogNotesText,
            $commercialObservations->count(),
            $commercialText,
            self::stringValue($agency->user_tdev),
            self::stringValue($agency->created_by),
            self::stringValue($agency->created_at),
            self::stringValue($agency->updated_at),
        ];
    }
}
