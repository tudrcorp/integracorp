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
    case DroppedAffiliationCollections = 'dropped_affiliation_collections';

    public function label(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'Cobranza por mes · General',
            self::CollectionsByStructure => 'Cobranza por mes · Por agencia o agente',
            self::DroppedAffiliationCollections => 'Cuotas de afiliaciones dadas de baja',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'Toda la cartera por cobrar: una fila por afiliación con su próxima cuota pendiente, ordenada por vencimiento.',
            self::CollectionsByStructure => 'La cobranza de una agencia con toda su estructura (agencias y agentes de su red) o de un agente, agrupada con subtotales.',
            self::DroppedAffiliationCollections => 'Cuotas por pagar de afiliaciones excluidas, inactivas o anuladas, con la fecha de baja y si vencen antes o después de ella, para decidir si se anulan o se mantienen como deuda.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'heroicon-o-clipboard-document-list',
            self::CollectionsByStructure => 'heroicon-o-building-office-2',
            self::DroppedAffiliationCollections => 'heroicon-o-user-minus',
        };
    }

    public function fileSlug(): string
    {
        return match ($this) {
            self::CollectionsGeneral => 'cobranza-general',
            self::CollectionsByStructure => 'cobranza-estructura',
            self::DroppedAffiliationCollections => 'cuotas-afiliaciones-de-baja',
        };
    }

    /**
     * Qué cuenta cada fila del reporte, para los mensajes de la pantalla.
     *
     * @return array{0: string, 1: string}
     */
    public function rowNoun(): array
    {
        return $this === self::DroppedAffiliationCollections ? ['cuota', 'cuotas'] : ['afiliación', 'afiliaciones'];
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
