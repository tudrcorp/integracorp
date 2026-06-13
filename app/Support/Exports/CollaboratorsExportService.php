<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\Collaborator;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class CollaboratorsExportService extends AbstractScheduledEntityExportService
{
    protected function exportConfigKey(): string
    {
        return 'collaborators';
    }

    protected function defaultFilenamePrefix(): string
    {
        return 'integracorp_colaboradores';
    }

    /**
     * @return array{recordCount: int, rowCount: int, noteCount: int}
     */
    protected function populateSpreadsheet(Writer $writer): array
    {
        $recordCount = 0;
        $rowCount = 0;

        Collaborator::query()
            ->orderBy('id')
            ->chunkById(100, function ($collaborators) use (&$recordCount, &$rowCount, $writer): void {
                foreach ($collaborators as $collaborator) {
                    /** @var Collaborator $collaborator */
                    $recordCount++;
                    $writer->addRow(Row::fromValues(self::mapRow($collaborator)));
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
            'DNI',
            'Fecha Nacimiento',
            'Fecha Ingreso Empresa',
            'Departamento',
            'Cargo',
            'Sexo',
            'Teléfono',
            'Email Corporativo',
            'Email Alternativo',
            'Estatus',
            'Creado Por',
            'Fecha Creación',
            'Fecha Actualización',
        ];
    }

    /**
     * @return list<string|int|null>
     */
    private static function mapRow(Collaborator $collaborator): array
    {
        return [
            $collaborator->id,
            self::stringValue($collaborator->code),
            self::stringValue($collaborator->full_name),
            self::stringValue($collaborator->dni),
            self::stringValue($collaborator->birth_date),
            self::stringValue($collaborator->company_init_date),
            self::stringValue($collaborator->departament),
            self::stringValue($collaborator->position),
            self::stringValue($collaborator->sex),
            self::stringValue($collaborator->phone),
            self::stringValue($collaborator->coorporate_email),
            self::stringValue($collaborator->alternative_email),
            self::stringValue($collaborator->status),
            self::stringValue($collaborator->created_by),
            self::stringValue($collaborator->created_at),
            self::stringValue($collaborator->updated_at),
        ];
    }
}
