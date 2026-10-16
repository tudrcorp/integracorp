<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reportes del Centro de Reportes de Administración.
 */
enum AdministrationReportType: string
{
    case CollectionsGeneral = 'collections_general';
    case CollectionsByStructure = 'collections_by_structure';

    public function label(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'Cobranza por mes · General',
            self::CollectionsByStructure => 'Cobranza por mes · Por agencia o agente',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'Toda la cartera por cobrar: una fila por afiliación con su próxima cuota pendiente, ordenada por vencimiento.',
            self::CollectionsByStructure => 'La cobranza de una agencia con toda su estructura (agencias y agentes de su red) o de un agente, agrupada con subtotales.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'heroicon-o-clipboard-document-list',
            self::CollectionsByStructure => 'heroicon-o-building-office-2',
        };
    }

    public function fileSlug(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'cobranza-general',
            self::CollectionsByStructure => 'cobranza-estructura',
        };
    }

    public function isGrouped(): bool
    {
        return $this === self::CollectionsByStructure;
    }

    /**
     * @return list<OperationReportFormat>
     */
    public function formats(): array
    {
        return [OperationReportFormat::Excel, OperationReportFormat::Csv, OperationReportFormat::Pdf];
    }
}
