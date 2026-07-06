<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Agent;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class AgentsExportService extends AbstractScheduledEntityExportService
{
    protected function exportConfigKey(): string
    {
        return 'agents';
    }

    protected function defaultFilenamePrefix(): string
    {
        return 'integracorp_agentes';
    }

    /**
     * @return array{recordCount: int, rowCount: int, noteCount: int}
     */
    protected function populateSpreadsheet(Writer $writer): array
    {
        $recordCount = 0;
        $rowCount = 0;
        $noteCount = 0;

        Agent::query()
            ->with([
                'agency:id,name,code',
                'country:id,name',
                'state:id,definition',
                'city:id,definition',
                'region:id,definition',
                'typeAgent:id,definition',
                'notes' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
                'observationCommercialStructures' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id'),
            ])
            ->orderBy('id')
            ->chunkById(100, function ($agents) use (&$recordCount, &$rowCount, &$noteCount, $writer): void {
                foreach ($agents as $agent) {
                    /** @var Agent $agent */
                    $recordCount++;
                    $blogNotes = $agent->notes;
                    $commercialObservations = $agent->observationCommercialStructures;
                    $noteCount += $blogNotes->count() + $commercialObservations->count();

                    $writer->addRow(Row::fromValues(self::mapRow($agent, $blogNotes, $commercialObservations)));
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
            'Código Agente',
            'Pertenece A',
            'Tipo Agente',
            'Nombre Completo',
            'CI',
            'RIF',
            'Fecha Nacimiento',
            'Dirección',
            'Email',
            'Teléfono Principal',
            'Instagram',
            'País',
            'Región',
            'Estado',
            'Ciudad',
            'Sexo',
            'Estado Civil',
            'Código Agencia',
            'Nombre Agencia',
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
    private static function mapRow(Agent $agent, $blogNotes, $commercialObservations): array
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
            $agent->id,
            self::stringValue($agent->code_agent),
            self::stringValue($agent->owner_code),
            self::stringValue($agent->typeAgent?->definition),
            self::stringValue($agent->name),
            self::stringValue($agent->ci),
            self::stringValue($agent->rif),
            self::stringValue($agent->birth_date),
            self::stringValue($agent->address),
            self::stringValue($agent->email),
            self::stringValue($agent->phone),
            self::stringValue($agent->user_instagram),
            self::stringValue($agent->country?->name),
            self::stringValue($agent->region?->definition ?? $agent->getAttribute('region')),
            self::stringValue($agent->state?->definition),
            self::stringValue($agent->city?->definition),
            self::stringValue($agent->sex),
            self::stringValue($agent->marital_status),
            self::stringValue($agent->code_agency ?? $agent->agency?->code),
            self::stringValue($agent->agency?->name_corporative),
            self::stringValue($agent->name_contact_2),
            self::stringValue($agent->email_contact_2),
            self::stringValue($agent->phone_contact_2),
            self::stringValue($agent->status),
            self::stringValue($agent->comments),
            $blogNotes->count(),
            $blogNotesText,
            $commercialObservations->count(),
            $commercialText,
            self::stringValue($agent->user_tdev),
            self::stringValue($agent->created_by),
            self::stringValue($agent->created_at),
            self::stringValue($agent->updated_at),
        ];
    }
}
